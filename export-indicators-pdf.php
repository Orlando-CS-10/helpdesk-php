<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

requireLogin();

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: home.php');
    exit;
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDurationFromSeconds($seconds): string
{
    if ($seconds === null || $seconds === '' || !is_numeric($seconds)) {
        return '00:00:00';
    }

    $seconds = max(0, (int)round((float)$seconds));
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
}

function fetchOne(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: [];
}

function fetchRows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function statusLabel(?string $status): string
{
    if ($status === null || $status === '') {
        return 'Sin estado';
    }

    return [
        'ABIERTO' => 'Abierto',
        'EN_PROCESO' => 'En proceso',
        'RESPONDIDO' => 'Respondido',
        'CERRADO' => 'Cerrado',
    ][$status] ?? ucfirst(strtolower(str_replace('_', ' ', $status)));
}

function buildBarRows(array $rows, string $labelKey, string $valueKey): string
{
    if (empty($rows)) {
        return '<div class="empty-box">Sin datos disponibles</div>';
    }

    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int)($row[$valueKey] ?? 0));
    }

    $max = max($max, 1);
    $html = '';

    foreach ($rows as $row) {
        $label = e($row[$labelKey] ?? 'Sin dato');
        $value = (int)($row[$valueKey] ?? 0);
        $width = max(3, round(($value / $max) * 100));

        $html .= '
            <div class="bar-row">
                <div class="bar-meta">
                    <span>' . $label . '</span>
                    <strong>' . $value . '</strong>
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: ' . $width . '%;"></div>
                </div>
            </div>
        ';
    }

    return $html;
}

function getLogoBase64(): string
{
    /*
     * Dompdf necesita la extensión PHP GD para renderizar imágenes PNG.
     * Si GD no está activa en XAMPP, el PDF no debe romperse: se usa texto de respaldo.
     * Cuando habilites GD, el logo PNG se mostrará normalmente.
     */
    $possibleLogoPaths = [
        __DIR__ . '/public/assets/img/pronet-logo.jpg',
        __DIR__ . '/public/assets/img/pronet-logo.jpeg',
        __DIR__ . '/public/assets/img/logo-pronet.jpg',
        __DIR__ . '/public/assets/img/logo-pronet.jpeg',
        __DIR__ . '/public/assets/img/logo.jpg',
        __DIR__ . '/public/assets/img/logo.jpeg',
        __DIR__ . '/public/assets/img/pronet-logo.png',
        __DIR__ . '/public/assets/img/logo-pronet.png',
        __DIR__ . '/public/assets/img/logo.png',
        __DIR__ . '/public/assets/img/pronet-logo.svg',
        __DIR__ . '/public/assets/img/logo-pronet.svg',
        __DIR__ . '/public/assets/img/logo.svg',
    ];

    foreach ($possibleLogoPaths as $logoPath) {
        if (!file_exists($logoPath)) {
            continue;
        }

        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));

        if ($extension === 'png' && !extension_loaded('gd')) {
            continue;
        }

        $mimeByExtension = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
        ];

        if (!isset($mimeByExtension[$extension])) {
            continue;
        }

        return 'data:' . $mimeByExtension[$extension] . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    return '';
}

function validateReportDate(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
        return false;
    }

    return $date !== false && $date->format('Y-m-d') === $value;
}

function formatReportDate(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date instanceof DateTimeImmutable
        ? $date->format('d/m/Y')
        : $value;
}

$periodMode = trim((string)($_GET['period_mode'] ?? 'all'));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$whereSql = '1 = 1';
$params = [];
$periodLabel = 'Todos los registros';
$filenamePeriod = 'todos_los_registros';

$hasDateRange = $periodMode === 'range' || $dateFrom !== '' || $dateTo !== '';

if ($hasDateRange) {
    if ($dateFrom === '' || $dateTo === '') {
        http_response_code(400);
        exit('Debes seleccionar una fecha inicial y una fecha final.');
    }

    if (!validateReportDate($dateFrom) || !validateReportDate($dateTo)) {
        http_response_code(400);
        exit('El rango de fechas no tiene un formato válido.');
    }

    if ($dateFrom > $dateTo) {
        http_response_code(400);
        exit('La fecha inicial no puede ser posterior a la fecha final.');
    }

    $whereSql .= ' AND t.created_at BETWEEN :date_from AND :date_to';
    $params['date_from'] = $dateFrom . ' 00:00:00';
    $params['date_to'] = $dateTo . ' 23:59:59';

    $periodLabel = formatReportDate($dateFrom) . ' al ' . formatReportDate($dateTo);
    $filenamePeriod = str_replace('-', '', $dateFrom) . '_al_' . str_replace('-', '', $dateTo);
} else {
    $periodMode = 'all';
    $dateFrom = '';
    $dateTo = '';
}

