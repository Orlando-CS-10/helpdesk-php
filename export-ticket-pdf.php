<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/business_hours.php';
require_once __DIR__ . '/app/helpers/sla_helper.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Lima');
}

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';

if (!in_array($currentRole, ['ADMIN', 'TECH'], true)) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticketId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo 'No se encontró Dompdf. Ejecuta en la raíz del proyecto: composer require dompdf/dompdf';
    exit;
}

require_once $autoloadPath;

use Dompdf\Dompdf;
use Dompdf\Options;

function e_pdf($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function pdfDate(?string $date): string
{
    if (empty($date)) {
        return 'No disponible';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'No disponible';
}

function pdfRoleLabel(?string $role): string
{
    return match ($role) {
        'ADMIN' => 'Administrador',
        'TECH' => 'Técnico',
        'CLIENT' => 'Cliente',
        default => $role ?: 'No definido',
    };
}

function pdfStatusLabel(?string $status): string
{
    if (empty($status)) {
        return 'No definido';
    }

    return ucfirst(strtolower(str_replace('_', ' ', $status)));
}

function pdfBusinessDuration(?string $start, ?string $end, bool $isPending = false): string
{
    if (empty($start)) {
        return 'No disponible';
    }

    if ($isPending || empty($end)) {
        return 'Pendiente';
    }

    try {
        if (function_exists('formatBusinessTimeStatus')) {
            return formatBusinessTimeStatus($start, $end, false);
        }

        if (function_exists('calculateBusinessHours')) {
            $hours = (float)calculateBusinessHours($start, $end);
            $totalMinutes = max(0, (int)round($hours * 60));
            $h = intdiv($totalMinutes, 60);
            $m = $totalMinutes % 60;
            return sprintf('%d h %02d min', $h, $m);
        }
    } catch (Throwable $e) {
        // Fallback abajo.
    }

    $seconds = max(0, strtotime($end) - strtotime($start));
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return sprintf('%d h %02d min', $hours, $minutes);
}

function pdfInitials(?string $name): string
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        }
        if (mb_strlen($initials, 'UTF-8') >= 2) {
            break;
        }
    }

    return $initials ?: 'U';
}

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function renderRows(array $rows, array $columns, string $emptyMessage): string
{
    if (empty($rows)) {
        return '<div class="empty-box">' . e_pdf($emptyMessage) . '</div>';
    }

    $html = '<table class="data-table"><thead><tr>';
    foreach ($columns as $label => $callback) {
        $html .= '<th>' . e_pdf($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $callback) {
            $html .= '<td>' . $callback($row) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}


function pdfInlineAttachmentDataUri(array $attachment): ?string
{
    if ((int)($attachment['is_inline'] ?? 0) !== 1) {
        return null;
    }

    $relativePath = ltrim((string)($attachment['storage_path'] ?? ''), '/');
    $mimeType = (string)($attachment['mime_type'] ?? '');

    if ($relativePath === '' || !str_starts_with($mimeType, 'image/')) {
        return null;
    }

    $absolutePath = ticketStorageBasePath() . '/' . $relativePath;

    if (!is_file($absolutePath)) {
        return null;
    }

    $content = file_get_contents($absolutePath);

    if ($content === false) {
        return null;
    }

    return 'data:' . $mimeType . ';base64,' . base64_encode($content);
}

function pdfRenderMessageBody(array $message, array $attachments): string
{
    $format = strtolower((string)($message['message_format'] ?? 'plain'));
    $body = $format === 'html'
        ? ticketSanitizeRichHtml((string)($message['message'] ?? ''))
        : nl2br(e_pdf($message['message'] ?? ''));

    foreach ($attachments as $attachment) {
        $attachmentId = (int)($attachment['id'] ?? 0);
        $dataUri = pdfInlineAttachmentDataUri($attachment);

        if ($attachmentId <= 0 || $dataUri === null) {
            continue;
        }

        $publicUrl = '/helpdesk-php/download-message-attachment.php?id='
            . $attachmentId
            . '&inline=1';

        $body = str_replace($publicUrl, $dataUri, $body);
        $body = str_replace(
            htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8'),
            $dataUri,
            $body
        );
    }

    return '<div class="pdf-rich-message">' . $body . '</div>';
}

function pdfRenderMessageDocuments(array $attachments): string
{
    $documents = array_values(array_filter(
        $attachments,
        static fn(array $attachment): bool => (int)($attachment['is_inline'] ?? 0) !== 1
    ));

    if (empty($documents)) {
        return '';
    }

    $html = '<div class="pdf-attachments">';

    foreach ($documents as $attachment) {
        $extension = strtoupper(pathinfo(
            (string)($attachment['original_name'] ?? ''),
            PATHINFO_EXTENSION
        ));

        $html .= '<div class="pdf-attachment">'
            . '<strong>' . e_pdf($extension !== '' ? $extension : 'FILE') . '</strong>'
            . '<span>' . e_pdf($attachment['original_name'] ?? 'Archivo adjunto') . '</span>'
            . '<em>' . e_pdf(ticketFormatBytes((int)($attachment['file_size'] ?? 0))) . '</em>'
            . '</div>';
    }

    return $html . '</div>';
}

// =============================
// Datos del ticket
// =============================
$sqlTicket = "SELECT
                t.*,
                requester.name AS requester_name,
                requester.email AS requester_email,
                requester.phone AS requester_phone,
                requester.position AS requester_position,
                requester.company AS requester_company,
                requester.company_id AS requester_company_id,
                assigned.name AS assigned_name,
                assigned.email AS assigned_email,
                assigned.role AS assigned_role,
                assigned.tech_level AS assigned_level,
                company.business_name AS company_business_name,
                company.trade_name AS company_trade_name,
                company.ruc AS company_ruc,
                company.sla_contract_type AS sla_contract_type
              FROM tickets t
              INNER JOIN users requester ON requester.id = t.requester_id
              LEFT JOIN users assigned ON assigned.id = t.assigned_to
              LEFT JOIN client_companies company
                ON company.id = COALESCE(t.company_id, requester.company_id)
              WHERE t.id = :ticket_id
              LIMIT 1";

$stmtTicket = $pdo->prepare($sqlTicket);
$stmtTicket->execute(['ticket_id' => $ticketId]);
$ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$publicFormatSelect = ticketColumnExists($pdo, 'ticket_messages', 'message_format')
    ? 'tm.message_format'
    : "'plain' AS message_format";

$sqlMessages = "SELECT tm.*, {$publicFormatSelect}, u.name, u.role
                FROM ticket_messages tm
                INNER JOIN users u ON u.id = tm.user_id
                WHERE tm.ticket_id = :ticket_id
                ORDER BY tm.created_at ASC, tm.id ASC";
$stmtMessages = $pdo->prepare($sqlMessages);
$stmtMessages->execute(['ticket_id' => $ticketId]);
$messages = $stmtMessages->fetchAll(PDO::FETCH_ASSOC);

$messageAttachments = ticketLoadAttachmentsMap(
    $pdo,
    'PUBLIC',
    array_column($messages, 'id')
);

$sqlActivities = "SELECT *
                  FROM ticket_activity
                  WHERE ticket_id = :ticket_id
                  ORDER BY created_at ASC, id ASC";
$stmtActivities = $pdo->prepare($sqlActivities);
$stmtActivities->execute(['ticket_id' => $ticketId]);
$activities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);

$sqlFeedback = "SELECT *
                FROM ticket_feedback
                WHERE ticket_id = :ticket_id
                LIMIT 1";
$stmtFeedback = $pdo->prepare($sqlFeedback);
$stmtFeedback->execute(['ticket_id' => $ticketId]);
$feedback = $stmtFeedback->fetch(PDO::FETCH_ASSOC);

$sqlClientStats = "SELECT
                    COUNT(*) AS total_tickets,
                    SUM(CASE WHEN status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO') THEN 1 ELSE 0 END) AS open_tickets,
                    SUM(CASE WHEN status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_tickets
                  FROM tickets
                  WHERE requester_id = :client_id";
$stmtClientStats = $pdo->prepare($sqlClientStats);
$stmtClientStats->execute(['client_id' => $ticket['requester_id']]);
$clientStats = $stmtClientStats->fetch(PDO::FETCH_ASSOC) ?: [];

$sqlClientTickets = "SELECT id, subject, status, priority, created_at
                     FROM tickets
                     WHERE requester_id = :client_id
                     ORDER BY created_at DESC
                     LIMIT 10";
$stmtClientTickets = $pdo->prepare($sqlClientTickets);
$stmtClientTickets->execute(['client_id' => $ticket['requester_id']]);
$clientTickets = $stmtClientTickets->fetchAll(PDO::FETCH_ASSOC);

$internalMessages = [];
$internalMessageAttachments = [];

if (ticketTableExists($pdo, 'ticket_internal_messages')) {
    $internalFormatSelect = ticketColumnExists(
        $pdo,
        'ticket_internal_messages',
        'message_format'
    )
        ? 'tim.message_format'
        : "'plain' AS message_format";

    $sqlInternal = "SELECT tim.*, {$internalFormatSelect}, u.name, u.role
                    FROM ticket_internal_messages tim
                    INNER JOIN users u ON u.id = tim.user_id
                    WHERE tim.ticket_id = :ticket_id
                      AND (tim.deleted_at IS NULL OR tim.deleted_at = '0000-00-00 00:00:00')
                    ORDER BY tim.created_at ASC, tim.id ASC";
    $stmtInternal = $pdo->prepare($sqlInternal);
    $stmtInternal->execute(['ticket_id' => $ticketId]);
    $internalMessages = $stmtInternal->fetchAll(PDO::FETCH_ASSOC);

    $internalMessageAttachments = ticketLoadAttachmentsMap(
        $pdo,
        'INTERNAL',
        array_column($internalMessages, 'id')
    );
}

$firstResponseAt = $ticket['level_first_response_at'] ?? $ticket['first_response_at'] ?? null;
$closedAt = $ticket['closed_at'] ?? null;

if (empty($closedAt) && ($ticket['status'] ?? '') === 'CERRADO') {
    $closedAt = $ticket['updated_at'] ?? null;
}

$ttaHours = getTicketTtaHours($ticket);
$ttrHours = getTicketTtrHours($ticket);
$ttaLabel = $ttaHours === null ? 'Pendiente' : formatSlaDuration($ttaHours);
$ttrLabel = $ttrHours === null ? 'Pendiente' : formatSlaDuration($ttrHours);

$slaTimer = getSlaTimerData($ticket);
$slaLabel = $slaTimer['status_label'] ?? getSlaStatusLabel($ticket);
$slaClass = match (true) {
    str_contains($slaLabel, 'cumplido'), $slaLabel === 'Dentro del SLA' => 'badge-success',
    str_contains($slaLabel, 'vencido'), str_contains($slaLabel, 'fuera') => 'badge-danger',
    default => 'badge-pending',
};

$logoPathPng = __DIR__ . '/public/assets/img/pronet-logo.png';
$logoBase64 = '';
if (extension_loaded('gd') && file_exists($logoPathPng)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPathPng));
}

$generatedAt = date('d/m/Y H:i');
$filename = 'ticket_' . (int)$ticket['id'] . '_reporte.pdf';

$messagesHtml = '';
if (empty($messages)) {
    $messagesHtml = '<div class="empty-box">No hay mensajes registrados en la conversación.</div>';
} else {
    foreach ($messages as $message) {
        $attachments = $messageAttachments[(int)$message['id']] ?? [];

        $messagesHtml .= '<div class="message-box">
            <div class="avatar">' . e_pdf(pdfInitials($message['name'] ?? '')) . '</div>
            <div class="message-content">
                <div class="message-head">
                    <strong>' . e_pdf($message['name'] ?? 'Usuario') . '</strong>
                    <span>' . e_pdf(pdfRoleLabel($message['role'] ?? '')) . '</span>
                    <em>' . e_pdf(pdfDate($message['created_at'] ?? null)) . '</em>
                </div>'
                . pdfRenderMessageBody($message, $attachments)
                . pdfRenderMessageDocuments($attachments)
            . '</div>
        </div>';
    }
}

$internalHtml = '';
if (empty($internalMessages)) {
    $internalHtml = '<div class="empty-box">No hay mensajes internos registrados.</div>';
} else {
    foreach ($internalMessages as $message) {
        $attachments = $internalMessageAttachments[(int)$message['id']] ?? [];

        $internalHtml .= '<div class="message-box internal-message-box">
            <div class="avatar internal-avatar">' . e_pdf(pdfInitials($message['name'] ?? '')) . '</div>
            <div class="message-content">
                <div class="message-head">
                    <strong>' . e_pdf($message['name'] ?? 'Usuario') . '</strong>
                    <span>' . e_pdf(pdfRoleLabel($message['role'] ?? '')) . '</span>
                    <em>' . e_pdf(pdfDate($message['created_at'] ?? null)) . '</em>
                </div>'
                . pdfRenderMessageBody($message, $attachments)
                . pdfRenderMessageDocuments($attachments)
            . '</div>
        </div>';
    }
}

$activitiesHtml = renderRows(
    $activities,
    [
        'Fecha' => fn($row) => e_pdf(pdfDate($row['created_at'] ?? null)),
        'Actor' => fn($row) => e_pdf(($row['actor_name'] ?? 'Sistema') . ' - ' . pdfRoleLabel($row['actor_role'] ?? '')),
        'Actividad' => fn($row) => e_pdf($row['activity_type'] ?? $row['action_type'] ?? 'Actividad'),
        'Descripción' => fn($row) => nl2br(e_pdf($row['description'] ?? '')),
    ],
    'No hay actividades registradas para este ticket.'
);

$clientTicketsHtml = renderRows(
    $clientTickets,
    [
        'Ticket' => fn($row) => '#' . (int)$row['id'],
        'Asunto' => fn($row) => e_pdf($row['subject'] ?? ''),
        'Estado' => fn($row) => e_pdf(pdfStatusLabel($row['status'] ?? '')),
        'Prioridad' => fn($row) => e_pdf($row['priority'] ?? ''),
        'Fecha' => fn($row) => e_pdf(pdfDate($row['created_at'] ?? null)),
    ],
    'El cliente no tiene otros tickets recientes.'
);

$feedbackHtml = '<div class="empty-box">No hay evaluación del cliente registrada para este ticket.</div>';
if (!empty($feedback)) {
    $feedbackHtml = '<div class="info-grid three">
        <div class="info-item"><span>Calificación</span><strong>' . e_pdf((int)($feedback['rating'] ?? 0)) . '/5</strong></div>
        <div class="info-item"><span>¿Se resolvió?</span><strong>' . e_pdf($feedback['resolved'] ?? 'No registrado') . '</strong></div>
        <div class="info-item full"><span>Comentario</span><strong>' . nl2br(e_pdf($feedback['comment'] ?? 'Sin comentario.')) . '</strong></div>
    </div>';
}

$html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22px 24px 26px; }
    body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 11px; line-height: 1.45; }
    .header { width: 100%; border-radius: 18px; background: #0f3d2e; color: #ffffff; padding: 18px 20px; box-sizing: border-box; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-title { font-size: 20px; font-weight: 800; margin: 0 0 5px; }
    .header-sub { color: #d7e3dd; margin: 0; font-size: 11px; }
    .logo-cell { text-align: right; vertical-align: middle; width: 180px; }
    .logo { max-width: 155px; max-height: 64px; }
    .logo-fallback { display: inline-block; border: 1px solid rgba(255,255,255,0.28); border-radius: 12px; padding: 8px 12px; font-weight: 900; color: #ffffff; }
    .meta { margin-top: 10px; color: #64748b; font-size: 10px; text-align: right; }
    .section { margin-top: 14px; page-break-inside: avoid; }
    .section.breakable { page-break-inside: auto; }
    .section-title { margin: 0 0 8px; padding: 8px 10px; border-left: 5px solid #ff7a00; border-radius: 9px; background: #eef6f2; color: #0f3d2e; font-size: 13px; font-weight: 900; }
    .ticket-summary { border: 1px solid #dbe3ec; border-radius: 16px; padding: 14px; background: #ffffff; }
    .ticket-code { display: inline-block; padding: 5px 9px; border-radius: 999px; background: #fff7ed; color: #9a3412; font-weight: 900; margin-bottom: 8px; }
    .ticket-subject { margin: 0 0 6px; font-size: 18px; color: #0f172a; }
    .ticket-description { margin: 0; color: #334155; }
    .badge { display: inline-block; padding: 5px 9px; border-radius: 999px; font-weight: 900; font-size: 10px; }
    .badge-status { background: #eef6f2; color: #0f3d2e; border: 1px solid #cfe7db; }
    .badge-priority { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .badge-success { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
    .badge-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .badge-pending { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .info-grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin-left: -8px; }
    .info-grid td, .info-item { border: 1px solid #e7edf4; border-radius: 12px; background: #f8fafc; padding: 10px; vertical-align: top; }
    .info-grid span, .info-item span { display: block; color: #64748b; font-size: 10px; font-weight: 800; margin-bottom: 4px; }
    .info-grid strong, .info-item strong { color: #0f172a; font-size: 11px; }
    .three { display: block; }
    .full { margin-top: 8px; }
    .metrics-table { width: 100%; border-collapse: separate; border-spacing: 8px; margin-left: -8px; }
    .metric { border: 1px solid #dbe3ec; border-radius: 14px; padding: 10px; text-align: center; background: #ffffff; }
    .metric span { display: block; color: #64748b; font-size: 10px; font-weight: 800; margin-bottom: 5px; }
    .metric strong { display: block; color: #0f3d2e; font-size: 15px; font-weight: 900; }
    .message-box { display: table; width: 100%; margin-bottom: 8px; border: 1px solid #e7edf4; border-radius: 14px; background: #ffffff; page-break-inside: avoid; }
    .avatar { display: table-cell; width: 38px; padding: 10px; vertical-align: top; }
    .avatar::after { content: attr(data-initials); }
    .message-box .avatar { color: #ffffff; font-weight: 900; text-align: center; }
    .message-box .avatar { background: #0f3d2e; }
    .message-content { display: table-cell; padding: 9px 11px 9px 0; vertical-align: top; }
    .message-head { margin-bottom: 4px; }
    .message-head strong { color: #0f172a; font-size: 11px; }
    .message-head span { margin-left: 6px; padding: 2px 6px; border-radius: 999px; background: #eef6f2; color: #0f3d2e; font-size: 9px; font-weight: 900; }
    .message-head em { float: right; color: #64748b; font-style: normal; font-size: 9px; }
    .message-content p { margin: 0; color: #334155; }
    .internal-message-box { border-left: 4px solid #ff7a00; background: #fffbf7; }
    .internal-avatar { background: #ff7a00 !important; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { background: #0f3d2e; color: #ffffff; text-align: left; padding: 7px; font-size: 10px; }
    .data-table td { border: 1px solid #e7edf4; padding: 7px; vertical-align: top; }
    .data-table tr:nth-child(even) td { background: #f8fafc; }
    .empty-box { padding: 12px; border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; color: #64748b; text-align: center; }
    .footer { position: fixed; left: 24px; right: 24px; bottom: 9px; color: #94a3b8; font-size: 9px; text-align: center; }

    .pdf-rich-message { color: #334155; font-size: 10px; line-height: 1.5; }
    .pdf-rich-message p, .pdf-rich-message div { margin: 0 0 6px; }
    .pdf-rich-message h1, .pdf-rich-message h2, .pdf-rich-message h3 { color: #0f172a; margin: 8px 0 5px; }
    .pdf-rich-message h1 { font-size: 18px; }
    .pdf-rich-message h2 { font-size: 15px; }
    .pdf-rich-message h3 { font-size: 13px; }
    .pdf-rich-message ul, .pdf-rich-message ol { margin: 5px 0 7px 18px; padding: 0; }
    .pdf-rich-message blockquote { margin: 6px 0; padding: 6px 8px; border-left: 3px solid #ff7a00; background: #fff8f1; }
    .pdf-rich-message pre { padding: 7px; border-radius: 7px; background: #111827; color: #f8fafc; white-space: pre-wrap; }
    .pdf-rich-message img { display: block; max-width: 470px; max-height: 340px; margin: 7px 0; border: 1px solid #dbe3ec; border-radius: 9px; }
    .pdf-attachments { margin-top: 7px; }
    .pdf-attachment { display: table; width: 100%; margin-top: 4px; padding: 6px 8px; border: 1px solid #dbe3ec; border-radius: 8px; background: #f8fafc; box-sizing: border-box; }
    .pdf-attachment strong, .pdf-attachment span, .pdf-attachment em { display: table-cell; vertical-align: middle; }
    .pdf-attachment strong { width: 42px; color: #0f5132; font-size: 8px; }
    .pdf-attachment span { color: #334155; font-size: 9px; }
    .pdf-attachment em { width: 70px; color: #64748b; font-size: 8px; font-style: normal; text-align: right; }

</style>
</head>
<body>
<div class="header">
    <table class="header-table"><tr><td>
        <div class="header-title">Reporte de detalle de ticket</div>
        <p class="header-sub">Gestión del mantenimiento correctivo de redes y comunicaciones</p>
    </td><td class="logo-cell">' . ($logoBase64 ? '<img class="logo" src="' . $logoBase64 . '">' : '<span class="logo-fallback">PRONET SYSTEM S.A.C.</span>') . '</td></tr></table>
</div>
<div class="meta">Generado el ' . e_pdf($generatedAt) . ' por ' . e_pdf($currentUser['name'] ?? 'Usuario') . ' - ' . e_pdf(pdfRoleLabel($currentRole)) . '</div>

<div class="section">
    <div class="ticket-summary">
        <div class="ticket-code">Ticket #' . (int)$ticket['id'] . '</div>
        <h1 class="ticket-subject">' . e_pdf($ticket['subject'] ?? 'Sin asunto') . '</h1>
        <p class="ticket-description">' . nl2br(e_pdf($ticket['description'] ?? 'Sin descripción')) . '</p>
        <p style="margin:10px 0 0;"><span class="badge badge-status">' . e_pdf(pdfStatusLabel($ticket['status'] ?? '')) . '</span> <span class="badge badge-priority">Prioridad: ' . e_pdf($ticket['priority'] ?? 'N/D') . '</span> <span class="badge ' . $slaClass . '">SLA: ' . e_pdf($slaLabel) . '</span></p>
    </div>
</div>

<div class="section">
    <div class="section-title">Información general</div>
    <table class="info-grid"><tr>
        <td><span>Cliente</span><strong>' . e_pdf($ticket['requester_name'] ?? 'No disponible') . '</strong></td>
        <td><span>Técnico asignado</span><strong>' . e_pdf($ticket['assigned_name'] ?? 'Sin asignar') . '</strong></td>
        <td><span>Categoría</span><strong>' . e_pdf($ticket['category'] ?? 'No definida') . '</strong></td>
    </tr><tr>
        <td><span>Creación</span><strong>' . e_pdf(pdfDate($ticket['created_at'] ?? null)) . '</strong></td>
        <td><span>Última actualización</span><strong>' . e_pdf(pdfDate($ticket['updated_at'] ?? null)) . '</strong></td>
        <td><span>Cierre</span><strong>' . e_pdf(pdfDate($closedAt)) . '</strong></td>
    </tr></table>
</div>

<div class="section">
    <div class="section-title">Indicadores operativos</div>
    <table class="metrics-table"><tr>
        <td class="metric"><span>Contrato / objetivo</span><strong>' . e_pdf($slaTimer['contract_label'] ?? 'Contrato 8/5') . ' · ' . e_pdf(formatSlaDuration($ticket['sla_hours'] ?? 0)) . '</strong></td>
        <td class="metric"><span>Tiempo de respuesta (TTA)</span><strong>' . e_pdf($ttaLabel) . '</strong></td>
        <td class="metric"><span>Tiempo de resolución (TTR)</span><strong>' . e_pdf($ttrLabel) . '</strong></td>
        <td class="metric"><span>Cumplimiento SLA</span><strong>' . e_pdf($slaLabel) . '</strong></td>
    </tr></table>
</div>

<div class="section">
    <div class="section-title">Información del cliente</div>
    <table class="info-grid"><tr>
        <td><span>Nombre</span><strong>' . e_pdf($ticket['requester_name'] ?? 'No registrado') . '</strong></td>
        <td><span>Correo</span><strong>' . e_pdf($ticket['requester_email'] ?? 'No registrado') . '</strong></td>
        <td><span>Teléfono</span><strong>' . e_pdf($ticket['requester_phone'] ?? 'No registrado') . '</strong></td>
    </tr><tr>
        <td><span>Cargo</span><strong>' . e_pdf($ticket['requester_position'] ?? 'No registrado') . '</strong></td>
        <td><span>Empresa</span><strong>' . e_pdf($ticket['company_business_name'] ?? $ticket['requester_company'] ?? 'No registrado') . '</strong></td>
        <td><span>Tickets del cliente</span><strong>' . (int)($clientStats['total_tickets'] ?? 0) . ' total / ' . (int)($clientStats['open_tickets'] ?? 0) . ' activos / ' . (int)($clientStats['closed_tickets'] ?? 0) . ' cerrados</strong></td>
    </tr></table>
</div>

<div class="section breakable">
    <div class="section-title">Conversación del ticket</div>
    ' . $messagesHtml . '
</div>

<div class="section breakable">
    <div class="section-title">Actividad del ticket</div>
    ' . $activitiesHtml . '
</div>

<div class="section breakable">
    <div class="section-title">Conversación interna - solo equipo técnico</div>
    ' . $internalHtml . '
</div>

<div class="section">
    <div class="section-title">Evaluación del cliente</div>
    ' . $feedbackHtml . '
</div>

<div class="section breakable">
    <div class="section-title">Últimos tickets del cliente</div>
    ' . $clientTicketsHtml . '
</div>

<div class="footer">PRONET SYSTEM S.A.C. - Reporte generado automáticamente por el sistema Helpdesk.</div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($filename, ['Attachment' => true]);
exit;
