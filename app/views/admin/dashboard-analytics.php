<?php
$title = 'Dashboard';

$activePage = 'reports';
$pageTitle = 'Dashboard avanzado';
$pageSubtitle = 'Indicadores operativos de tickets, SLA, niveles técnicos y carga del equipo.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/index.php',
        'class' => 'btn-secondary',
        'text' => 'Panel admin'
    ]
];

$totalTickets = $totalTickets ?? 0;
$openTickets = $openTickets ?? 0;
$inProgressTickets = $inProgressTickets ?? 0;
$answeredTickets = $answeredTickets ?? 0;
$closedTickets = $closedTickets ?? 0;
$activeTickets = $activeTickets ?? 0;
$escalatedTickets = $escalatedTickets ?? 0;
$closedWithinSla = $closedWithinSla ?? 0;
$closedOutSla = $closedOutSla ?? 0;

$avgTTA = $avgTTA ?? '00:00:00';
$avgTTR = $avgTTR ?? '00:00:00';
$slaPercent = $slaPercent ?? 0;

$ticketsByStatus = $ticketsByStatus ?? [];
$ticketsByPriority = $ticketsByPriority ?? [];
$ticketsByCategory = $ticketsByCategory ?? [];
$ticketsByTechnician = $ticketsByTechnician ?? [];
$ticketsByLevel = $ticketsByLevel ?? [];
$technicianSummary = $technicianSummary ?? [];
$levelSummary = $levelSummary ?? [];
$recentTickets = $recentTickets ?? [];

function dashboardChartLabels(array $rows, string $labelKey): array
{
    return array_map(static fn($row) => (string)($row[$labelKey] ?? 'Sin dato'), $rows);
}

function dashboardChartValues(array $rows, string $valueKey): array
{
    return array_map(static fn($row) => (int)($row[$valueKey] ?? 0), $rows);
}

function dashboardStatusLabel(?string $status): string
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

function dashboardPercent(int $value, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    return number_format(($value / $total) * 100, 1) . '%';
}


