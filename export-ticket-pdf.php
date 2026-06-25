<?php

// Evita que avisos, espacios o salidas accidentales dañen el binario del PDF.
// En XAMPP suele existir output_buffering, por lo que una sola advertencia puede
// hacer que Chrome muestre "Error al cargar el documento PDF".
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('zlib.output_compression', '0');

/**
 * Muestra un error legible en lugar de entregar un PDF corrupto.
 */
function ticketPdfAbort(Throwable $exception): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        || (isset($_GET['debug']) && $_GET['debug'] === '1');

    $message = $isLocal
        ? $exception->getMessage() . ' en ' . $exception->getFile() . ':' . $exception->getLine()
        : 'No se pudo generar el reporte PDF. Revisa el registro de errores del servidor.';

    error_log('[PDF Ticket] ' . $exception);

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Error al generar PDF</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:40px;color:#172033}'
        . '.box{max-width:760px;margin:40px auto;background:#fff;border:1px solid #e2e7ef;border-radius:16px;padding:28px;box-shadow:0 12px 35px rgba(18,30,55,.08)}'
        . 'h1{font-size:22px;margin:0 0 12px}p{line-height:1.55;color:#4b5565}.detail{background:#f8fafc;border-radius:10px;padding:14px;word-break:break-word;font-family:Consolas,monospace;font-size:13px}'
        . 'a{display:inline-block;margin-top:18px;color:#0f5f9c;text-decoration:none;font-weight:700}</style></head><body>'
        . '<div class="box"><h1>No se pudo generar el PDF</h1>'
        . '<p>El sistema encontró un problema durante la exportación.</p>'
        . '<div class="detail">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<a href="javascript:history.back()">Volver al ticket</a></div></body></html>';
    exit;
}

set_exception_handler(static function (Throwable $exception): void {
    ticketPdfAbort($exception);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }

    ticketPdfAbort(new ErrorException(
        $error['message'],
        0,
        $error['type'],
        $error['file'],
        $error['line']
    ));
});

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/business_hours.php';
require_once __DIR__ . '/app/helpers/sla_helper.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';
<<<<<<< HEAD
=======
require_once __DIR__ . '/app/helpers/ticket_pdf_helper.php';
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)

requireLogin();

date_default_timezone_set('America/Lima');

$currentUser = user();
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

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

if (!is_file($autoloadPath)) {
    http_response_code(500);
    echo 'No se encontró Dompdf. Ejecuta en la raíz del proyecto: composer require dompdf/dompdf';
    exit;
}

require_once $autoloadPath;

use Dompdf\Dompdf;
use Dompdf\Options;

// Dompdf necesita estas extensiones. Si faltan, se muestra un mensaje claro
// en vez de enviar al visor un archivo vacío o incompleto.
$missingRequirements = [];

if (!class_exists('DOMDocument')) {
    $missingRequirements[] = 'DOM/XML';
}

if (!function_exists('mb_internal_encoding')) {
    $missingRequirements[] = 'mbstring';
}

if (!empty($missingRequirements)) {
    throw new RuntimeException(
        'Faltan extensiones de PHP requeridas por Dompdf: '
        . implode(', ', $missingRequirements)
        . '. En XAMPP abre php.ini, habilita extension=mbstring y extension=php_xml.dll, guarda y reinicia Apache.'
    );
}

if (!class_exists(Dompdf::class)) {
    throw new RuntimeException('Dompdf no pudo cargarse desde vendor/autoload.php.');
}

function ticketPdfQueryFlag(string $name, bool $default = true): bool
{
    if (!array_key_exists($name, $_GET)) {
        return $default;
    }

    return in_array(strtolower((string)$_GET[$name]), ['1', 'true', 'yes', 'on'], true);
}

function ticketPdfMessageCard(
    array $message,
    array $attachments,
    bool $compact,
    bool $includeImages,
    bool $includeDocuments,
    int $imageLimit = 2,
    bool $fullImageBody = false,
    bool $summarizeText = false
): string {
    $name = (string)($message['name'] ?? 'Usuario');
    $role = ticketPdfRoleLabel($message['role'] ?? '');
    $date = ticketPdfDate($message['created_at'] ?? null);
    $avatarContent = ticketPdfAvatarContent(
        $name,
        $message['profile_photo'] ?? null,
        27
    );
    $roleClass = match (strtoupper((string)($message['role'] ?? ''))) {
        'CLIENT' => 'client',
        'TECH' => 'tech',
        'ADMIN' => 'admin',
        default => 'system',
    };

    if ($compact) {
        $excerpt = ticketPdfExcerpt($message['message'] ?? '', 220);
        $excerpt = $excerpt !== ''
            ? $excerpt
            : 'Mensaje compuesto únicamente por archivos adjuntos.';

        return '<div class="internal-card">'
            . '<div class="internal-meta">'
            . '<span class="message-avatar avatar-' . $roleClass . '">' . $avatarContent . '</span>'
            . '<span class="internal-author"><strong>' . ticketPdfEscape($name) . '</strong>'
            . '<small>' . ticketPdfEscape($date) . '</small></span>'
            . '</div>'
            . '<p>' . ticketPdfEscape($excerpt) . '</p>'
            . '</div>';
    }

    if ($summarizeText) {
        $summary = ticketPdfExcerpt($message['message'] ?? '', 650);
        $body = $summary !== ''
            ? '<div class="pdf-rich-message">' . nl2br(ticketPdfEscape($summary)) . '</div>'
            : '';
    } else {
        $body = ticketPdfRenderRichBody($message, $attachments, false);
    }

    $images = $includeImages
        ? ticketPdfRenderInlineImages($attachments, $imageLimit)
        : '';
    $documents = $includeDocuments
        ? ticketPdfRenderDocuments($attachments)
        : '';

    return '<div class="message-card">'
        . '<div class="message-meta">'
        . '<span class="message-avatar avatar-' . $roleClass . '">' . $avatarContent . '</span>'
        . '<span class="message-name">' . ticketPdfEscape($name) . '</span>'
        . '<span class="message-role">' . ticketPdfEscape($role) . '</span>'
        . '<span class="message-date">' . ticketPdfEscape($date) . '</span>'
        . '</div>'
        . '<div class="message-content">'
        . $body
        . $images
        . $documents
        . '</div>'
        . '</div>';
}

function ticketPdfTimelineItem(array $activity): string
{
    $time = !empty($activity['created_at'])
        ? date('H:i', strtotime((string)$activity['created_at']))
        : '--:--';
    $actor = trim((string)($activity['actor_name'] ?? ''));
    $actor = $actor !== '' ? $actor : 'Sistema';
    $role = ticketPdfRoleLabel($activity['actor_role'] ?? 'SYSTEM');
    $type = strtoupper((string)($activity['activity_type'] ?? ''));
    $tone = match ($type) {
        'CREATED' => 'orange',
        'AUTO_ASSIGNED', 'ASSIGNED' => 'blue',
        'LEVEL_ESCALATED', 'LEVEL_DEESCALATED' => 'green',
        'SLA_BREACHED' => 'red',
        default => 'dark',
    };

    return '<div class="timeline-item">'
        . '<span class="timeline-time">' . ticketPdfEscape($time) . '</span>'
        . '<span class="timeline-dot dot-' . $tone . '"></span>'
        . '<div class="timeline-copy">'
        . '<strong>' . ticketPdfEscape(ticketPdfActivityLabel($activity['activity_type'] ?? '')) . '</strong>'
        . '<small>' . ticketPdfEscape($actor . ' · ' . $role) . '</small>'
        . '<p>' . ticketPdfEscape($activity['description'] ?? '') . '</p>'
        . '</div>'
        . '</div>';
}