$scope = trim($_GET['scope'] ?? 'all');
$technicianId = filter_input(INPUT_GET, 'technician_id', FILTER_VALIDATE_INT) ?: 0;
$selectedTechnician = null;
$scopeLabel = 'Todos los técnicos';

if ($scope === 'technician') {
    if ($technicianId <= 0) {
        http_response_code(400);
        exit('Debes seleccionar un técnico para exportar el reporte.');
    }

    $selectedTechnician = fetchOne($pdo, "
        SELECT
            id,
            name,
            COALESCE(tech_level, 1) AS tech_level
        FROM users
        WHERE id = :technician_id
          AND role = 'TECH'
          AND status = 1
        LIMIT 1
    ", ['technician_id' => $technicianId]);

    if (empty($selectedTechnician)) {
        http_response_code(404);
        exit('No se encontró el técnico seleccionado o no se encuentra activo.');
    }

    $whereSql .= ' AND t.assigned_to = :technician_id';
    $params['technician_id'] = $technicianId;
    $scopeLabel = 'Técnico: ' . ($selectedTechnician['name'] ?? 'Sin nombre') . ' - Nivel ' . (int)($selectedTechnician['tech_level'] ?? 1);
} else {
    $scope = 'all';
}

$technicianSummaryWhereSql = "u.role = 'TECH' AND u.status = 1";

if ($selectedTechnician !== null) {
    $technicianSummaryWhereSql .= ' AND u.id = :technician_id';
}

$aggregate = fetchOne($pdo, "
    SELECT
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN t.status = 'ABIERTO' THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN t.status = 'EN_PROCESO' THEN 1 ELSE 0 END) AS in_progress_tickets,
        SUM(CASE WHEN t.status = 'RESPONDIDO' THEN 1 ELSE 0 END) AS answered_tickets,
        SUM(CASE WHEN t.status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_tickets,
        SUM(CASE WHEN t.status <> 'CERRADO' THEN 1 ELSE 0 END) AS active_tickets,
        SUM(CASE WHEN COALESCE(t.support_level, 1) > 1 THEN 1 ELSE 0 END) AS escalated_tickets,
        SUM(
            CASE
                WHEN t.status = 'CERRADO'
                 AND TIMESTAMPDIFF(SECOND, t.created_at, COALESCE(t.closed_at, t.updated_at)) <= COALESCE(t.sla_hours, 0) * 3600
                THEN 1 ELSE 0
            END
        ) AS closed_within_sla,
        SUM(
            CASE
                WHEN t.status = 'CERRADO'
                 AND TIMESTAMPDIFF(SECOND, t.created_at, COALESCE(t.closed_at, t.updated_at)) > COALESCE(t.sla_hours, 0) * 3600
                THEN 1 ELSE 0
            END
        ) AS closed_out_sla,
        AVG(
            CASE
                WHEN COALESCE(t.level_first_response_at, t.first_response_at) IS NOT NULL
                THEN TIMESTAMPDIFF(SECOND, t.created_at, COALESCE(t.level_first_response_at, t.first_response_at))
                ELSE NULL
            END
        ) AS avg_tta_seconds,
        AVG(
            CASE
                WHEN t.status = 'CERRADO'
                THEN TIMESTAMPDIFF(SECOND, t.created_at, COALESCE(t.closed_at, t.updated_at))
                ELSE NULL
            END
        ) AS avg_ttr_seconds
    FROM tickets t
    WHERE {$whereSql}
", $params);

$totalTickets = (int)($aggregate['total_tickets'] ?? 0);
$openTickets = (int)($aggregate['open_tickets'] ?? 0);
$inProgressTickets = (int)($aggregate['in_progress_tickets'] ?? 0);
$answeredTickets = (int)($aggregate['answered_tickets'] ?? 0);
$closedTickets = (int)($aggregate['closed_tickets'] ?? 0);
$activeTickets = (int)($aggregate['active_tickets'] ?? 0);
$escalatedTickets = (int)($aggregate['escalated_tickets'] ?? 0);
$closedWithinSla = (int)($aggregate['closed_within_sla'] ?? 0);
$closedOutSla = (int)($aggregate['closed_out_sla'] ?? 0);
$closedTotalForSla = $closedWithinSla + $closedOutSla;
$slaPercent = $closedTotalForSla > 0 ? round(($closedWithinSla / $closedTotalForSla) * 100, 2) : 0;
$avgTTA = formatDurationFromSeconds($aggregate['avg_tta_seconds'] ?? null);
$avgTTR = formatDurationFromSeconds($aggregate['avg_ttr_seconds'] ?? null);

$ticketsByStatus = fetchRows($pdo, "
    SELECT
        t.status,
        CASE t.status
            WHEN 'ABIERTO' THEN 'Abierto'
            WHEN 'EN_PROCESO' THEN 'En proceso'
            WHEN 'RESPONDIDO' THEN 'Respondido'
            WHEN 'CERRADO' THEN 'Cerrado'
            ELSE COALESCE(t.status, 'Sin estado')
        END AS status_label,
        COUNT(*) AS total
    FROM tickets t
    WHERE {$whereSql}
    GROUP BY t.status
    ORDER BY total DESC
", $params);

$ticketsByPriority = fetchRows($pdo, "
    SELECT COALESCE(NULLIF(t.priority, ''), 'Sin prioridad') AS priority, COUNT(*) AS total
    FROM tickets t
    WHERE {$whereSql}
    GROUP BY COALESCE(NULLIF(t.priority, ''), 'Sin prioridad')
    ORDER BY total DESC
", $params);

$ticketsByCategory = fetchRows($pdo, "
    SELECT COALESCE(NULLIF(t.category, ''), 'Sin categoría') AS category, COUNT(*) AS total
    FROM tickets t
    WHERE {$whereSql}
    GROUP BY COALESCE(NULLIF(t.category, ''), 'Sin categoría')
    ORDER BY total DESC
    LIMIT 8
", $params);

$ticketsByLevel = fetchRows($pdo, "
    SELECT COALESCE(t.support_level, 1) AS support_level, COUNT(*) AS total
    FROM tickets t
    WHERE {$whereSql}
    GROUP BY COALESCE(t.support_level, 1)
    ORDER BY support_level ASC
", $params);

$ticketsByTechnician = fetchRows($pdo, "
    SELECT COALESCE(u.name, 'Sin asignar') AS technician_name, COUNT(*) AS total
    FROM tickets t
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE {$whereSql}
    GROUP BY COALESCE(u.name, 'Sin asignar')
    ORDER BY total DESC
    LIMIT 8
", $params);

$dateJoinSql = '';
if ($dateFrom !== '') {
    $dateJoinSql .= ' AND t.created_at >= :date_from';
}
if ($dateTo !== '') {
    $dateJoinSql .= ' AND t.created_at <= :date_to';
}

$technicianSummary = fetchRows($pdo, "
    SELECT
        u.name,
        COALESCE(u.tech_level, 1) AS tech_level,
        SUM(CASE WHEN t.id IS NOT NULL AND t.status <> 'CERRADO' THEN 1 ELSE 0 END) AS active_tickets,
        SUM(CASE WHEN t.id IS NOT NULL AND t.status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_tickets,
        SUM(CASE WHEN t.id IS NOT NULL AND COALESCE(t.support_level, 1) > 1 THEN 1 ELSE 0 END) AS escalated_tickets
    FROM users u
    LEFT JOIN tickets t ON t.assigned_to = u.id {$dateJoinSql}
    WHERE {$technicianSummaryWhereSql}
    GROUP BY u.id, u.name, u.tech_level
    ORDER BY active_tickets DESC, closed_tickets DESC, u.name ASC
    LIMIT 10
", $params);

$recentTickets = fetchRows($pdo, "
    SELECT
        t.id,
        t.subject,
        t.status,
        t.priority,
        t.category,
        t.created_at,
        COALESCE(u.name, 'Sin asignar') AS technician_name
    FROM tickets t
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE {$whereSql}
    ORDER BY t.created_at DESC
    LIMIT 8
", $params);

$generatedAt = date('d/m/Y H:i');
$logoBase64 = getLogoBase64();

function formatDurationReadable($seconds): string
{
    if ($seconds === null || $seconds === '' || !is_numeric($seconds)) {
        return 'Sin datos';
    }

    $seconds = max(0, (int)round((float)$seconds));

    if ($seconds < 60) {
        return $seconds . ' s';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($hours > 0) {
        return $hours . ' h ' . $minutes . ' min';
    }

    return $minutes . ' min';
}

function shortPdfText(?string $text, int $limit = 55): string
{
    $text = trim((string)$text);

    if ($text === '') {
        return 'Sin información';
    }

    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $limit) {
        return mb_substr($text, 0, $limit - 1, 'UTF-8') . '…';
    }

    if (!function_exists('mb_strlen') && strlen($text) > $limit) {
        return substr($text, 0, $limit - 1) . '…';
    }

    return $text;
}

function statusBadgeClass(?string $status): string
{
    return match ($status) {
        'ABIERTO' => 'badge badge-open',
        'EN_PROCESO' => 'badge badge-progress',
        'RESPONDIDO' => 'badge badge-answered',
        'CERRADO' => 'badge badge-closed',
        default => 'badge badge-neutral',
    };
}

function priorityBadgeClass(?string $priority): string
{
    return match (strtoupper((string)$priority)) {
        'ALTA', 'CRITICA', 'CRÍTICA' => 'badge badge-danger',
        'MEDIA' => 'badge badge-warning',
        'BAJA' => 'badge badge-low',
        default => 'badge badge-neutral',
    };
}

function buildExecutiveBarRows(array $rows, string $labelKey, string $valueKey, string $tone = 'green'): string
{
    if (empty($rows)) {
        return '<div class="empty-box">Sin datos disponibles</div>';
    }

    $max = 1;

    foreach ($rows as $row) {
        $max = max($max, (int)($row[$valueKey] ?? 0));
    }

    $html = '';

    foreach (array_slice($rows, 0, 4) as $row) {
        $label = e(shortPdfText((string)($row[$labelKey] ?? 'Sin dato'), 28));
        $value = (int)($row[$valueKey] ?? 0);
        $width = $value > 0 ? max(5, round(($value / $max) * 100)) : 0;

        $html .= '
            <div class="bar-row">
                <div class="bar-meta">
                    <span>' . $label . '</span>
                    <strong>' . $value . '</strong>
                </div>
                <div class="bar-track">
                    <div class="bar-fill bar-' . e($tone) . '" style="width:' . $width . '%"></div>
                </div>
            </div>';
    }

    return $html;
}

$avgTTAReadable = formatDurationReadable($aggregate['avg_tta_seconds'] ?? null);
$avgTTRReadable = formatDurationReadable($aggregate['avg_ttr_seconds'] ?? null);

$closedPercent = $totalTickets > 0 ? round(($closedTickets / $totalTickets) * 100, 1) : 0;
$activePercent = $totalTickets > 0 ? round(($activeTickets / $totalTickets) * 100, 1) : 0;

$slaTone = 'danger';
$slaLabel = 'Crítico';

if ($closedTotalForSla === 0) {
    $slaTone = 'neutral';
    $slaLabel = 'Sin cierres evaluables';
} elseif ($slaPercent >= 90) {
    $slaTone = 'success';
    $slaLabel = 'Óptimo';
} elseif ($slaPercent >= 75) {
    $slaTone = 'warning';
    $slaLabel = 'En seguimiento';
}

$topCategory = $ticketsByCategory[0] ?? [];
$topTechnician = $ticketsByTechnician[0] ?? [];

$insights = [];

if ($totalTickets === 0) {
    $insights[] = 'No existen tickets registrados para el periodo seleccionado.';
} else {
    if ($closedTotalForSla > 0) {
        $insights[] = 'El ' . $slaPercent . '% de los tickets cerrados cumplió el SLA (' . $closedWithinSla . ' de ' . $closedTotalForSla . ').';
    } else {
        $insights[] = 'Aún no existen tickets cerrados con datos suficientes para evaluar el SLA.';
    }

    if ($closedOutSla > 0) {
        $insights[] = $closedOutSla . ' ticket(s) cerraron fuera del tiempo objetivo y requieren revisión.';
    }

    if (!empty($topCategory)) {
        $insights[] = 'La categoría con mayor incidencia fue ' . ($topCategory['category'] ?? 'Sin categoría') . ' con ' . (int)($topCategory['total'] ?? 0) . ' ticket(s).';
    }

    if (!empty($topTechnician)) {
        $insights[] = ($topTechnician['technician_name'] ?? 'Sin asignar') . ' concentró la mayor carga registrada con ' . (int)($topTechnician['total'] ?? 0) . ' ticket(s).';
    }

    if ($totalTickets < 5) {
        $insights[] = 'La muestra del periodo es reducida; conviene interpretar los porcentajes junto con los valores absolutos.';
    }
}

$insights = array_slice(array_values(array_unique($insights)), 0, 3);

$levelRows = array_map(static function ($row) {
    $row['level_label'] = 'Nivel ' . (int)($row['support_level'] ?? 1);
    return $row;
}, $ticketsByLevel);

$logoHtml = $logoBase64 !== ''
    ? '<img class="report-logo" src="' . $logoBase64 . '" alt="PRONET SYSTEM S.A.C.">'
    : '<div class="brand-fallback"><strong>PRONET</strong><span>SYSTEM</span></div>';

$insightsHtml = '';

foreach ($insights as $index => $insight) {
    $insightsHtml .= '
        <div class="insight-item">
            <span class="insight-number">' . str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) . '</span>
            <p>' . e($insight) . '</p>
        </div>';
}

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 24px 28px 34px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #172033;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.4;
        }

        .report-header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid #ff7a00;
        }

        .report-header td {
            vertical-align: middle;
            padding: 0 0 13px;
        }

        .header-copy {
            width: 74%;
            padding-right: 24px !important;
        }

        .header-brand {
            width: 26%;
            text-align: right;
        }

        .report-kicker {
            margin: 0 0 5px;
            color: #ff7a00;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .report-title {
            margin: 0;
            color: #0f3d2e;
            font-size: 24px;
            line-height: 1.12;
            font-weight: 900;
        }

        .report-description {
            max-width: 720px;
            margin: 7px 0 0;
            color: #5d697c;
            font-size: 10px;
            line-height: 1.5;
        }

        .report-logo {
            max-width: 150px;
            max-height: 62px;
        }

        .brand-fallback {
            display: inline-block;
            text-align: right;
            color: #0f3d2e;
        }

        .brand-fallback strong {
            display: block;
            font-size: 20px;
        }

        .brand-fallback span {
            color: #ff7a00;
            font-size: 11px;
            letter-spacing: 1px;
        }

        .meta-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .meta-table td {
            width: 25%;
            padding: 8px 10px;
            border: 1px solid #dfe6ec;
            border-radius: 7px;
            background: #f8fafb;
        }

        .meta-label {
            display: block;
            margin-bottom: 2px;
            color: #8792a4;
            font-size: 7.8px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .meta-value {
            color: #273348;
            font-size: 9px;
            font-weight: 700;
        }

        .section-heading {
            width: 100%;
            margin-top: 13px;
            margin-bottom: 7px;
            border-collapse: collapse;
        }

        .section-heading td {
            vertical-align: middle;
        }

        .section-index {
            width: 32px;
            height: 32px;
            text-align: center;
            border-radius: 8px;
            background: #0f3d2e;
            color: #ffffff;
            font-size: 10px;
            font-weight: 900;
        }

        .section-copy {
            padding-left: 10px;
        }

        .section-copy strong {
            display: block;
            color: #172033;
            font-size: 14px;
            line-height: 1.2;
        }

        .section-copy span {
            color: #748094;
            font-size: 8.5px;
        }

        .focus-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 7px 0;
        }

        .focus-card {
            width: 33.333%;
            padding: 13px 14px;
            vertical-align: top;
            border: 1px solid #dce4e9;
            border-radius: 10px;
            background: #ffffff;
        }

        .focus-card.success {
            border-top: 4px solid #16805c;
        }

        .focus-card.warning {
            border-top: 4px solid #e59b1b;
        }

        .focus-card.danger {
            border-top: 4px solid #dc4c4c;
        }

        .focus-card.neutral {
            border-top: 4px solid #8792a4;
        }

        .focus-label {
            display: block;
            color: #7b8798;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .focus-value {
            display: block;
            margin-top: 5px;
            color: #0f3d2e;
            font-size: 22px;
            font-weight: 900;
            line-height: 1.05;
        }

        .focus-note {
            display: block;
            margin-top: 6px;
            color: #657286;
            font-size: 8px;
        }

        .status-chip {
            display: inline-block;
            margin-top: 7px;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 7px;
            font-weight: 800;
        }

        .chip-success {
            background: #e7f7ef;
            color: #087a51;
        }

        .chip-warning {
            background: #fff4d9;
            color: #a86800;
        }

        .chip-danger {
            background: #feecec;
            color: #bd3030;
        }

        .chip-neutral {
            background: #edf1f5;
            color: #627085;
        }

        .sla-progress {
            width: 100%;
            height: 7px;
            margin-top: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #edf1f4;
        }

        .sla-progress-fill {
            height: 7px;
            border-radius: 999px;
            background: #16805c;
        }

        .sla-progress-fill.warning {
            background: #e59b1b;
        }

        .sla-progress-fill.danger {
            background: #dc4c4c;
        }

        .sla-progress-fill.neutral {
            background: #8792a4;
        }

        .mini-kpi-table {
            width: 100%;
            margin-top: 8px;
            border-collapse: separate;
            border-spacing: 7px 0;
        }

        .mini-kpi {
            width: 20%;
            padding: 9px 10px;
            text-align: center;
            border: 1px solid #e1e7ec;
            border-radius: 9px;
            background: #f9fbfc;
        }

        .mini-kpi strong {
            display: block;
            color: #172033;
            font-size: 16px;
            line-height: 1;
        }

        .mini-kpi span {
            display: block;
            margin-top: 5px;
            color: #7a8799;
            font-size: 7.5px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .analysis-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 7px;
        }

        .analysis-card {
            width: 25%;
            min-height: 112px;
            padding: 11px 12px;
            vertical-align: top;
            border: 1px solid #dfe6eb;
            border-radius: 10px;
            background: #ffffff;
        }

        .analysis-title {
            margin-bottom: 9px;
            color: #0f3d2e;
            font-size: 11px;
            font-weight: 900;
        }

        .bar-row {
            margin-bottom: 7px;
        }

        .bar-meta {
            display: table;
            width: 100%;
            margin-bottom: 3px;
            color: #536074;
            font-size: 8px;
        }

        .bar-meta span,
        .bar-meta strong {
            display: table-cell;
        }

        .bar-meta strong {
            text-align: right;
            color: #253248;
        }

        .bar-track {
            width: 100%;
            height: 7px;
            overflow: hidden;
            border-radius: 999px;
            background: #edf1f4;
        }

        .bar-fill {
            height: 7px;
            border-radius: 999px;
        }

        .bar-green {
            background: #16805c;
        }

        .bar-orange {
            background: #ff7a00;
        }

        .bar-blue {
            background: #3b78b4;
        }

        .bar-slate {
            background: #64748b;
        }

        .empty-box {
            padding: 14px;
            text-align: center;
            border: 1px dashed #cbd5df;
            border-radius: 8px;
            background: #f9fbfc;
            color: #748094;
        }

        .insights-box {
            padding: 10px 12px;
            border: 1px solid #f0dcc5;
            border-left: 4px solid #ff7a00;
            border-radius: 9px;
            background: #fff9f2;
        }

        .insight-item {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }

        .insight-item:last-child {
            margin-bottom: 0;
        }

        .insight-number,
        .insight-item p {
            display: table-cell;
            vertical-align: top;
        }

        .insight-number {
            width: 24px;
            color: #ff7a00;
            font-size: 8px;
            font-weight: 900;
        }

        .insight-item p {
            margin: 0;
            color: #4e5c70;
            font-size: 8.5px;
            line-height: 1.42;
        }

        .detail-page {
            page-break-before: always;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table th {
            padding: 7px 8px;
            border: 1px solid #274d40;
            background: #0f3d2e;
            color: #ffffff;
            font-size: 8px;
            font-weight: 800;
            text-align: center;
        }

        .data-table td {
            padding: 7px 8px;
            border: 1px solid #dfe5ea;
            color: #39465a;
            font-size: 8px;
            text-align: center;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #f8fafb;
        }

        .data-table .text-left {
            text-align: left;
        }

        .level-pill {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            background: #e9f5ef;
            color: #0f6f4d;
            font-size: 7px;
            font-weight: 900;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 7px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-open {
            background: #e8f1ff;
            color: #2f67a6;
        }

        .badge-progress {
            background: #fff3d8;
            color: #a56b00;
        }

        .badge-answered {
            background: #eeeafe;
            color: #6550a9;
        }

        .badge-closed {
            background: #e7f7ef;
            color: #087a51;
        }

        .badge-danger {
            background: #feeaea;
            color: #bc3333;
        }

        .badge-warning {
            background: #fff1dc;
            color: #b66500;
        }

        .badge-low {
            background: #edf6ef;
            color: #39734f;
        }

        .badge-neutral {
            background: #edf1f5;
            color: #657286;
        }

        .definition-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .definition-table td {
            width: 33.333%;
            padding: 9px 10px;
            vertical-align: top;
            border: 1px solid #e1e7ec;
            border-radius: 8px;
            background: #f9fbfc;
        }

        .definition-table strong {
            display: block;
            color: #0f3d2e;
            font-size: 9px;
        }

        .definition-table span {
            display: block;
            margin-top: 4px;
            color: #667488;
            font-size: 7.8px;
            line-height: 1.4;
        }

        .page-note {
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 7px;
            background: #f3f6f8;
            color: #718094;
            font-size: 7.5px;
        }
    </style>
</head>
<body>
    <table class="report-header">
        <tr>
            <td class="header-copy">
                <p class="report-kicker">Reporte ejecutivo de soporte técnico</p>
                <h1 class="report-title">Estado operativo, SLA e indicadores</h1>
                <p class="report-description">
                    Resumen gerencial para analizar volumen de incidencias, tiempos de atención y resolución,
                    cumplimiento de acuerdos de servicio y distribución de carga técnica.
                </p>
            </td>
            <td class="header-brand">' . $logoHtml . '</td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">Empresa</span>
                <span class="meta-value">PRONET SYSTEM S.A.C.</span>
            </td>
            <td>
                <span class="meta-label">Periodo</span>
                <span class="meta-value">' . e($periodLabel) . '</span>
            </td>
            <td>
                <span class="meta-label">Alcance</span>
                <span class="meta-value">' . e($scopeLabel) . '</span>
            </td>
            <td>
                <span class="meta-label">Generado</span>
                <span class="meta-value">' . e($generatedAt) . '</span>
            </td>
        </tr>
    </table>

    <table class="section-heading">
        <tr>
            <td class="section-index">01</td>
            <td class="section-copy">
                <strong>Resumen ejecutivo</strong>
                <span>Indicadores esenciales para una lectura rápida del desempeño.</span>
            </td>
        </tr>
    </table>

    <table class="focus-table">
        <tr>
            <td class="focus-card ' . e($slaTone) . '">
                <span class="focus-label">Cumplimiento SLA</span>
                <span class="focus-value">' . e($slaPercent) . '%</span>
                <div class="sla-progress">
                    <div class="sla-progress-fill ' . e($slaTone) . '" style="width:' . min(100, max(0, (float)$slaPercent)) . '%"></div>
                </div>
                <span class="status-chip chip-' . e($slaTone) . '">' . e($slaLabel) . '</span>
                <span class="focus-note">' . $closedWithinSla . ' cumplido(s) / ' . $closedOutSla . ' fuera de SLA</span>
            </td>

            <td class="focus-card neutral">
                <span class="focus-label">Tiempo de primera atención</span>
                <span class="focus-value">' . e($avgTTAReadable) . '</span>
                <span class="status-chip chip-neutral">TTA promedio</span>
                <span class="focus-note">Formato completo: ' . e($avgTTA) . '</span>
            </td>

            <td class="focus-card neutral">
                <span class="focus-label">Tiempo de resolución</span>
                <span class="focus-value">' . e($avgTTRReadable) . '</span>
                <span class="status-chip chip-neutral">TTR promedio</span>
                <span class="focus-note">Formato completo: ' . e($avgTTR) . '</span>
            </td>
        </tr>
    </table>

    <table class="mini-kpi-table">
        <tr>
            <td class="mini-kpi"><strong>' . $totalTickets . '</strong><span>Total tickets</span></td>
            <td class="mini-kpi"><strong>' . $activeTickets . '</strong><span>Activos · ' . e($activePercent) . '%</span></td>
            <td class="mini-kpi"><strong>' . $closedTickets . '</strong><span>Cerrados · ' . e($closedPercent) . '%</span></td>
            <td class="mini-kpi"><strong>' . $escalatedTickets . '</strong><span>Escalados</span></td>
            <td class="mini-kpi"><strong>' . $closedOutSla . '</strong><span>Fuera de SLA</span></td>
        </tr>
    </table>

    <table class="section-heading">
        <tr>
            <td class="section-index">02</td>
            <td class="section-copy">
                <strong>Distribución operativa</strong>
                <span>Composición de tickets por estado, prioridad, categoría y nivel de atención.</span>
            </td>
        </tr>
    </table>

    <table class="analysis-grid">
        <tr>
            <td class="analysis-card">
                <div class="analysis-title">Tickets por estado</div>
                ' . buildExecutiveBarRows($ticketsByStatus, 'status_label', 'total', 'green') . '
            </td>
            <td class="analysis-card">
                <div class="analysis-title">Prioridad</div>
                ' . buildExecutiveBarRows($ticketsByPriority, 'priority', 'total', 'orange') . '
            </td>
            <td class="analysis-card">
                <div class="analysis-title">Categorías frecuentes</div>
                ' . buildExecutiveBarRows($ticketsByCategory, 'category', 'total', 'blue') . '
            </td>
            <td class="analysis-card">
                <div class="analysis-title">Nivel técnico</div>
                ' . buildExecutiveBarRows($levelRows, 'level_label', 'total', 'slate') . '
            </td>
        </tr>
    </table>

    <table class="section-heading">
        <tr>
            <td class="section-index">03</td>
            <td class="section-copy">
                <strong>Hallazgos clave</strong>
                <span>Interpretación automática basada en los resultados del periodo.</span>
            </td>
        </tr>
    </table>

    <div class="insights-box">' . $insightsHtml . '</div>

    <div class="detail-page">
        <table class="report-header">
            <tr>
                <td class="header-copy">
                    <p class="report-kicker">Detalle operativo</p>
                    <h1 class="report-title">Equipo técnico y tickets recientes</h1>
                    <p class="report-description">
                        Desglose de carga por responsable y últimas incidencias registradas en el periodo.
                    </p>
                </td>
                <td class="header-brand">' . $logoHtml . '</td>
            </tr>
        </table>

        <table class="section-heading">
            <tr>
                <td class="section-index">04</td>
                <td class="section-copy">
                    <strong>Rendimiento por técnico</strong>
                    <span>Tickets activos, cierres y escalamiento según nivel de soporte.</span>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:36%">Técnico</th>
                    <th style="width:16%">Nivel</th>
                    <th style="width:16%">Activos</th>
                    <th style="width:16%">Cerrados</th>
                    <th style="width:16%">Escalados</th>
                </tr>
            </thead>
            <tbody>';

if (!empty($technicianSummary)) {
    foreach ($technicianSummary as $technician) {
        $html .= '
                <tr>
                    <td class="text-left">' . e($technician['name'] ?? 'Sin nombre') . '</td>
                    <td><span class="level-pill">N' . (int)($technician['tech_level'] ?? 1) . '</span></td>
                    <td>' . (int)($technician['active_tickets'] ?? 0) . '</td>
                    <td>' . (int)($technician['closed_tickets'] ?? 0) . '</td>
                    <td>' . (int)($technician['escalated_tickets'] ?? 0) . '</td>
                </tr>';
    }
} else {
    $html .= '<tr><td colspan="5">Sin técnicos registrados.</td></tr>';
}

$html .= '
            </tbody>
        </table>

        <table class="section-heading">
            <tr>
                <td class="section-index">05</td>
                <td class="section-copy">
                    <strong>Últimos tickets registrados</strong>
                    <span>Incidencias más recientes incluidas en el alcance del reporte.</span>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:7%">Ticket</th>
                    <th style="width:28%">Asunto</th>
                    <th style="width:12%">Estado</th>
                    <th style="width:11%">Prioridad</th>
                    <th style="width:14%">Categoría</th>
                    <th style="width:16%">Técnico</th>
                    <th style="width:12%">Fecha</th>
                </tr>
            </thead>
            <tbody>';

if (!empty($recentTickets)) {
    foreach ($recentTickets as $ticket) {
        $html .= '
                <tr>
                    <td><strong>#' . (int)$ticket['id'] . '</strong></td>
                    <td class="text-left">' . e(shortPdfText($ticket['subject'] ?? 'Sin asunto', 48)) . '</td>
                    <td><span class="' . e(statusBadgeClass($ticket['status'] ?? '')) . '">' . e(statusLabel($ticket['status'] ?? '')) . '</span></td>
                    <td><span class="' . e(priorityBadgeClass($ticket['priority'] ?? '')) . '">' . e($ticket['priority'] ?? 'Sin prioridad') . '</span></td>
                    <td>' . e(shortPdfText($ticket['category'] ?? 'Sin categoría', 20)) . '</td>
                    <td>' . e(shortPdfText($ticket['technician_name'] ?? 'Sin asignar', 22)) . '</td>
                    <td>' . e(!empty($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : '-') . '</td>
                </tr>';
    }
} else {
    $html .= '<tr><td colspan="7">No existen tickets registrados para el periodo seleccionado.</td></tr>';
}

$html .= '
            </tbody>
        </table>

        <table class="definition-table">
            <tr>
                <td>
                    <strong>TTA · Tiempo de atención</strong>
                    <span>Tiempo transcurrido desde el registro del ticket hasta la primera respuesta técnica.</span>
                </td>
                <td>
                    <strong>TTR · Tiempo de resolución</strong>
                    <span>Tiempo total desde el registro de la incidencia hasta su cierre definitivo.</span>
                </td>
                <td>
                    <strong>% SLA · Cumplimiento</strong>
                    <span>Porcentaje de tickets cerrados dentro del plazo objetivo configurado.</span>
                </td>
            </tr>
        </table>

        <div class="page-note">
            Este informe combina valores absolutos y porcentajes. Para periodos con pocos tickets,
            se recomienda priorizar la lectura de cantidades y revisar el detalle individual de las incidencias.
        </div>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();
$footerFont = $fontMetrics->getFont('DejaVu Sans', 'normal');

$canvas->page_text(
    30,
    570,
    'PRONET SYSTEM S.A.C.  |  Reporte automático de soporte  |  Página {PAGE_NUM} de {PAGE_COUNT}',
    $footerFont,
    7.5,
    [0.39, 0.45, 0.53]
);

$filenameScope = $scope === 'technician'
    ? 'tecnico_' . $technicianId
    : 'todos_los_tecnicos';

$filename = 'reporte_ejecutivo_sla_'
    . $filenameScope
    . '_'
    . $filenamePeriod
    . '_'
    . date('Ymd_His')
    . '.pdf';

$dompdf->stream($filename, [
    'Attachment' => true,
]);