/*
|--------------------------------------------------------------------------
| Dashboard con SLA según contrato de empresa
|--------------------------------------------------------------------------
| Esta sección recalcula los indicadores principales usando el contrato
| SLA de client_companies:
| - 24_7: tiempo calendario real.
| - 8_5: solo horario laboral definido en business_hours.php.
|
| Si ocurre algún error, se mantienen los valores recibidos previamente.
*/
if (!function_exists('dashboardTableExists')) {
    function dashboardTableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

function dashboardHoursToClock(float|int|string|null $hours): string
{
    if (function_exists('formatDecimalHoursToClock')) {
        return formatDecimalHoursToClock($hours);
    }

    if ($hours === null || $hours === '' || !is_numeric($hours)) {
        return '00:00:00';
    }

    $totalSeconds = (int)round(((float)$hours) * 3600);
    $h = intdiv($totalSeconds, 3600);
    $m = intdiv($totalSeconds % 3600, 60);
    $sec = $totalSeconds % 60;

    return sprintf('%02d:%02d:%02d', $h, $m, $sec);
}

function dashboardElapsedHoursByContract(array $ticket, ?string $startDateTime, ?string $endDateTime): float
{
    if (empty($startDateTime) || empty($endDateTime)) {
        return 0;
    }

    $contractType = $ticket['company_sla_contract_type']
        ?? $ticket['sla_contract_type']
        ?? $ticket['contract_type']
        ?? '8_5';

    if (function_exists('calculateSlaElapsedHours')) {
        return calculateSlaElapsedHours($startDateTime, $endDateTime, $contractType);
    }

    if (function_exists('normalizeSlaContractType') && normalizeSlaContractType($contractType) === '8_5' && function_exists('calculateBusinessHours')) {
        return calculateBusinessHours($startDateTime, $endDateTime);
    }

    try {
        $start = new DateTime($startDateTime, new DateTimeZone('America/Lima'));
        $end = new DateTime($endDateTime, new DateTimeZone('America/Lima'));
    } catch (Throwable $exception) {
        return 0;
    }

    if ($end <= $start) {
        return 0;
    }

    return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
}

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../helpers/sla_helper.php';

    if (isset($pdo) && $pdo instanceof PDO) {
        $dashboardTicketsStmt = $pdo->query("
            SELECT
                t.*,
                COALESCE(cc.sla_contract_type, requester_company.sla_contract_type, '8_5') AS company_sla_contract_type,
                tech.name AS technician_name,
                COALESCE(tech.tech_level, t.support_level, 1) AS technician_level
            FROM tickets t
            LEFT JOIN users requester ON requester.id = t.requester_id
            LEFT JOIN client_companies requester_company ON requester_company.id = requester.company_id
            LEFT JOIN client_companies cc ON cc.id = t.company_id
            LEFT JOIN users tech ON tech.id = t.assigned_to
            ORDER BY t.created_at DESC
        ");

        $dashboardTickets = $dashboardTicketsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $dashboardEscalatedTicketIds = [];
        if (dashboardTableExists($pdo, 'ticket_level_history')) {
            $dashboardEscalatedRows = $pdo->query("SELECT DISTINCT ticket_id FROM ticket_level_history")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $dashboardEscalatedTicketIds = array_fill_keys(array_map('intval', $dashboardEscalatedRows), true);
        }

        $statusOrder = ['ABIERTO', 'EN_PROCESO', 'RESPONDIDO', 'CERRADO'];
        $statusCounts = array_fill_keys($statusOrder, 0);
        $priorityCounts = [];
        $categoryCounts = [];
        $technicianCounts = [];
        $levelCounts = [];
        $levelSummaryMap = [
            1 => ['level' => 1, 'current_total' => 0, 'active_total' => 0, 'closed_total' => 0, 'escalated_total' => 0],
            2 => ['level' => 2, 'current_total' => 0, 'active_total' => 0, 'closed_total' => 0, 'escalated_total' => 0],
            3 => ['level' => 3, 'current_total' => 0, 'active_total' => 0, 'closed_total' => 0, 'escalated_total' => 0],
        ];

        $technicianSummaryMap = [];
        $techRows = $pdo->query("
            SELECT id, name, COALESCE(tech_level, 1) AS tech_level
            FROM users
            WHERE role = 'TECH'
            ORDER BY COALESCE(tech_level, 1) ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($techRows as $techRow) {
            $techId = (int)$techRow['id'];
            $technicianSummaryMap[$techId] = [
                'id' => $techId,
                'name' => $techRow['name'] ?? 'Técnico',
                'tech_level' => (int)($techRow['tech_level'] ?? 1),
                'active_tickets' => 0,
                'closed_tickets' => 0,
                'escalated_tickets' => 0,
            ];
        }

        $totalTickets = count($dashboardTickets);
        $openTickets = 0;
        $inProgressTickets = 0;
        $answeredTickets = 0;
        $closedTickets = 0;
        $activeTickets = 0;
        $escalatedTickets = 0;
        $closedWithinSla = 0;
        $closedOutSla = 0;

        $ttaTotalHours = 0;
        $ttaCount = 0;
        $ttrTotalHours = 0;
        $ttrCount = 0;

        foreach ($dashboardTickets as $ticket) {
            $status = strtoupper((string)($ticket['status'] ?? 'ABIERTO'));
            $priority = strtoupper((string)($ticket['priority'] ?? 'SIN PRIORIDAD'));
            $category = strtoupper((string)($ticket['category'] ?? 'OTROS'));
            $ticketId = (int)($ticket['id'] ?? 0);
            $assignedTo = isset($ticket['assigned_to']) ? (int)$ticket['assigned_to'] : 0;
            $supportLevel = max(1, min(3, (int)($ticket['support_level'] ?? $ticket['technician_level'] ?? 1)));
            $isClosed = $status === 'CERRADO';
            $isEscalated = isset($dashboardEscalatedTicketIds[$ticketId]) || $supportLevel > 1;

            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;

            $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $levelCounts[$supportLevel] = ($levelCounts[$supportLevel] ?? 0) + 1;

            if (!isset($levelSummaryMap[$supportLevel])) {
                $levelSummaryMap[$supportLevel] = ['level' => $supportLevel, 'current_total' => 0, 'active_total' => 0, 'closed_total' => 0, 'escalated_total' => 0];
            }
            $levelSummaryMap[$supportLevel]['current_total']++;

            if ($status === 'ABIERTO') {
                $openTickets++;
            } elseif ($status === 'EN_PROCESO') {
                $inProgressTickets++;
            } elseif ($status === 'RESPONDIDO') {
                $answeredTickets++;
            } elseif ($isClosed) {
                $closedTickets++;
            }

            if (!$isClosed) {
                $activeTickets++;
                $levelSummaryMap[$supportLevel]['active_total']++;
            } else {
                $levelSummaryMap[$supportLevel]['closed_total']++;
            }

            if ($isEscalated) {
                $escalatedTickets++;
                $levelSummaryMap[$supportLevel]['escalated_total']++;
            }

            if ($assignedTo > 0) {
                $technicianName = $ticket['technician_name'] ?? 'Sin nombre';
                $technicianCounts[$technicianName] = ($technicianCounts[$technicianName] ?? 0) + 1;

                if (!isset($technicianSummaryMap[$assignedTo])) {
                    $technicianSummaryMap[$assignedTo] = [
                        'id' => $assignedTo,
                        'name' => $technicianName,
                        'tech_level' => (int)($ticket['technician_level'] ?? $supportLevel),
                        'active_tickets' => 0,
                        'closed_tickets' => 0,
                        'escalated_tickets' => 0,
                    ];
                }

                if ($isClosed) {
                    $technicianSummaryMap[$assignedTo]['closed_tickets']++;
                } else {
                    $technicianSummaryMap[$assignedTo]['active_tickets']++;
                }

                if ($isEscalated) {
                    $technicianSummaryMap[$assignedTo]['escalated_tickets']++;
                }
            }

            if (!empty($ticket['created_at']) && !empty($ticket['first_response_at'])) {
                $ttaTotalHours += dashboardElapsedHoursByContract($ticket, $ticket['created_at'], $ticket['first_response_at']);
                $ttaCount++;
            }

            if ($isClosed && !empty($ticket['created_at']) && !empty($ticket['closed_at'])) {
                $ttrHours = dashboardElapsedHoursByContract($ticket, $ticket['created_at'], $ticket['closed_at']);
                $ttrTotalHours += $ttrHours;
                $ttrCount++;

                $slaHours = (float)($ticket['sla_hours'] ?? 0);
                if ($slaHours > 0 && $ttrHours <= $slaHours) {
                    $closedWithinSla++;
                } else {
                    $closedOutSla++;
                }
            }
        }

        $avgTTA = dashboardHoursToClock($ttaCount > 0 ? $ttaTotalHours / $ttaCount : 0);
        $avgTTR = dashboardHoursToClock($ttrCount > 0 ? $ttrTotalHours / $ttrCount : 0);
        $slaClosedTotal = $closedWithinSla + $closedOutSla;
        $slaPercent = $slaClosedTotal > 0 ? number_format(($closedWithinSla / $slaClosedTotal) * 100, 1, '.', '') : 0;

        $ticketsByStatus = [];
        foreach ($statusCounts as $statusKey => $count) {
            $ticketsByStatus[] = [
                'status_label' => dashboardStatusLabel($statusKey),
                'total' => (int)$count,
            ];
        }

        arsort($priorityCounts);
        $ticketsByPriority = array_map(
            static fn($priority, $count) => ['priority' => $priority, 'total' => (int)$count],
            array_keys($priorityCounts),
            array_values($priorityCounts)
        );

        arsort($categoryCounts);
        $ticketsByCategory = array_map(
            static fn($category, $count) => ['category' => $category, 'total' => (int)$count],
            array_keys($categoryCounts),
            array_values($categoryCounts)
        );

        arsort($technicianCounts);
        $ticketsByTechnician = array_map(
            static fn($technicianName, $count) => ['technician_name' => $technicianName, 'total' => (int)$count],
            array_keys($technicianCounts),
            array_values($technicianCounts)
        );

        ksort($levelCounts);
        $ticketsByLevel = array_map(
            static fn($level, $count) => ['support_level' => (int)$level, 'total' => (int)$count],
            array_keys($levelCounts),
            array_values($levelCounts)
        );

        ksort($levelSummaryMap);
        $levelSummary = array_values($levelSummaryMap);

        $technicianSummary = array_values(array_filter(
            $technicianSummaryMap,
            static fn($row) => ((int)($row['active_tickets'] ?? 0) + (int)($row['closed_tickets'] ?? 0) + (int)($row['escalated_tickets'] ?? 0)) > 0
        ));

        usort($technicianSummary, static function ($a, $b) {
            return [(int)($a['tech_level'] ?? 1), (string)($a['name'] ?? '')]
                <=> [(int)($b['tech_level'] ?? 1), (string)($b['name'] ?? '')];
        });

        $recentTickets = array_slice($dashboardTickets, 0, 8);
    }
} catch (Throwable $dashboardException) {
    // Se mantienen los valores recibidos para no romper la vista si alguna tabla/columna aún no existe.
}

$statusLabels = dashboardChartLabels($ticketsByStatus, 'status_label');
$statusValues = dashboardChartValues($ticketsByStatus, 'total');

$priorityLabels = dashboardChartLabels($ticketsByPriority, 'priority');
$priorityValues = dashboardChartValues($ticketsByPriority, 'total');

$categoryLabels = dashboardChartLabels($ticketsByCategory, 'category');
$categoryValues = dashboardChartValues($ticketsByCategory, 'total');

$technicianLabels = dashboardChartLabels($ticketsByTechnician, 'technician_name');
$technicianValues = dashboardChartValues($ticketsByTechnician, 'total');

$levelLabels = array_map(
    static fn($row) => 'Nivel ' . (int)($row['support_level'] ?? 1),
    $ticketsByLevel
);
$levelValues = dashboardChartValues($ticketsByLevel, 'total');

$slaLabels = ['Dentro del SLA', 'Fuera del SLA'];
$slaValues = [(int)$closedWithinSla, (int)$closedOutSla];

$lastUpdatedAt = date('d/m/Y H:i');
$activePercent = dashboardPercent((int)$activeTickets, (int)$totalTickets);
$closedPercent = dashboardPercent((int)$closedTickets, (int)$totalTickets);
$answeredPercent = dashboardPercent((int)$answeredTickets, (int)$totalTickets);
$pdfTechnicians = [];

try {
    require_once __DIR__ . '/../../config/database.php';

    if (isset($pdo) && $pdo instanceof PDO) {
        $pdfTechnicianStmt = $pdo->query("\n            SELECT\n                id,\n                name,\n                COALESCE(tech_level, 1) AS tech_level\n            FROM users\n            WHERE role = 'TECH'\n              AND status = 1\n            ORDER BY COALESCE(tech_level, 1) ASC, name ASC\n        ");

        $pdfTechnicians = $pdfTechnicianStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $exception) {
    $pdfTechnicians = [];
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">

    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">

        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-dashboard-content dashboard-v3">

            <section class="dashboard-v3-toolbar card">
                <div class="dashboard-v3-toolbar-text">
                    <span class="dashboard-v3-kicker"><i class="fa-solid fa-gauge-high"></i> Resumen operativo</span>
                    <h2>Lectura rápida del servicio</h2>
                    <p>Vista consolidada de atención, cumplimiento SLA y desempeño técnico. Sin duplicar datos, solo lo necesario para decidir.</p>
                </div>

                <div class="dashboard-v3-toolbar-actions">
                    <div class="dashboard-v3-updated">
                        <span>Actualizado</span>
                        <strong><?= htmlspecialchars($lastUpdatedAt) ?></strong>
                    </div>

                    <button type="button" class="dashboard-v3-export-btn" id="openExportPdfModal">
                        <i class="fa-solid fa-file-pdf"></i>
                        Exportar PDF
                    </button>
                </div>
            </section>

            <div class="export-pdf-modal-backdrop" id="exportPdfModal" aria-hidden="true">
                <div class="export-pdf-modal" role="dialog" aria-modal="true" aria-labelledby="exportPdfModalTitle">
                    <form method="get" action="/helpdesk-php/export-indicators-pdf.php" target="_blank" id="exportPdfForm">
                        <button type="button" class="export-pdf-modal-close" data-export-pdf-close aria-label="Cerrar">
                            <i class="fa-solid fa-xmark"></i>
                        </button>

                        <div class="export-pdf-modal-header">
                            <span class="export-pdf-modal-icon">
                                <i class="fa-solid fa-file-pdf"></i>
                            </span>
                            <div>
                                <h3 id="exportPdfModalTitle">Exportar reporte de indicadores</h3>
                                <p>Selecciona el alcance y el periodo que deseas analizar.</p>
                            </div>
                        </div>

                        <div class="export-pdf-options">
                            <label class="export-pdf-option active" for="exportScopeAll">
                                <input type="radio" name="scope" value="all" id="exportScopeAll" checked>
                                <span>
                                    <strong>Todos los técnicos</strong>
                                    <small>Incluye el resumen general, SLA, TTA, TTR y carga de todo el equipo.</small>
                                </span>
                            </label>

                            <label class="export-pdf-option" for="exportScopeTechnician">
                                <input type="radio" name="scope" value="technician" id="exportScopeTechnician">
                                <span>
                                    <strong>Un solo técnico</strong>
                                    <small>Filtra las estadísticas únicamente por el técnico seleccionado.</small>
                                </span>
                            </label>
                        </div>

                        <div class="export-technician-select-wrap" id="exportTechnicianSelectWrap" aria-hidden="true">
                            <label for="exportTechnicianId">Técnico</label>
                            <select name="technician_id" id="exportTechnicianId" disabled>
                                <option value="">Selecciona un técnico</option>
                                <?php foreach ($pdfTechnicians as $pdfTechnician): ?>
                                    <option value="<?= (int)$pdfTechnician['id'] ?>">
                                        <?= htmlspecialchars($pdfTechnician['name'] ?? 'Técnico') ?> - Nivel <?= (int)($pdfTechnician['tech_level'] ?? 1) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($pdfTechnicians)): ?>
                                <small>No se encontraron técnicos activos para filtrar el reporte.</small>
                            <?php else: ?>
                                <small>El PDF mostrará solo tickets asignados al técnico elegido.</small>
                            <?php endif; ?>
                        </div>

                        <section class="export-period-section" aria-labelledby="exportPeriodTitle">
                            <div class="export-period-heading">
                                <div>
                                    <span class="export-period-eyebrow">Periodo del reporte</span>
                                    <strong id="exportPeriodTitle">¿Qué registros deseas incluir?</strong>
                                </div>

                                <span class="export-period-icon" aria-hidden="true">
                                    <i class="fa-regular fa-calendar"></i>
                                </span>
                            </div>

                            <div class="export-period-options">
                                <label class="export-period-option active" for="exportPeriodAll">
                                    <input
                                        type="radio"
                                        name="period_mode"
                                        value="all"
                                        id="exportPeriodAll"
                                        checked>

                                    <span>
                                        <strong>Todos los registros</strong>
                                        <small>Incluye toda la información disponible en el sistema.</small>
                                    </span>
                                </label>

                                <label class="export-period-option" for="exportPeriodRange">
                                    <input
                                        type="radio"
                                        name="period_mode"
                                        value="range"
                                        id="exportPeriodRange">

                                    <span>
                                        <strong>Entre dos fechas</strong>
                                        <small>Analiza únicamente los tickets creados dentro del rango.</small>
                                    </span>
                                </label>
                            </div>

                            <div class="export-date-range" id="exportDateRange" aria-hidden="true">
                                <div class="export-date-field">
                                    <label for="exportDateFrom">Fecha inicial</label>
                                    <input
                                        type="date"
                                        name="date_from"
                                        id="exportDateFrom"
                                        disabled>
                                </div>

                                <span class="export-date-separator" aria-hidden="true">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </span>

                                <div class="export-date-field">
                                    <label for="exportDateTo">Fecha final</label>
                                    <input
                                        type="date"
                                        name="date_to"
                                        id="exportDateTo"
                                        disabled>
                                </div>

                                <small class="export-date-help">
                                    Se incluirán los tickets desde las 00:00 de la fecha inicial
                                    hasta las 23:59 de la fecha final.
                                </small>

                                <div
                                    class="export-date-error"
                                    id="exportDateError"
                                    role="alert"
                                    hidden></div>
                            </div>
                        </section>

                        <div class="export-pdf-modal-actions">
                            <button type="button" class="export-pdf-cancel-btn" data-export-pdf-close>Cancelar</button>
                            <button type="submit" class="export-pdf-generate-btn">
                                <i class="fa-solid fa-download"></i>
                                Generar PDF
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <section class="dashboard-v3-focus-grid">
                <article class="dashboard-v3-focus-card focus-active">
                    <span class="dashboard-v3-focus-icon"><i class="fa-solid fa-headset"></i></span>
                    <div>
                        <span class="dashboard-v3-label">Tickets activos</span>
                        <strong><?= (int)$activeTickets ?></strong>
                        <small><?= htmlspecialchars($activePercent) ?> del total registrado</small>
                    </div>
                </article>

                <article class="dashboard-v3-focus-card focus-sla">
                    <span class="dashboard-v3-focus-icon"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <span class="dashboard-v3-label">SLA cumplido</span>
                        <strong><?= htmlspecialchars((string)$slaPercent) ?>%</strong>
                        <small><?= (int)$closedWithinSla ?> dentro / <?= (int)$closedOutSla ?> fuera</small>
                    </div>
                </article>

                <article class="dashboard-v3-focus-card focus-tta">
                    <span class="dashboard-v3-focus-icon"><i class="fa-solid fa-stopwatch"></i></span>
                    <div>
                        <span class="dashboard-v3-label">TTA promedio</span>
                        <strong><?= htmlspecialchars((string)$avgTTA) ?></strong>
                        <small>Tiempo hasta primera atención</small>
                    </div>
                </article>

                <article class="dashboard-v3-focus-card focus-ttr">
                    <span class="dashboard-v3-focus-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                    <div>
                        <span class="dashboard-v3-label">TTR promedio</span>
                        <strong><?= htmlspecialchars((string)$avgTTR) ?></strong>
                        <small>Tiempo promedio de resolución</small>
                    </div>
                </article>
            </section>

            <section class="dashboard-v3-status-strip card">
                <div class="dashboard-v3-status-item">
                    <span>Abiertos</span>
                    <strong><?= (int)$openTickets ?></strong>
                    <small>Sin cierre</small>
                </div>

                <div class="dashboard-v3-status-item">
                    <span>En proceso</span>
                    <strong><?= (int)$inProgressTickets ?></strong>
                    <small>Gestionados ahora</small>
                </div>

                <div class="dashboard-v3-status-item">
                    <span>Respondidos</span>
                    <strong><?= (int)$answeredTickets ?></strong>
                    <small><?= htmlspecialchars($answeredPercent) ?> del total</small>
                </div>

                <div class="dashboard-v3-status-item">
                    <span>Cerrados</span>
                    <strong><?= (int)$closedTickets ?></strong>
                    <small><?= htmlspecialchars($closedPercent) ?> del total</small>
                </div>

                <div class="dashboard-v3-status-item muted">
                    <span>Escalados</span>
                    <strong><?= (int)$escalatedTickets ?></strong>
                    <small>Cambio de nivel técnico</small>
                </div>
            </section>

            <div class="dashboard-v3-section-title">
                <span>01</span>
                <div>
                    <h3>Flujo de atención</h3>
                    <p>Estado actual de los tickets y lectura del cumplimiento SLA.</p>
                </div>
            </div>

            <section class="dashboard-v3-grid dashboard-v3-grid-2">
                <article class="dashboard-v3-panel card">
                    <div class="dashboard-v3-panel-header">
                        <div>
                            <h3>Tickets por estado</h3>
                            <p>Distribución actual del flujo operativo.</p>
                        </div>
                    </div>
                    <div class="dashboard-v3-chart-box">
                        <canvas id="ticketsStatusChart"></canvas>
                    </div>
                </article>

                <article class="dashboard-v3-panel card">
                    <div class="dashboard-v3-panel-header compact">
                        <div>
                            <h3>Detalle SLA</h3>
                            <p>Resultado de tickets cerrados frente al objetivo.</p>
                        </div>
                        <strong class="dashboard-v3-panel-number"><?= htmlspecialchars((string)$slaPercent) ?>%</strong>
                    </div>

                    <div class="dashboard-v3-sla-layout">
                        <div class="dashboard-v3-chart-box donut">
                            <canvas id="slaChart"></canvas>
                        </div>

                        <div class="dashboard-v3-sla-stats">
                            <div>
                                <span>Dentro del SLA</span>
                                <strong><?= (int)$closedWithinSla ?></strong>
                            </div>
                            <div>
                                <span>Fuera del SLA</span>
                                <strong><?= (int)$closedOutSla ?></strong>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <div class="dashboard-v3-section-title">
                <span>02</span>
                <div>
                    <h3>Tipo de incidencias</h3>
                    <p>Prioridades y categorías que concentran la atención del equipo.</p>
                </div>
            </div>

            <section class="dashboard-v3-grid dashboard-v3-grid-2">
                <article class="dashboard-v3-panel card">
                    <div class="dashboard-v3-panel-header">
                        <div>
                            <h3>Prioridad de incidencias</h3>
                            <p>Urgencia operativa predominante.</p>
                        </div>
                    </div>
                    <div class="dashboard-v3-chart-box">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </article>

                <article class="dashboard-v3-panel card">
                    <div class="dashboard-v3-panel-header">
                        <div>
                            <h3>Categorías frecuentes</h3>
                            <p>Puntos críticos detectados por tipo de soporte.</p>
                        </div>
                    </div>
                    <div class="dashboard-v3-chart-box">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </article>
            </section>

            <div class="dashboard-v3-section-title">
                <span>03</span>
                <div>
                    <h3>Carga técnica</h3>
                    <p>Distribución de tickets asignados, cerrados y escalados por responsable.</p>
                </div>
            </div>

            <section class="dashboard-v3-grid dashboard-v3-team-grid">
                <article class="dashboard-v3-panel card dashboard-v3-team-table-card">
                    <div class="dashboard-v3-panel-header">
                        <div>
                            <h3>Resumen por técnico</h3>
                            <p>Carga activa, cierres y escalamiento por responsable.</p>
                        </div>
                    </div>

                    <?php if (!empty($technicianSummary)): ?>
                        <div class="tickets-table-wrapper">
                            <table class="tickets-table admin-dashboard-table dashboard-v3-table">
                                <thead>
                                    <tr>
                                        <th>Técnico</th>
                                        <th>Nivel</th>
                                        <th>Activos</th>
                                        <th>Cerrados</th>
                                        <th>Escalados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($technicianSummary as $technician): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($technician['name'] ?? 'Sin nombre') ?></strong>
                                            </td>
                                            <td><span class="metric-pill neutral-pill">N<?= (int)($technician['tech_level'] ?? 1) ?></span></td>
                                            <td><?= (int)($technician['active_tickets'] ?? 0) ?></td>
                                            <td><?= (int)($technician['closed_tickets'] ?? 0) ?></td>
                                            <td><?= (int)($technician['escalated_tickets'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-ticket-box">
                            <h4>Sin técnicos registrados</h4>
                            <p>Cuando existan técnicos activos, aparecerá el resumen de carga laboral.</p>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="dashboard-v3-panel card">
                    <div class="dashboard-v3-panel-header">
                        <div>
                            <h3>Carga por técnico</h3>
                            <p>Tickets asignados actualmente al equipo.</p>
                        </div>
                    </div>
                    <div class="dashboard-v3-chart-box compact-chart">
                        <canvas id="technicianChart"></canvas>
                    </div>
                </article>

                <article class="dashboard-v3-panel card">
                    <div class="dashboard-v3-panel-header">
                        <div>
                            <h3>Resumen por nivel</h3>
                            <p>Lectura rápida por nivel de soporte.</p>
                        </div>
                    </div>

                    <div class="dashboard-v3-level-grid">
                        <?php foreach ($levelSummary as $level): ?>
                            <div class="dashboard-v3-level-card">
                                <span>Nivel <?= (int)$level['level'] ?></span>
                                <strong><?= (int)$level['current_total'] ?></strong>
                                <small>actuales</small>
                                <div>
                                    <em><?= (int)$level['active_total'] ?> activos</em>
                                    <em><?= (int)$level['closed_total'] ?> cerrados</em>
                                    <em><?= (int)$level['escalated_total'] ?> escalados</em>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </section>

            <div class="dashboard-v3-section-title">
                <span>04</span>
                <div>
                    <h3>Últimos registros</h3>
                    <p>Incidencias recientes para seguimiento operativo inmediato.</p>
                </div>
            </div>

            <section class="card dashboard-v3-panel dashboard-v3-recent-card">
                <?php if (!empty($recentTickets)): ?>
                    <div class="tickets-table-wrapper">
                        <table class="tickets-table admin-dashboard-table dashboard-v3-table dashboard-v3-recent-table">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th>Categoría</th>
                                    <th>Técnico</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= (int)$ticket['id'] ?></strong>
                                            <span><?= htmlspecialchars($ticket['subject'] ?? 'Sin asunto') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars(dashboardStatusLabel($ticket['status'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars($ticket['priority'] ?? 'Sin prioridad') ?></td>
                                        <td><?= htmlspecialchars($ticket['category'] ?? 'Sin categoría') ?></td>
                                        <td><?= htmlspecialchars($ticket['technician_name'] ?? 'Sin asignar') ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['created_at'] ?? 'now'))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-ticket-box">
                        <h4>Aún no hay tickets</h4>
                        <p>Cuando se registren incidencias, aparecerán en esta sección.</p>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const dashboardPalette = {
        green: '#0f3d2e',
        greenSoft: '#1f7a5a',
        greenLight: '#dff3e8',
        orange: '#ff7a00',
        orangeSoft: '#ffb36b',
        amber: '#d97706',
        slate: '#475569',
        muted: '#94a3b8',
        line: '#e7edf4'
    };

    const statusLabels = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>;
    const statusValues = <?= json_encode($statusValues, JSON_UNESCAPED_UNICODE) ?>;

    const slaLabels = <?= json_encode($slaLabels, JSON_UNESCAPED_UNICODE) ?>;
    const slaValues = <?= json_encode($slaValues, JSON_UNESCAPED_UNICODE) ?>;

    const priorityLabels = <?= json_encode($priorityLabels, JSON_UNESCAPED_UNICODE) ?>;
    const priorityValues = <?= json_encode($priorityValues, JSON_UNESCAPED_UNICODE) ?>;

    const categoryLabels = <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE) ?>;
    const categoryValues = <?= json_encode($categoryValues, JSON_UNESCAPED_UNICODE) ?>;

    const technicianLabels = <?= json_encode($technicianLabels, JSON_UNESCAPED_UNICODE) ?>;
    const technicianValues = <?= json_encode($technicianValues, JSON_UNESCAPED_UNICODE) ?>;

    const levelLabels = <?= json_encode($levelLabels, JSON_UNESCAPED_UNICODE) ?>;
    const levelValues = <?= json_encode($levelValues, JSON_UNESCAPED_UNICODE) ?>;

    const dashboardColors = [
        dashboardPalette.greenSoft,
        dashboardPalette.orange,
        dashboardPalette.green,
        dashboardPalette.amber,
        dashboardPalette.slate,
        '#6b8f71',
        '#a16207'
    ];

    const chartDefaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 650,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 10,
                    usePointStyle: true,
                    color: dashboardPalette.slate,
                    font: {
                        size: 11,
                        weight: '700'
                    }
                }
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(148, 163, 184, 0.18)'
                },
                ticks: {
                    color: dashboardPalette.slate,
                    precision: 0
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(148, 163, 184, 0.18)'
                },
                ticks: {
                    color: dashboardPalette.slate,
                    precision: 0
                }
            }
        }
    };

    function createBarChart(canvasId, labels, values, label, horizontal = false) {
        const canvas = document.getElementById(canvasId);

        if (!canvas || labels.length === 0) {
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label,
                    data: values,
                    backgroundColor: labels.map((_, index) => dashboardColors[index % dashboardColors.length]),
                    borderRadius: 10,
                    maxBarThickness: 34
                }]
            },
            options: {
                ...chartDefaultOptions,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    createBarChart('ticketsStatusChart', statusLabels, statusValues, 'Tickets');
    createBarChart('priorityChart', priorityLabels, priorityValues, 'Tickets', true);
    createBarChart('categoryChart', categoryLabels, categoryValues, 'Tickets');
    createBarChart('technicianChart', technicianLabels, technicianValues, 'Tickets asignados', true);
    createBarChart('levelChart', levelLabels, levelValues, 'Tickets por nivel');

    const slaCanvas = document.getElementById('slaChart');

    if (slaCanvas) {
        new Chart(slaCanvas, {
            type: 'doughnut',
            data: {
                labels: slaLabels,
                datasets: [{
                    data: slaValues,
                    backgroundColor: [dashboardPalette.greenSoft, dashboardPalette.orange],
                    borderWidth: 0
                }]
            },
            options: {
                ...chartDefaultOptions,
                cutout: '70%',
                plugins: {
                    ...chartDefaultOptions.plugins,
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            usePointStyle: true,
                            color: dashboardPalette.slate,
                            font: {
                                size: 11,
                                weight: '700'
                            }
                        }
                    }
                }
            }
        });
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const openButton = document.getElementById('openExportPdfModal');
        const modal = document.getElementById('exportPdfModal');
        const form = document.getElementById('exportPdfForm');
        const closeButtons = document.querySelectorAll('[data-export-pdf-close]');
        const scopeInputs = document.querySelectorAll('input[name="scope"]');
        const technicianWrap = document.getElementById('exportTechnicianSelectWrap');
        const technicianSelect = document.getElementById('exportTechnicianId');
        const optionCards = document.querySelectorAll('.export-pdf-option');

        const periodInputs = document.querySelectorAll('input[name="period_mode"]');
        const periodCards = document.querySelectorAll('.export-period-option');
        const dateRange = document.getElementById('exportDateRange');
        const dateFromInput = document.getElementById('exportDateFrom');
        const dateToInput = document.getElementById('exportDateTo');
        const dateError = document.getElementById('exportDateError');

        if (!openButton || !modal || !form) {
            return;
        }

        const formatDateForInput = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        };

        const setDefaultDateRange = () => {
            if (!dateFromInput || !dateToInput) {
                return;
            }

            const today = new Date();
            const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const todayValue = formatDateForInput(today);

            dateFromInput.max = todayValue;
            dateToInput.max = todayValue;

            if (dateFromInput.value === '') {
                dateFromInput.value = formatDateForInput(firstDayOfMonth);
            }

            if (dateToInput.value === '') {
                dateToInput.value = todayValue;
            }
        };

        const clearDateError = () => {
            if (dateError) {
                dateError.hidden = true;
                dateError.textContent = '';
            }

            dateFromInput?.classList.remove('has-error');
            dateToInput?.classList.remove('has-error');
        };

        const showDateError = (message) => {
            if (!dateError) {
                return;
            }

            dateError.hidden = false;
            dateError.textContent = message;
        };

        const openModal = () => {
            setDefaultDateRange();
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        };

        const closeModal = () => {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        };

        const updateScopeState = () => {
            const selectedScope = document.querySelector('input[name="scope"]:checked')?.value || 'all';
            const isTechnicianScope = selectedScope === 'technician';

            technicianWrap?.classList.toggle('show', isTechnicianScope);
            technicianWrap?.setAttribute('aria-hidden', isTechnicianScope ? 'false' : 'true');

            if (technicianSelect) {
                technicianSelect.disabled = !isTechnicianScope;

                if (!isTechnicianScope) {
                    technicianSelect.value = '';
                }
            }

            optionCards.forEach((card) => {
                const input = card.querySelector('input[name="scope"]');
                card.classList.toggle('active', input?.checked === true);
            });
        };

        const updatePeriodState = () => {
            const selectedPeriod = document.querySelector('input[name="period_mode"]:checked')?.value || 'all';
            const isRange = selectedPeriod === 'range';

            dateRange?.classList.toggle('show', isRange);
            dateRange?.setAttribute('aria-hidden', isRange ? 'false' : 'true');

            if (dateFromInput && dateToInput) {
                dateFromInput.disabled = !isRange;
                dateToInput.disabled = !isRange;

                if (isRange) {
                    setDefaultDateRange();
                }
            }

            periodCards.forEach((card) => {
                const input = card.querySelector('input[name="period_mode"]');
                card.classList.toggle('active', input?.checked === true);
            });

            clearDateError();
        };

        openButton.addEventListener('click', openModal);

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                closeModal();
            }
        });

        scopeInputs.forEach((input) => {
            input.addEventListener('change', updateScopeState);
        });

        periodInputs.forEach((input) => {
            input.addEventListener('change', updatePeriodState);
        });

        form.addEventListener('submit', (event) => {
            const selectedScope = document.querySelector('input[name="scope"]:checked')?.value || 'all';

            if (selectedScope === 'technician' && technicianSelect && technicianSelect.value === '') {
                event.preventDefault();
                technicianSelect.focus();
                technicianSelect.classList.add('has-error');
                return;
            }

            const selectedPeriod = document.querySelector('input[name="period_mode"]:checked')?.value || 'all';

            if (selectedPeriod === 'range') {
                clearDateError();

                const dateFrom = dateFromInput?.value || '';
                const dateTo = dateToInput?.value || '';

                if (dateFrom === '' || dateTo === '') {
                    event.preventDefault();

                    if (dateFrom === '') {
                        dateFromInput?.classList.add('has-error');
                    }

                    if (dateTo === '') {
                        dateToInput?.classList.add('has-error');
                    }

                    showDateError('Selecciona la fecha inicial y la fecha final.');
                    (dateFrom === '' ? dateFromInput : dateToInput)?.focus();
                    return;
                }

                if (dateFrom > dateTo) {
                    event.preventDefault();
                    dateFromInput?.classList.add('has-error');
                    dateToInput?.classList.add('has-error');
                    showDateError('La fecha inicial no puede ser posterior a la fecha final.');
                    dateFromInput?.focus();
                    return;
                }
            }

            closeModal();
        });

        technicianSelect?.addEventListener('change', () => {
            technicianSelect.classList.remove('has-error');
        });

        [dateFromInput, dateToInput].forEach((input) => {
            input?.addEventListener('change', clearDateError);
        });

        updateScopeState();
        updatePeriodState();
    });
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
