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

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$whereSql = '1 = 1';
$params = [];
$periodLabel = 'Todos los registros';

if ($dateFrom !== '') {
    $whereSql .= ' AND t.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $whereSql .= ' AND t.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}

if ($dateFrom !== '' || $dateTo !== '') {
    $periodLabel = trim(($dateFrom !== '' ? $dateFrom : 'Inicio') . ' al ' . ($dateTo !== '' ? $dateTo : 'actualidad'));
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

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            color: #0f172a;
            background: #ffffff;
            font-size: 11px;
        }
        .report-header {
            background: #0f3d2e;
            color: #ffffff;
            padding: 20px 22px;
            border-radius: 14px;
            display: table;
            width: 100%;
            box-sizing: border-box;
        }
        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }
        .header-left { width: 68%; }
        .header-right { width: 32%; text-align: right; }
        .report-title {
            margin: 0 0 8px;
            font-size: 22px;
            line-height: 1.2;
            font-weight: 800;
        }
        .report-description {
            margin: 0;
            color: #d1fae5;
            line-height: 1.5;
            font-size: 11px;
        }
        .logo {
            max-width: 170px;
            max-height: 72px;
        }
        .meta-row {
            margin-top: 10px;
            width: 100%;
            border-collapse: collapse;
        }
        .meta-row td {
            border: 1px solid #dbe3ec;
            padding: 8px 10px;
            background: #f8fafc;
        }
        .section-title {
            margin-top: 12px;
            background: #1f7a5a;
            color: #ffffff;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-top: 8px;
        }
        .kpi-table td {
            width: 25%;
            padding: 12px 10px;
            text-align: center;
            border: 1px solid #dbe3ec;
            border-radius: 12px;
            background: #f8fafc;
        }
        .kpi-label {
            display: block;
            margin-bottom: 6px;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .kpi-value {
            display: block;
            color: #0f3d2e;
            font-size: 18px;
            font-weight: 900;
        }
        .grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
        }
        .grid-table td {
            width: 50%;
            vertical-align: top;
            border: 1px solid #dbe3ec;
            border-radius: 12px;
            padding: 12px;
            background: #ffffff;
        }
        .box-title {
            color: #0f3d2e;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .bar-row { margin-bottom: 9px; }
        .bar-meta {
            display: table;
            width: 100%;
            margin-bottom: 4px;
            color: #334155;
            font-size: 10px;
        }
        .bar-meta span,
        .bar-meta strong { display: table-cell; }
        .bar-meta strong { text-align: right; color: #0f3d2e; }
        .bar-track {
            width: 100%;
            height: 12px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }
        .bar-fill {
            height: 12px;
            border-radius: 999px;
            background: #1f7a5a;
        }
        .sla-card {
            text-align: center;
            padding: 8px 0 4px;
        }
        .sla-percent {
            display: inline-block;
            width: 92px;
            height: 92px;
            line-height: 92px;
            text-align: center;
            border-radius: 50%;
            background: #1f7a5a;
            color: #ffffff;
            font-size: 22px;
            font-weight: 900;
            border: 10px solid #d1fae5;
        }
        .sla-split {
            margin-top: 12px;
            font-size: 10px;
            color: #475569;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .summary-table th {
            background: #0f3d2e;
            color: #ffffff;
            padding: 8px;
            border: 1px solid #dbe3ec;
            font-size: 10px;
        }
        .summary-table td {
            padding: 8px;
            border: 1px solid #dbe3ec;
            text-align: center;
            font-size: 10px;
        }
        .summary-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef6f2;
            color: #0f3d2e;
            font-weight: 800;
        }
        .empty-box {
            padding: 12px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
        }
        .interpretation {
            margin-top: 10px;
            padding: 12px;
            border: 1px solid #dbe3ec;
            border-left: 5px solid #ff7a00;
            border-radius: 10px;
            background: #fff7ed;
            color: #334155;
            line-height: 1.55;
        }
        .footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #dbe3ec;
            text-align: center;
            color: #64748b;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <div class="header-left">
            <h1 class="report-title">Informe de estado de SLA e indicadores</h1>
            <p class="report-description">
                Reporte gerencial de mantenimiento correctivo de redes y comunicaciones. Incluye estadísticas de tickets,
                tiempos de respuesta, tiempos de resolución, cumplimiento SLA, niveles técnicos y carga operativa del equipo.
            </p>
        </div>
        <div class="header-right">
            ' . ($logoBase64 !== '' ? '<img class="logo" src="' . $logoBase64 . '" alt="PRONET SYSTEM S.A.C.">' : '<strong>PRONET<br>SYSTEM S.A.C.</strong>') . '
        </div>
    </div>

    <table class="meta-row">
        <tr>
            <td><strong>Empresa:</strong> PRONET SYSTEM S.A.C.</td>
            <td><strong>Periodo:</strong> ' . e($periodLabel) . '</td>
            <td><strong>Generado:</strong> ' . e($generatedAt) . '</td>
        </tr>
        <tr>
            <td colspan="3"><strong>Alcance:</strong> ' . e($scopeLabel) . '</td>
        </tr>
    </table>

    <div class="section-title">Resumen general</div>

    <table class="kpi-table">
        <tr>
            <td><span class="kpi-label">Total tickets</span><span class="kpi-value">' . $totalTickets . '</span></td>
            <td><span class="kpi-label">Activos</span><span class="kpi-value">' . $activeTickets . '</span></td>
            <td><span class="kpi-label">Cerrados</span><span class="kpi-value">' . $closedTickets . '</span></td>
            <td><span class="kpi-label">Escalados</span><span class="kpi-value">' . $escalatedTickets . '</span></td>
        </tr>
        <tr>
            <td><span class="kpi-label">TTA promedio</span><span class="kpi-value">' . e($avgTTA) . '</span></td>
            <td><span class="kpi-label">TTR promedio</span><span class="kpi-value">' . e($avgTTR) . '</span></td>
            <td><span class="kpi-label">SLA cumplido</span><span class="kpi-value">' . e($slaPercent) . '%</span></td>
            <td><span class="kpi-label">Fuera SLA</span><span class="kpi-value">' . $closedOutSla . '</span></td>
        </tr>
    </table>

    <div class="section-title">Indicadores visuales</div>

    <table class="grid-table">
        <tr>
            <td>
                <div class="box-title">Tickets por estado</div>
                ' . buildBarRows($ticketsByStatus, 'status_label', 'total') . '
            </td>
            <td>
                <div class="box-title">Cumplimiento SLA</div>
                <div class="sla-card">
                    <div class="sla-percent">' . e($slaPercent) . '%</div>
                    <div class="sla-split">
                        Cumplidos: <strong>' . $closedWithinSla . '</strong> &nbsp; | &nbsp;
                        No cumplidos: <strong>' . $closedOutSla . '</strong>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="box-title">Tickets por prioridad</div>
                ' . buildBarRows($ticketsByPriority, 'priority', 'total') . '
            </td>
            <td>
                <div class="box-title">Categorías más frecuentes</div>
                ' . buildBarRows($ticketsByCategory, 'category', 'total') . '
            </td>
        </tr>
        <tr>
            <td>
                <div class="box-title">Tickets por nivel técnico</div>
                ' . buildBarRows(array_map(static function ($row) {
                    $row['level_label'] = 'Nivel ' . (int)($row['support_level'] ?? 1);
                    return $row;
                }, $ticketsByLevel), 'level_label', 'total') . '
            </td>
            <td>
                <div class="box-title">Carga por técnico</div>
                ' . buildBarRows($ticketsByTechnician, 'technician_name', 'total') . '
            </td>
        </tr>
    </table>

    <div class="section-title">Resumen por técnico</div>

    <table class="summary-table">
        <thead>
            <tr>
                <th>Técnico</th>
                <th>Nivel</th>
                <th>Activos</th>
                <th>Cerrados</th>
                <th>Escalados</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($technicianSummary)) {
    foreach ($technicianSummary as $technician) {
        $html .= '
            <tr>
                <td>' . e($technician['name'] ?? 'Sin nombre') . '</td>
                <td><span class="pill">N' . (int)($technician['tech_level'] ?? 1) . '</span></td>
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

    <div class="section-title">Últimos tickets registrados</div>

    <table class="summary-table">
        <thead>
            <tr>
                <th>Ticket</th>
                <th>Asunto</th>
                <th>Estado</th>
                <th>Prioridad</th>
                <th>Categoría</th>
                <th>Técnico</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($recentTickets)) {
    foreach ($recentTickets as $ticket) {
        $html .= '
            <tr>
                <td>#' . (int)$ticket['id'] . '</td>
                <td>' . e($ticket['subject'] ?? 'Sin asunto') . '</td>
                <td>' . e(statusLabel($ticket['status'] ?? '')) . '</td>
                <td>' . e($ticket['priority'] ?? 'Sin prioridad') . '</td>
                <td>' . e($ticket['category'] ?? 'Sin categoría') . '</td>
                <td>' . e($ticket['technician_name'] ?? 'Sin asignar') . '</td>
                <td>' . e(!empty($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : '-') . '</td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="7">No existen tickets registrados para el periodo seleccionado.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="interpretation">
        <strong>Lectura rápida:</strong> El TTA representa el tiempo transcurrido desde la apertura del ticket hasta la primera atención.
        El TTR representa el tiempo transcurrido desde la apertura hasta el cierre del ticket. El porcentaje SLA expresa la proporción
        de tickets cerrados dentro del plazo objetivo configurado para cada incidencia.
    </div>

    <div class="footer">
        PRONET SYSTEM S.A.C. - Reporte generado automáticamente por el sistema Helpdesk.
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filenameScope = $scope === 'technician' ? 'tecnico_' . $technicianId : 'todos_los_tecnicos';
$filename = 'reporte_indicadores_sla_' . $filenameScope . '_' . date('Ymd_His') . '.pdf';

$dompdf->stream($filename, [
    'Attachment' => true,
]);