<<<<<<< HEAD
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
=======
// =========================================================
// Carga de información
// =========================================================
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
$sqlTicket = "SELECT
                t.*,
                requester.name AS requester_name,
                requester.email AS requester_email,
                requester.phone AS requester_phone,
                requester.position AS requester_position,
                requester.company AS requester_company,
                requester.company_id AS requester_company_id,
<<<<<<< HEAD
=======
                requester.profile_photo AS requester_profile_photo,
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
                assigned.name AS assigned_name,
                assigned.email AS assigned_email,
                assigned.role AS assigned_role,
                assigned.tech_level AS assigned_level,
<<<<<<< HEAD
                company.business_name AS company_business_name,
                company.trade_name AS company_trade_name,
                company.ruc AS company_ruc,
=======
                assigned.profile_photo AS assigned_profile_photo,
                company.business_name AS company_business_name,
                company.trade_name AS company_trade_name,
                company.ruc AS company_ruc,
                company.phone AS company_phone,
                company.email AS company_email,
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
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

<<<<<<< HEAD
$publicFormatSelect = ticketColumnExists($pdo, 'ticket_messages', 'message_format')
    ? 'tm.message_format'
    : "'plain' AS message_format";

$sqlMessages = "SELECT tm.*, {$publicFormatSelect}, u.name, u.role
=======
$sqlMessages = "SELECT tm.*, u.name, u.role, u.profile_photo
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
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

$feedback = null;
if (ticketTableExists($pdo, 'ticket_feedback')) {
    $stmtFeedback = $pdo->prepare(
        'SELECT * FROM ticket_feedback WHERE ticket_id = :ticket_id LIMIT 1'
    );
    $stmtFeedback->execute(['ticket_id' => $ticketId]);
    $feedback = $stmtFeedback->fetch(PDO::FETCH_ASSOC) ?: null;
}

$stmtClientStats = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO') THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_tickets
     FROM tickets
     WHERE requester_id = :client_id"
);
$stmtClientStats->execute(['client_id' => $ticket['requester_id']]);
$clientStats = $stmtClientStats->fetch(PDO::FETCH_ASSOC) ?: [];

$stmtClientTickets = $pdo->prepare(
    'SELECT id, subject, status, priority, created_at
     FROM tickets
     WHERE requester_id = :client_id
     ORDER BY created_at DESC
     LIMIT 10'
);
$stmtClientTickets->execute(['client_id' => $ticket['requester_id']]);
$clientTickets = $stmtClientTickets->fetchAll(PDO::FETCH_ASSOC);

$internalMessages = [];
$internalMessageAttachments = [];

if (ticketTableExists($pdo, 'ticket_internal_messages')) {
<<<<<<< HEAD
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
=======
    $stmtInternal = $pdo->prepare(
        "SELECT tim.*, u.name, u.role, u.profile_photo
         FROM ticket_internal_messages tim
         INNER JOIN users u ON u.id = tim.user_id
         WHERE tim.ticket_id = :ticket_id
           AND (tim.deleted_at IS NULL OR tim.deleted_at = '0000-00-00 00:00:00')
         ORDER BY tim.created_at ASC, tim.id ASC"
    );
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
    $stmtInternal->execute(['ticket_id' => $ticketId]);
    $internalMessages = $stmtInternal->fetchAll(PDO::FETCH_ASSOC);

    $internalMessageAttachments = ticketLoadAttachmentsMap(
        $pdo,
        'INTERNAL',
        array_column($internalMessages, 'id')
    );
}

<<<<<<< HEAD
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
=======
$allAttachments = [];
if (ticketTableExists($pdo, 'ticket_message_attachments')) {
    $stmtAttachments = $pdo->prepare(
        'SELECT a.*, uploader.name AS uploader_name
         FROM ticket_message_attachments a
         LEFT JOIN users uploader ON uploader.id = a.uploaded_by
         WHERE a.ticket_id = :ticket_id
         ORDER BY a.created_at ASC, a.id ASC'
    );
    $stmtAttachments->execute(['ticket_id' => $ticketId]);
    $allAttachments = $stmtAttachments->fetchAll(PDO::FETCH_ASSOC);
}

$levelHistory = [];
if (ticketTableExists($pdo, 'ticket_level_history')) {
    $stmtLevelHistory = $pdo->prepare(
        'SELECT tlh.*, technician.name AS technician_name
         FROM ticket_level_history tlh
         LEFT JOIN users technician ON technician.id = tlh.technician_id
         WHERE tlh.ticket_id = :ticket_id
         ORDER BY tlh.assigned_at ASC, tlh.id ASC'
    );
    $stmtLevelHistory->execute(['ticket_id' => $ticketId]);
    $levelHistory = $stmtLevelHistory->fetchAll(PDO::FETCH_ASSOC);
}
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)

// =========================================================
// Configuración del reporte
// =========================================================
$reportType = strtolower((string)($_GET['type'] ?? 'executive'));
$reportType = $reportType === 'full' ? 'full' : 'executive';
$isExecutive = $reportType === 'executive';

$includePublic = !$isExecutive || ticketPdfQueryFlag('include_public', true);
$includeActivity = !$isExecutive || ticketPdfQueryFlag('include_activity', true);
$includeInternal = !$isExecutive || ticketPdfQueryFlag('include_internal', true);
$includeImages = !$isExecutive || ticketPdfQueryFlag('include_images', true);
$includeDocuments = !$isExecutive || ticketPdfQueryFlag('include_documents', true);

$publicLimit = $isExecutive ? 8 : 0;
$activityLimit = $isExecutive ? 15 : 0;
$internalLimit = $isExecutive ? 5 : 0;
$imageLimit = $isExecutive ? 2 : 0;

$selectedMessages = $isExecutive
    ? ticketPdfLastItems($messages, $publicLimit)
    : $messages;
$selectedInternalMessages = $isExecutive
    ? ticketPdfLastItems($internalMessages, $internalLimit)
    : $internalMessages;

$activityPresentation = $isExecutive
    ? ticketPdfPrepareExecutiveActivities($activities, $activityLimit)
    : [
        'selected' => $activities,
        'explicit' => $activities,
        'grouped' => [],
    ];

$closedAt = ticketPdfFindClosedAt($ticket, $activities);
$ticketForMetrics = $ticket;
$ticketForMetrics['closed_at'] = $closedAt;

