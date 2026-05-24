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

$slaLabels = ['Cumplido', 'No cumplido'];
$slaValues = [(int)$closedWithinSla, (int)$closedOutSla];

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">

    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">

        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-dashboard-content">

            <section class="admin-dashboard-hero card">
                <div>
                    <span class="admin-dashboard-eyebrow">Panel gerencial</span>
                    <h2>Resumen operativo del mantenimiento correctivo</h2>
                    <p>
                        Monitorea la carga de tickets, el cumplimiento SLA, los tiempos TTA/TTR y el comportamiento por nivel técnico.
                    </p>
                </div>

                <div class="admin-dashboard-hero-metrics">
                    <div>
                        <strong><?= (int)$activeTickets ?></strong>
                        <span>Tickets activos</span>
                    </div>
                    <div>
                        <strong><?= (int)$escalatedTickets ?></strong>
                        <span>Escalados</span>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars((string)$slaPercent) ?>%</strong>
                        <span>SLA cumplido</span>
                    </div>
                </div>
            </section>

            <section class="admin-kpi-grid admin-kpi-grid-8">
                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Total tickets</span>
                    <strong class="admin-kpi-value"><?= (int)$totalTickets ?></strong>
                    <p>Incidencias registradas en el sistema.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Abiertos</span>
                    <strong class="admin-kpi-value"><?= (int)$openTickets ?></strong>
                    <p>Tickets aún sin atención final.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">En proceso</span>
                    <strong class="admin-kpi-value"><?= (int)$inProgressTickets ?></strong>
                    <p>Casos actualmente gestionados.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Cerrados</span>
                    <strong class="admin-kpi-value"><?= (int)$closedTickets ?></strong>
                    <p>Incidencias finalizadas.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">TTA promedio</span>
                    <strong class="admin-kpi-value admin-kpi-time"><?= htmlspecialchars((string)$avgTTA) ?></strong>
                    <p>Primera atención en horario laboral.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">TTR promedio</span>
                    <strong class="admin-kpi-value admin-kpi-time"><?= htmlspecialchars((string)$avgTTR) ?></strong>
                    <p>Resolución en horario laboral.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">% SLA cumplido</span>
                    <strong class="admin-kpi-value"><?= htmlspecialchars((string)$slaPercent) ?>%</strong>
                    <p>Tickets cerrados dentro del objetivo.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Escalados</span>
                    <strong class="admin-kpi-value"><?= (int)$escalatedTickets ?></strong>
                    <p>Tickets que pasaron de nivel técnico.</p>
                </div>
            </section>

            <section class="admin-panel-grid">
                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Tickets por estado</h2>
                        <p>Distribución actual del flujo de atención.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="ticketsStatusChart"></canvas>
                    </div>
                </div>

                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Cumplimiento SLA</h2>
                        <p>Tickets cerrados dentro y fuera del tiempo objetivo.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="slaChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="admin-panel-grid">
                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Prioridad de incidencias</h2>
                        <p>Permite reconocer la urgencia operativa predominante.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </div>

                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Categorías más frecuentes</h2>
                        <p>Ayuda a identificar puntos críticos del soporte técnico.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="admin-panel-grid">
                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Tickets por nivel técnico</h2>
                        <p>Seguimiento de la atención por nivel de soporte.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="levelChart"></canvas>
                    </div>
                </div>

                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Carga por técnico</h2>
                        <p>Distribución de tickets asignados al equipo.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="technicianChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="admin-panel-grid admin-panel-grid-wide">
                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Resumen por técnico</h2>
                        <p>Carga activa, tickets cerrados y casos escalados por responsable.</p>
                    </div>

                    <?php if (!empty($technicianSummary)): ?>
                        <div class="tickets-table-wrapper">
                            <table class="tickets-table admin-dashboard-table">
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
                                            <td><?= htmlspecialchars($technician['name'] ?? 'Sin nombre') ?></td>
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
                </div>

                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Resumen por nivel</h2>
                        <p>Vista rápida del comportamiento por nivel técnico.</p>
                    </div>

                    <div class="admin-level-summary">
                        <?php foreach ($levelSummary as $level): ?>
                            <div class="admin-level-card">
                                <span>Nivel <?= (int)$level['level'] ?></span>
                                <strong><?= (int)$level['current_total'] ?></strong>
                                <small>Actuales</small>

                                <div class="admin-level-meta">
                                    <div>
                                        <b><?= (int)$level['active_total'] ?></b>
                                        <em>Activos</em>
                                    </div>
                                    <div>
                                        <b><?= (int)$level['closed_total'] ?></b>
                                        <em>Cerrados</em>
                                    </div>
                                    <div>
                                        <b><?= (int)$level['escalated_total'] ?></b>
                                        <em>Escalados</em>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card admin-panel-card">
                <div class="admin-panel-card-header">
                    <h2>Últimos tickets registrados</h2>
                    <p>Seguimiento rápido de las incidencias más recientes.</p>
                </div>

                <?php if (!empty($recentTickets)): ?>
                    <div class="tickets-table-wrapper">
                        <table class="tickets-table admin-dashboard-table">
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
        orange: '#ff7a00',
        orangeSoft: '#ffb36b',
        slate: '#334155',
        muted: '#94a3b8',
        blue: '#2563eb',
        red: '#dc2626'
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
                    boxWidth: 12,
                    usePointStyle: true
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
                    backgroundColor: dashboardPalette.greenSoft,
                    borderRadius: 8,
                    maxBarThickness: 38
                }]
            },
            options: {
                ...chartDefaultOptions,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            precision: 0
                        }
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
                    backgroundColor: [dashboardPalette.greenSoft, dashboardPalette.red],
                    borderWidth: 0
                }]
            },
            options: {
                ...chartDefaultOptions,
                cutout: '68%'
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