$ttaHours = getTicketTtaHours($ticketForMetrics);
$ttrHours = getTicketTtrHours($ticketForMetrics);
$ttaLabel = $ttaHours === null ? 'Pendiente' : formatSlaDuration($ttaHours);
$ttrLabel = $ttrHours === null ? 'Pendiente' : formatSlaDuration($ttrHours);

$slaTimer = getSlaTimerData($ticketForMetrics);
$slaStatus = (string)($slaTimer['status_label'] ?? getSlaStatusLabel($ticketForMetrics));
$slaRemaining = (float)($slaTimer['remaining_signed_hours'] ?? 0);
$slaTimeLabel = $slaRemaining < 0
    ? formatSlaDuration(abs($slaRemaining)) . ' excedidos'
    : formatSlaDuration($slaRemaining) . ' restantes';
$slaTone = match (true) {
    str_contains(strtolower($slaStatus), 'vencido'),
    str_contains(strtolower($slaStatus), 'fuera') => 'danger',
    str_contains(strtolower($slaStatus), 'próximo'),
    str_contains(strtolower($slaStatus), 'seguimiento') => 'warning',
    default => 'success',
};

$companyName = trim((string)($ticket['company_business_name'] ?? ''));
if ($companyName === '') {
    $companyName = trim((string)($ticket['company_trade_name'] ?? ''));
}
if ($companyName === '') {
    $companyName = trim((string)($ticket['requester_company'] ?? ''));
}
if ($companyName === '') {
    $companyName = 'Empresa no registrada';
}

$documents = ticketPdfDocumentRows($allAttachments);
$imageCount = count(array_filter(
    $allAttachments,
    static fn(array $attachment): bool => (int)($attachment['is_inline'] ?? 0) === 1
));

$generatedAt = date('d/m/Y H:i');
$reportLabel = $isExecutive ? 'Reporte ejecutivo' : 'Reporte completo';

$companyLogoPayload = ticketPdfLocalImagePayload(
    __DIR__ . '/public/assets/img/logo.png',
    true,
    132,
    38,
    'image/png'
);
$companyLogoHtml = $companyLogoPayload !== null
    ? '<img class="company-logo" src="' . ticketPdfEscape($companyLogoPayload['src']) . '"'
        . ' width="' . (int)$companyLogoPayload['width'] . '"'
        . ' height="' . (int)$companyLogoPayload['height'] . '"'
        . ' alt="Pronet System">'
    : '<strong class="company-name-fallback">PRONET SYSTEM S.A.C.</strong>';
$filename = sprintf(
    'ticket_%d_%s.pdf',
    (int)$ticket['id'],
    $isExecutive ? 'ejecutivo' : 'completo'
);

// =========================================================
// Fragmentos HTML
// =========================================================
$publicMessagesHtml = '';
if (!$includePublic) {
    $publicMessagesHtml = '<div class="empty-state">La conversación pública fue excluida de esta exportación.</div>';
} elseif (empty($selectedMessages)) {
    $publicMessagesHtml = '<div class="empty-state">No hay mensajes públicos registrados.</div>';
} elseif ($isExecutive) {
    // Mantiene el contexto inicial y prioriza mensajes que contienen evidencias.
    $detailedIds = [];

    if (!empty($selectedMessages)) {
        $detailedIds[(int)$selectedMessages[0]['id']] = true;
    }

    foreach ($selectedMessages as $message) {
        $messageId = (int)($message['id'] ?? 0);
        $attachments = $messageAttachments[$messageId] ?? [];

        if (!empty($attachments) && count($detailedIds) < 3) {
            $detailedIds[$messageId] = true;
        }
    }

    if (!empty($selectedMessages) && count($detailedIds) < 3) {
        $lastMessage = end($selectedMessages);
        $detailedIds[(int)($lastMessage['id'] ?? 0)] = true;
        reset($selectedMessages);
    }

    foreach ($selectedMessages as $message) {
        if (count($detailedIds) >= 3) {
            break;
        }
        $detailedIds[(int)($message['id'] ?? 0)] = true;
    }

    $detailedMessages = array_values(array_filter(
        $selectedMessages,
        static fn(array $message): bool => isset($detailedIds[(int)($message['id'] ?? 0)])
    ));
    $compactMessages = array_values(array_filter(
        $selectedMessages,
        static fn(array $message): bool => !isset($detailedIds[(int)($message['id'] ?? 0)])
    ));

    foreach ($detailedMessages as $message) {
        $attachments = $messageAttachments[(int)$message['id']] ?? [];
        $publicMessagesHtml .= ticketPdfMessageCard(
            $message,
            $attachments,
            false,
            $includeImages,
            $includeDocuments,
            $imageLimit,
            false,
            true
        );
    }

    if (!empty($compactMessages)) {
        $publicMessagesHtml .= '<div class="compact-summary"><strong>Contenido resumido</strong>'
            . '<span>' . count($compactMessages)
            . ' mensajes adicionales se incluyen en formato compacto, sin repetir imágenes ni documentos.</span></div>';
    }
} else {
<<<<<<< HEAD
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
=======
    foreach ($selectedMessages as $message) {
        $attachments = $messageAttachments[(int)$message['id']] ?? [];
        $publicMessagesHtml .= ticketPdfMessageCard(
            $message,
            $attachments,
            false,
            true,
            true,
            0,
            true
        );
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
    }
}

$internalMessagesHtml = '';
if (!$includeInternal) {
    $internalMessagesHtml = '<div class="empty-state">La conversación interna fue excluida de esta exportación.</div>';
} elseif (empty($selectedInternalMessages)) {
    $internalMessagesHtml = '<div class="empty-state">No hay mensajes internos registrados.</div>';
} else {
<<<<<<< HEAD
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
=======
    $internalCards = [];

    foreach ($selectedInternalMessages as $message) {
        $attachments = $internalMessageAttachments[(int)$message['id']] ?? [];
        $internalCards[] = ticketPdfMessageCard(
            $message,
            $attachments,
            true,
            false,
            false,
            $imageLimit,
            false
        );
    }

    $internalMessagesHtml = '<table class="internal-grid"><tbody>';
    foreach (array_chunk($internalCards, 2) as $row) {
        $internalMessagesHtml .= '<tr><td>' . ($row[0] ?? '') . '</td><td>' . ($row[1] ?? '') . '</td></tr>';
    }
    $internalMessagesHtml .= '</tbody></table>';
}

$activitiesHtml = '';
if (!$includeActivity) {
    $activitiesHtml = '<div class="empty-state">La actividad del ticket fue excluida de esta exportación.</div>';
} elseif (empty($activityPresentation['explicit'])) {
    $activitiesHtml = '<div class="empty-state">No hay eventos registrados.</div>';
} else {
    foreach ($activityPresentation['explicit'] as $activity) {
        $activitiesHtml .= ticketPdfTimelineItem($activity);
    }

    if (!empty($activityPresentation['grouped'])) {
        $grouped = $activityPresentation['grouped'];
        $firstGrouped = reset($grouped);
        $lastGrouped = end($grouped);
        $activitiesHtml .= '<div class="grouped-events"><strong>Eventos agrupados</strong><span>'
            . count($grouped) . ' actualizaciones menores entre '
            . ticketPdfEscape(date('H:i', strtotime((string)($firstGrouped['created_at'] ?? 'now'))))
            . ' y '
            . ticketPdfEscape(date('H:i', strtotime((string)($lastGrouped['created_at'] ?? 'now'))))
            . '.</span></div>';
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
    }
}

$documentsHtml = '';
if (!$includeDocuments) {
    $documentsHtml = '<div class="empty-state">El listado de documentos fue excluido de esta exportación.</div>';
} elseif (empty($documents)) {
    $documentsHtml = '<div class="empty-state">No hay documentos adjuntos.</div>';
} else {
    $documentsHtml = '<div class="document-list">';

    foreach ($documents as $document) {
        $extension = strtoupper(pathinfo((string)($document['original_name'] ?? ''), PATHINFO_EXTENSION));
        $documentsHtml .= '<div class="document-row">'
            . '<span class="document-type">' . ticketPdfEscape($extension ?: 'FILE') . '</span>'
            . '<span class="document-name">' . ticketPdfEscape($document['original_name'] ?? 'Archivo') . '</span>'
            . '<span class="document-owner">' . ticketPdfEscape($document['uploader_name'] ?? 'Usuario') . '</span>'
            . '<span class="document-size">' . ticketPdfEscape(ticketFormatBytes((int)($document['file_size'] ?? 0))) . '</span>'
            . '</div>';
    }

    $documentsHtml .= '</div>';
}

$feedbackHtml = '';
if (($ticket['status'] ?? '') !== 'CERRADO') {
    $feedbackHtml = '<div class="empty-state">La evaluación estará disponible cuando el ticket haya sido cerrado.</div>';
} elseif (empty($feedback)) {
    $feedbackHtml = '<div class="empty-state">El ticket está cerrado, pero el cliente todavía no registró una evaluación.</div>';
} else {
    $resolvedLabel = strtoupper((string)($feedback['resolved'] ?? '')) === 'SI' ? 'Sí' : 'No';
    $feedbackHtml = '<div class="feedback-grid">'
        . '<div><span>Calificación</span><strong>' . (int)($feedback['rating'] ?? 0) . ' de 5</strong></div>'
        . '<div><span>¿Se resolvió?</span><strong>' . ticketPdfEscape($resolvedLabel) . '</strong></div>'
        . '<div class="feedback-comment"><span>Comentario</span><strong>'
        . ticketPdfEscape($feedback['comment'] ?: 'Sin comentario.')
        . '</strong></div></div>';
}

$clientTicketsHtml = '';
if (!empty($clientTickets)) {
    $clientTicketsHtml = '<table class="data-table"><thead><tr>'
        . '<th>Ticket</th><th>Asunto</th><th>Estado</th><th>Prioridad</th><th>Fecha</th>'
        . '</tr></thead><tbody>';

    foreach ($clientTickets as $clientTicket) {
        $clientTicketsHtml .= '<tr>'
            . '<td>#' . (int)$clientTicket['id'] . '</td>'
            . '<td>' . ticketPdfEscape($clientTicket['subject'] ?? '') . '</td>'
            . '<td>' . ticketPdfEscape(ticketPdfStatusLabel($clientTicket['status'] ?? '')) . '</td>'
            . '<td>' . ticketPdfEscape($clientTicket['priority'] ?? '') . '</td>'
            . '<td>' . ticketPdfEscape(ticketPdfDateOnly($clientTicket['created_at'] ?? null)) . '</td>'
            . '</tr>';
    }

    $clientTicketsHtml .= '</tbody></table>';
}

$levelHistoryHtml = '';
if (!empty($levelHistory)) {
    $levelHistoryHtml = '<table class="data-table"><thead><tr>'
        . '<th>Nivel</th><th>Técnico</th><th>Asignación</th><th>Primera respuesta</th><th>Resultado</th>'
        . '</tr></thead><tbody>';

    foreach ($levelHistory as $row) {
        $levelHistoryHtml .= '<tr>'
            . '<td>Nivel ' . (int)($row['support_level'] ?? 0) . '</td>'
            . '<td>' . ticketPdfEscape($row['technician_name'] ?? 'Sin asignar') . '</td>'
            . '<td>' . ticketPdfEscape(ticketPdfDate($row['assigned_at'] ?? null)) . '</td>'
            . '<td>' . ticketPdfEscape(ticketPdfDate($row['first_response_at'] ?? null, 'Pendiente')) . '</td>'
            . '<td>' . ticketPdfEscape(ticketPdfStatusLabel($row['result'] ?? 'Pendiente')) . '</td>'
            . '</tr>';
    }

    $levelHistoryHtml .= '</tbody></table>';
}

$publicScope = $includePublic
    ? ($isExecutive
        ? 'Se muestran los últimos ' . count($selectedMessages) . ' de ' . count($messages) . ' mensajes.'
        : 'Se incluyen los ' . count($messages) . ' mensajes registrados.')
    : 'Conversación pública excluida.';
$activityScope = $includeActivity
    ? ($isExecutive
        ? 'Actividad: últimos ' . min($activityLimit, count($activities)) . ' de ' . count($activities) . ' eventos.'
        : 'Actividad: ' . count($activities) . ' eventos completos.')
    : 'Actividad excluida.';
$internalScope = $includeInternal
    ? ($isExecutive
        ? 'Conversación interna: últimos ' . count($selectedInternalMessages) . ' de ' . count($internalMessages) . ' mensajes.'
        : 'Conversación interna: ' . count($internalMessages) . ' mensajes completos.')
    : 'Conversación interna excluida.';

$slaObjectiveHours = max(0.01, (float)($ticket['sla_hours'] ?? 0));
$slaUsedHours = max(0.0, $slaObjectiveHours - max(0.0, $slaRemaining));
$slaProgressPercent = (int)max(4, min(100, round(($slaUsedHours / $slaObjectiveHours) * 100)));

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
<<<<<<< HEAD
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

=======
    @page { margin: 13px 15px 27px; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: DejaVu Sans, sans-serif;
        color: #182238;
        font-size: 9px;
        line-height: 1.32;
        background: #ffffff;
    }

    .page-break { page-break-before: always; }
    .keep-short { page-break-inside: avoid; }

    .page-banner {
        height: 58px;
        margin: 0 0 18px;
        padding: 11px 18px 9px;
        border-radius: 8px;
        background: #0b543f;
        color: #ffffff;
        overflow: hidden;
    }
    .page-banner-left,
    .page-banner-right {
        display: inline-block;
        vertical-align: top;
    }
    .page-banner-left { width: 70%; }
    .page-banner-right { width: 28%; padding-top: 3px; text-align: right; }
    .page-banner h1 {
        margin: 0;
        color: #ffffff;
        font-size: 19px;
        line-height: 1.05;
        font-weight: 900;
        letter-spacing: .3px;
        text-transform: uppercase;
    }
    .page-banner p {
        margin: 6px 0 0;
        color: #dbece5;
        font-size: 8px;
    }
    .company-logo {
        display: inline-block;
        margin: -2px 0 0 auto;
        vertical-align: top;
    }
    .company-name-fallback {
        display: block;
        font-size: 9px;
        letter-spacing: .3px;
    }
    .page-banner-right span {
        display: block;
        margin-top: 7px;
        color: #dbece5;
        font-size: 7px;
    }

    .ticket-card {
        margin-bottom: 17px;
        padding: 12px 15px;
        border: 1px solid #dfe5e9;
        border-radius: 8px;
        background: #ffffff;
    }
    .ticket-main,
    .ticket-owner {
        display: inline-block;
        vertical-align: top;
    }
    .ticket-main { width: 73%; }
    .ticket-owner { width: 25%; padding-top: 2px; text-align: right; }
    .ticket-code {
        display: block;
        color: #f27c22;
        font-size: 8px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .ticket-subject {
        margin: 2px 0 4px;
        color: #1e283c;
        font-size: 17px;
        line-height: 1.15;
        font-weight: 900;
    }
    .ticket-description {
        margin: 0;
        color: #6c7787;
        font-size: 8px;
    }
    .ticket-owner .label {
        display: block;
        color: #7e8997;
        font-size: 7px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .ticket-owner strong {
        display: block;
        margin-top: 5px;
        color: #222c41;
        font-size: 10px;
    }
    .ticket-owner small {
        display: block;
        margin-top: 5px;
        color: #7d8795;
        font-size: 7px;
    }
    .badges { margin-top: 7px; }
    .badge {
        display: inline-block;
        min-width: 82px;
        margin-right: 5px;
        padding: 5px 11px;
        border-radius: 13px;
        text-align: center;
        font-size: 8px;
        font-weight: 900;
    }
    .badge-status { background: #eaf5ef; color: #176349; }
    .badge-priority { background: #fff1e3; color: #d77a00; }
    .badge-success { background: #e8f5ef; color: #176349; }
    .badge-warning { background: #fff5d9; color: #9a6d00; }
    .badge-danger { background: #fde9e9; color: #a62e2e; }

    .section-block { margin: 0 0 16px; }
    .section-heading {
        margin: 0 0 7px;
        page-break-after: avoid;
    }
    .section-number {
        display: inline-block;
        width: 25px;
        height: 25px;
        padding-top: 6px;
        border-radius: 3px;
        background: #0c6048;
        color: #ffffff;
        text-align: center;
        vertical-align: middle;
        font-size: 8px;
        font-weight: 900;
    }
    .section-copy {
        display: inline-block;
        margin-left: 7px;
        vertical-align: middle;
    }
    .section-copy strong {
        display: block;
        color: #1e283c;
        font-size: 13px;
        line-height: 1.05;
    }
    .section-copy span {
        display: block;
        margin-top: 2px;
        color: #7a8594;
        font-size: 7px;
    }

    .metric-grid {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px 0;
        margin-left: -8px;
        table-layout: fixed;
    }
    .metric-grid td {
        height: 92px;
        padding: 10px 11px;
        border: 1px solid #dfe5e9;
        border-top: 4px solid #367cb7;
        border-radius: 6px;
        background: #ffffff;
        vertical-align: top;
    }
    .metric-grid td.metric-ttr { border-top-color: #e09d08; }
    .metric-grid td.metric-state { border-top-color: #26815f; }
    .metric-grid td.metric-sla {
        border: 0;
        background: #0b543f;
        color: #ffffff;
    }
    .metric-grid span {
        display: block;
        color: #748091;
        font-size: 7px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .metric-grid strong {
        display: block;
        margin-top: 13px;
        color: #2c78b7;
        font-size: 16px;
        line-height: 1.05;
    }
    .metric-grid .metric-ttr strong { color: #d99a00; }
    .metric-grid .metric-state strong { color: #25805d; }
    .metric-grid small {
        display: block;
        margin-top: 13px;
        color: #87919e;
        font-size: 7px;
    }
    .metric-grid .metric-sla span,
    .metric-grid .metric-sla strong,
    .metric-grid .metric-sla small { color: #ffffff; }
    .metric-grid .metric-sla strong {
        margin-top: 8px;
        font-size: 13px;
    }
    .metric-grid .metric-sla small {
        margin-top: 7px;
        color: #d3e7df;
    }
    .sla-remaining {
        margin-top: 8px;
        color: #9ee0bc;
        font-size: 8px;
        font-weight: 900;
    }
    .sla-progress {
        height: 7px;
        margin-top: 5px;
        border-radius: 4px;
        background: #2b6d5a;
        overflow: hidden;
    }
    .sla-progress div {
        height: 7px;
        border-radius: 4px;
        background: #67c795;
    }

    .info-grid {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px 7px;
        margin: -7px 0 0 -8px;
        table-layout: fixed;
    }
    .info-grid td {
        padding: 9px 11px;
        border: 1px solid #dfe5e9;
        border-radius: 6px;
        background: #f9fafb;
        vertical-align: top;
    }
    .info-grid td.highlight { background: #edf8f3; }
    .info-grid span {
        display: block;
        color: #7c8796;
        font-size: 7px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .info-grid strong {
        display: block;
        margin-top: 5px;
        color: #253047;
        font-size: 9px;
    }
    .info-grid small {
        display: block;
        margin-top: 4px;
        color: #8a94a0;
        font-size: 7px;
    }

    .client-card {
        padding: 10px 12px;
        border: 1px solid #dfe5e9;
        border-radius: 6px;
        background: #f9fafb;
    }
    .client-avatar,
    .client-primary,
    .client-contact,
    .client-contract {
        display: inline-block;
        vertical-align: middle;
    }
    .client-avatar {
        width: 38px;
        height: 38px;
        padding: 0;
        border-radius: 50%;
        background: #ff8500;
        color: #ffffff;
        text-align: center;
        line-height: 38px;
        font-size: 9px;
        font-weight: 900;
        overflow: hidden;
    }
    .client-primary { width: 30%; padding-left: 11px; }
    .client-contact { width: 31%; }
    .client-contract { width: 24%; text-align: left; }
    .client-card strong {
        display: block;
        color: #273147;
        font-size: 9px;
    }
    .client-primary span,
    .client-contact span,
    .client-contract span {
        display: block;
        margin-top: 5px;
        color: #7b8593;
        font-size: 7px;
    }

    .scope-box {
        padding: 11px 14px;
        border: 1px solid #f0d3b2;
        border-radius: 6px;
        background: #fff1e3;
    }
    .scope-box.full {
        border-color: #cfe2d9;
        background: #eff7f3;
    }
    .scope-box strong {
        display: block;
        color: #ee7b23;
        font-size: 8px;
        text-transform: uppercase;
    }
    .scope-box.full strong { color: #176349; }
    .scope-box b {
        display: block;
        margin-top: 8px;
        color: #273147;
        font-size: 9px;
    }
    .scope-box span {
        display: block;
        margin-top: 5px;
        color: #76808e;
        font-size: 7px;
    }

    .notice {
        margin: 0 0 15px;
        padding: 11px 15px;
        border: 1px solid #cbddea;
        border-radius: 5px;
        background: #eaf4fc;
        color: #3d78a8;
        font-size: 8px;
        font-weight: 900;
    }
    .notice-yellow {
        border-color: #ecd99d;
        background: #fff5d9;
        color: #b78400;
    }

    .message-card {
        margin: 0 0 10px;
        border: 1px solid #dde4e8;
        border-radius: 6px;
        background: #ffffff;
        page-break-inside: auto;
    }
    .message-meta {
        min-height: 33px;
        padding: 8px 11px;
        page-break-after: avoid;
    }
    .message-avatar {
        display: inline-block;
        width: 27px;
        height: 27px;
        padding: 0;
        border-radius: 50%;
        background: #0f6048;
        color: #ffffff;
        text-align: center;
        vertical-align: middle;
        line-height: 27px;
        font-size: 7px;
        font-weight: 900;
        overflow: hidden;
    }
    .avatar-photo {
        display: block;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 50%;
    }
    .avatar-client { background: #ff8500; }
    .avatar-tech { background: #367cb7; }
    .avatar-admin { background: #0f6048; }
    .avatar-system { background: #6f7b8c; }
    .message-name {
        display: inline-block;
        margin-left: 8px;
        color: #222c41;
        vertical-align: middle;
        font-size: 9px;
        font-weight: 900;
    }
    .message-role {
        display: inline-block;
        min-width: 80px;
        margin-left: 16px;
        padding: 4px 11px;
        border-radius: 12px;
        background: #eaf5ef;
        color: #176349;
        text-align: center;
        vertical-align: middle;
        font-size: 7px;
        font-weight: 900;
    }
    .message-date {
        float: right;
        padding-top: 8px;
        color: #7e8997;
        font-size: 7px;
    }
    .message-content {
        padding: 0 11px 10px 55px;
    }
    .pdf-rich-message {
        color: #303b4f;
        font-size: 8px;
        line-height: 1.35;
        word-wrap: break-word;
    }
    .pdf-rich-message p,
    .pdf-rich-message div { margin: 0 0 5px; }
    .pdf-rich-message h1 { margin: 5px 0; font-size: 13px; }
    .pdf-rich-message h2 { margin: 5px 0; font-size: 11px; }
    .pdf-rich-message h3 { margin: 5px 0; font-size: 10px; }
    .pdf-rich-message ul,
    .pdf-rich-message ol { margin: 4px 0 5px 16px; padding: 0; }
    .pdf-rich-message blockquote {
        margin: 5px 0;
        padding: 5px 7px;
        border-left: 3px solid #f07b26;
        background: #fff8f2;
    }
    .pdf-rich-message pre {
        white-space: pre-wrap;
        padding: 6px;
        border-radius: 5px;
        background: #1c2635;
        color: #ffffff;
    }
    .pdf-rich-message img { display: none; }

    .pdf-image-grid {
        width: 100%;
        margin-top: 8px;
        border-collapse: separate;
        border-spacing: 7px 0;
        table-layout: fixed;
    }
    .pdf-image-grid td {
        width: 50%;
        padding: 0;
        vertical-align: top;
    }
    .pdf-image-item {
        margin-bottom: 6px;
        padding: 7px;
        border: 1px solid #dce4e8;
        border-radius: 5px;
        background: #f9fafb;
        text-align: center;
        page-break-inside: avoid;
    }
    .pdf-image-item img { display: block; margin: 0 auto; }
    .pdf-image-caption {
        margin-top: 4px;
        color: #778291;
        font-size: 7px;
        word-wrap: break-word;
    }
    .pdf-more-files {
        margin-top: 5px;
        color: #647184;
        font-size: 7px;
    }

    .pdf-attachments { margin-top: 6px; }
    .pdf-attachment,
    .document-row {
        min-height: 24px;
        margin-top: 4px;
        padding: 6px 9px;
        border: 1px solid #dfe5e9;
        border-radius: 4px;
        background: #f7f9fa;
        page-break-inside: avoid;
    }
    .pdf-attachment-type,
    .document-type {
        display: inline-block;
        width: 55px;
        color: #ee7b23;
        font-size: 7px;
        font-weight: 900;
        vertical-align: middle;
    }
    .pdf-attachment-name,
    .document-name {
        display: inline-block;
        width: 55%;
        color: #263147;
        font-size: 8px;
        font-weight: 700;
        vertical-align: middle;
        word-wrap: break-word;
    }
    .pdf-attachment-size,
    .document-size {
        display: inline-block;
        width: 15%;
        color: #778291;
        text-align: right;
        font-size: 7px;
        vertical-align: middle;
    }
    .document-owner {
        display: inline-block;
        width: 18%;
        color: #778291;
        font-size: 7px;
        vertical-align: middle;
    }

    .compact-summary {
        margin-top: 7px;
        padding: 10px 14px;
        border: 1px solid #dfe5e9;
        border-radius: 5px;
        background: #f7f9fa;
    }
    .compact-summary strong {
        display: block;
        color: #687487;
        font-size: 7px;
        text-transform: uppercase;
    }
    .compact-summary span {
        display: block;
        margin-top: 8px;
        color: #2d384d;
        font-size: 8px;
        font-weight: 800;
    }

    .timeline {
        position: relative;
        margin: 0 0 11px;
        padding-top: 2px;
    }
    .timeline-item {
        min-height: 43px;
        position: relative;
        margin: 0;
        padding: 0 0 8px;
        page-break-inside: avoid;
    }
    .timeline-time {
        display: inline-block;
        width: 45px;
        padding-top: 5px;
        color: #687487;
        vertical-align: top;
        font-size: 8px;
        font-weight: 900;
    }
    .timeline-dot {
        display: inline-block;
        width: 17px;
        height: 17px;
        margin-top: 1px;
        border-radius: 50%;
        background: #0f6048;
        vertical-align: top;
    }
    .dot-orange { background: #ff8500; }
    .dot-blue { background: #367cb7; }
    .dot-green { background: #26815f; }
    .dot-red { background: #b53b3b; }
    .dot-dark { background: #0f6048; }
    .timeline-copy {
        display: inline-block;
        width: 82%;
        margin-left: 8px;
        padding-bottom: 7px;
        border-left: 1px solid #d8e0e5;
        vertical-align: top;
    }
    .timeline-copy strong,
    .timeline-copy small,
    .timeline-copy p {
        display: block;
        margin-left: 13px;
    }
    .timeline-copy strong {
        color: #243047;
        font-size: 9px;
    }
    .timeline-copy small {
        margin-top: 3px;
        color: #7b8694;
        font-size: 7px;
    }
    .timeline-copy p {
        margin-top: 4px;
        margin-bottom: 0;
        color: #536174;
        font-size: 8px;
    }
    .grouped-events {
        margin: 2px 0 12px;
        padding: 9px 14px;
        border: 1px solid #dfe5e9;
        border-radius: 5px;
        background: #f7f9fa;
    }
    .grouped-events strong {
        display: block;
        color: #687487;
        font-size: 7px;
        text-transform: uppercase;
    }
    .grouped-events span {
        display: block;
        margin-top: 6px;
        color: #273147;
        font-size: 8px;
        font-weight: 800;
    }

    .internal-grid {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px 0;
        margin-left: -8px;
        table-layout: fixed;
    }
    .internal-grid td { width: 50%; vertical-align: top; }
    .internal-card {
        min-height: 80px;
        padding: 10px 12px;
        border: 1px solid #dfe5e9;
        border-radius: 5px;
        background: #ffffff;
        page-break-inside: avoid;
    }
    .internal-author {
        display: inline-block;
        margin-left: 8px;
        vertical-align: middle;
    }
    .internal-author strong {
        display: block;
        color: #273147;
        font-size: 8px;
    }
    .internal-author small {
        display: block;
        margin-top: 3px;
        color: #7b8694;
        font-size: 7px;
    }
    .internal-card p {
        margin: 9px 0 0;
        color: #3e495c;
        font-size: 8px;
    }

    .feedback-grid {
        padding: 10px 12px;
        border: 1px solid #cfe2d9;
        border-radius: 6px;
        background: #eaf5ef;
        page-break-inside: avoid;
    }
    .feedback-grid div {
        display: inline-block;
        width: 18%;
        vertical-align: top;
    }
    .feedback-grid .feedback-comment { width: 57%; }
    .feedback-grid span {
        display: block;
        color: #718091;
        font-size: 7px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .feedback-grid strong {
        display: block;
        margin-top: 8px;
        color: #1d6048;
        font-size: 10px;
        word-wrap: break-word;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .data-table th {
        padding: 6px;
        background: #0f6048;
        color: #ffffff;
        text-align: left;
        font-size: 7px;
    }
    .data-table td {
        padding: 6px;
        border: 1px solid #dfe5e9;
        color: #4e5c70;
        font-size: 7px;
        vertical-align: top;
        word-wrap: break-word;
    }
    .data-table tr:nth-child(even) td { background: #f7f9fa; }

    .empty-state {
        padding: 10px 12px;
        border: 1px dashed #cad4dc;
        border-radius: 5px;
        background: #f8fafb;
        color: #6f7b8c;
        font-size: 8px;
    }

    .footer {
        position: fixed;
        left: 15px;
        right: 15px;
        bottom: 8px;
        padding-top: 5px;
        border-top: 1px solid #e0e5e8;
        color: #88929f;
        font-size: 6px;
    }
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
</style>
</head>
<body>
<div class="footer">Reporte de ticket · PRONET SYSTEM S.A.C.</div>

<div class="page-banner">
    <div class="page-banner-left">
        <h1><?= ticketPdfEscape(strtoupper($reportLabel)) ?> DEL TICKET #<?= (int)$ticket['id'] ?></h1>
        <p>Resumen operativo, SLA y datos principales.</p>
    </div>
    <div class="page-banner-right">
        <?= $companyLogoHtml ?>
        <span>Generado <?= ticketPdfEscape($generatedAt) ?></span>
    </div>
</div>

<div class="ticket-card keep-short">
    <div class="ticket-main">
        <span class="ticket-code">Ticket #<?= (int)$ticket['id'] ?></span>
        <h2 class="ticket-subject"><?= ticketPdfEscape($ticket['subject'] ?? 'Sin asunto') ?></h2>
        <p class="ticket-description"><?php
            $description = (string)($ticket['description'] ?? '');
            echo ticketPdfEscape($isExecutive ? ticketPdfExcerpt($description, 280) : ticketPdfExcerpt($description, 520));
        ?></p>
        <div class="badges">
            <span class="badge badge-status"><?= ticketPdfEscape(ticketPdfStatusLabel($ticket['status'] ?? '')) ?></span>
            <span class="badge badge-priority">Prioridad <?= ticketPdfEscape($ticket['priority'] ?? 'N/D') ?></span>
            <span class="badge badge-<?= ticketPdfEscape($slaTone) ?>"><?= ticketPdfEscape($slaStatus) ?></span>
        </div>
    </div>
    <div class="ticket-owner">
        <span class="label">Responsable</span>
        <strong><?= ticketPdfEscape($ticket['assigned_name'] ?: 'Sin asignar') ?></strong>
        <small><?php
            if (!empty($ticket['assigned_name'])) {
                echo 'Soporte nivel ' . (int)($ticket['assigned_level'] ?? $ticket['support_level'] ?? 1);
            } else {
                echo 'Pendiente de asignación';
            }
        ?></small>
    </div>
</div>

<div class="section-block keep-short">
    <div class="section-heading">
        <span class="section-number">01</span><span class="section-copy"><strong>Resumen operativo</strong><span>Indicadores esenciales para entender el ticket.</span></span>
    </div>
    <table class="metric-grid"><tr>
        <td><span>TTA</span><strong><?= ticketPdfEscape($ttaLabel) ?></strong><small>Primera respuesta</small></td>
        <td class="metric-ttr"><span>TTR</span><strong><?= ticketPdfEscape($ttrLabel) ?></strong><small><?= ($ticket['status'] ?? '') === 'CERRADO' ? 'Tiempo de resolución' : 'Ticket abierto' ?></small></td>
        <td class="metric-state"><span>Estado</span><strong><?= ticketPdfEscape(ticketPdfStatusLabel($ticket['status'] ?? '')) ?></strong><small>Situación actual</small></td>
        <td class="metric-sla"><span>Control del SLA</span><strong><?= ticketPdfEscape($slaStatus) ?></strong><small><?= ticketPdfEscape($slaTimer['contract_label'] ?? 'Contrato 8/5') ?> · objetivo <?= ticketPdfEscape(formatSlaDuration((float)($ticket['sla_hours'] ?? 0))) ?></small><div class="sla-remaining"><?= ticketPdfEscape($slaTimeLabel) ?></div><div class="sla-progress"><div style="width:<?= (int)$slaProgressPercent ?>%;"></div></div></td>
    </tr></table>
</div>

<<<<<<< HEAD
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
=======
<div class="section-block keep-short">
    <div class="section-heading">
        <span class="section-number">02</span><span class="section-copy"><strong>Información del caso</strong><span>Responsables, fechas y clasificación.</span></span>
    </div>
    <table class="info-grid">
        <tr>
            <td><span>Cliente</span><strong><?= ticketPdfEscape($ticket['requester_name'] ?? 'No registrado') ?></strong><small>Solicitante</small></td>
            <td class="highlight"><span>Técnico</span><strong><?= ticketPdfEscape($ticket['assigned_name'] ?: 'Sin asignar') ?></strong><small>Soporte nivel <?= (int)($ticket['support_level'] ?? 1) ?></small></td>
            <td><span>Categoría</span><strong><?= ticketPdfEscape(ticketPdfStatusLabel($ticket['category'] ?? 'OTROS')) ?></strong><small>Clasificación</small></td>
        </tr>
        <tr>
            <td><span>Creación</span><strong><?= ticketPdfEscape(ticketPdfDate($ticket['created_at'] ?? null)) ?></strong><small>Registro inicial</small></td>
            <td><span>Actualización</span><strong><?= ticketPdfEscape(ticketPdfDate($ticket['updated_at'] ?? null)) ?></strong><small>Último movimiento</small></td>
            <td><span>Cierre</span><strong><?= ticketPdfEscape(ticketPdfDate($closedAt, 'No disponible')) ?></strong><small><?= $closedAt ? 'Ticket cerrado' : 'Ticket abierto' ?></small></td>
        </tr>
    </table>
</div>

<div class="section-block keep-short">
    <div class="section-heading">
        <span class="section-number">03</span><span class="section-copy"><strong>Cliente y contrato</strong><span>Contacto responsable y alcance del servicio.</span></span>
    </div>
    <div class="client-card">
        <div class="client-avatar"><?= ticketPdfAvatarContent(
            $ticket['requester_name'] ?? 'Cliente',
            $ticket['requester_profile_photo'] ?? null,
            38
        ) ?></div>
        <div class="client-primary"><strong><?= ticketPdfEscape($ticket['requester_name'] ?? 'Cliente') ?></strong><span><?= ticketPdfEscape(($ticket['requester_position'] ?: 'Solicitante') . ' · ' . $companyName) ?></span></div>
        <div class="client-contact"><strong><?= ticketPdfEscape($ticket['requester_email'] ?? 'Sin correo') ?></strong><span><?= ticketPdfEscape($ticket['requester_phone'] ?? 'Sin teléfono') ?></span></div>
        <div class="client-contract"><strong><?= ticketPdfEscape($slaTimer['contract_label'] ?? 'Contrato 8/5') ?></strong><span>Objetivo <?= ticketPdfEscape(formatSlaDuration((float)($ticket['sla_hours'] ?? 0))) ?></span><span><?= (int)($clientStats['total_tickets'] ?? 0) ?> tickets del cliente</span></div>
    </div>
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
</div>

<div class="section-block keep-short">
    <div class="section-heading">
        <span class="section-number">04</span><span class="section-copy"><strong>Alcance del reporte</strong><span>Resumen de la información incluida.</span></span>
    </div>
    <div class="scope-box <?= $isExecutive ? '' : 'full' ?>">
        <strong><?= ticketPdfEscape($reportLabel) ?></strong>
        <b><?= ticketPdfEscape($publicScope) ?></b>
        <span><?= ticketPdfEscape($activityScope) ?> · <?= ticketPdfEscape($internalScope) ?></span>
        <span><?= $includeImages ? $imageCount . ' imágenes disponibles.' : 'Imágenes excluidas.' ?> <?= $includeDocuments ? count($documents) . ' documentos listados sin incrustar su contenido.' : 'Documentos excluidos.' ?></span>
    </div>
</div>

<div class="page-break"></div>
<div class="page-banner">
    <div class="page-banner-left">
        <h1>Conversación y evidencias</h1>
        <p>Vista compacta de mensajes, imágenes y documentos.</p>
    </div>
    <div class="page-banner-right">
        <?= $companyLogoHtml ?>
        <span><?= $isExecutive ? 'Últimos ' . count($selectedMessages) . ' de ' . count($messages) : count($messages) . ' mensajes' ?></span>
    </div>
</div>

<div class="notice"><?= ticketPdfEscape($publicScope) ?><?= $isExecutive && count($messages) > count($selectedMessages) ? ' Los ' . (count($messages) - count($selectedMessages)) . ' anteriores permanecen disponibles en el sistema.' : '' ?></div>
<?= $publicMessagesHtml ?>

<div class="page-break"></div>
<div class="page-banner">
    <div class="page-banner-left">
        <h1>Trazabilidad y seguimiento</h1>
        <p>Actividad reciente, conversación interna y evaluación.</p>
    </div>
    <div class="page-banner-right">
        <?= $companyLogoHtml ?>
        <span><?= $isExecutive ? 'Resumen compacto' : 'Trazabilidad completa' ?></span>
    </div>
</div>

<div class="notice notice-yellow"><?= ticketPdfEscape($activityScope) ?><?= $isExecutive ? ' Los eventos críticos nunca se agrupan.' : '' ?></div>

<div class="timeline"><?= $activitiesHtml ?></div>

<div class="section-block">
    <div class="section-heading">
        <span class="section-number">05</span><span class="section-copy"><strong>Conversación interna</strong><span><?= $isExecutive ? 'Últimos mensajes del equipo técnico.' : 'Notas privadas completas del equipo técnico.' ?></span></span>
    </div>
    <?= $internalMessagesHtml ?>
</div>

<div class="section-block keep-short">
    <div class="section-heading">
        <span class="section-number">06</span><span class="section-copy"><strong>Documentos del ticket</strong><span>Listado compacto; los archivos no se incrustan dentro del PDF.</span></span>
    </div>
    <?= $documentsHtml ?>
</div>

<div class="section-block keep-short">
    <div class="section-heading">
        <span class="section-number">07</span><span class="section-copy"><strong>Evaluación del cliente</strong><span>Resultado posterior al cierre del ticket.</span></span>
    </div>
    <?= $feedbackHtml ?>
</div>

<?php if (!$isExecutive): ?>
    <?php if ($levelHistoryHtml !== ''): ?>
        <div class="section-block">
            <div class="section-heading">
                <span class="section-number">08</span><span class="section-copy"><strong>Historial de niveles</strong><span>Asignaciones y resultados por nivel de soporte.</span></span>
            </div>
            <?= $levelHistoryHtml ?>
        </div>
    <?php endif; ?>

    <?php if ($clientTicketsHtml !== ''): ?>
        <div class="section-block">
            <div class="section-heading">
                <span class="section-number"><?= $levelHistoryHtml !== '' ? '09' : '08' ?></span><span class="section-copy"><strong>Historial reciente del cliente</strong><span>Últimos tickets asociados al solicitante.</span></span>
            </div>
            <?= $clientTicketsHtml ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

$dompdf->getCanvas()->page_script(static function (
    int $pageNumber,
    int $pageCount,
    $canvas,
    $fontMetrics
): void {
    $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
    $fontSize = 6;
    $text = 'Página ' . $pageNumber . ' de ' . $pageCount;
    $textWidth = $fontMetrics->getTextWidth($text, $font, $fontSize);
    $canvas->text(
        579 - $textWidth,
        823,
        $text,
        $font,
        $fontSize,
        [0.53, 0.57, 0.62]
    );
});

$pdfBinary = $dompdf->output();

if ($pdfBinary === '' || substr($pdfBinary, 0, 5) !== '%PDF-') {
    throw new RuntimeException('Dompdf no produjo un archivo PDF válido.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (headers_sent($headerFile, $headerLine)) {
    throw new RuntimeException(
        'No se pueden enviar los encabezados del PDF porque ya hubo salida en '
        . $headerFile . ':' . $headerLine
    );
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

echo $pdfBinary;
exit;
