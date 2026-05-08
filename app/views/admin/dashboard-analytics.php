<?php
$title = 'Dashboard';

$activePage = 'reports';
$pageTitle = 'Dashboard';
$pageSubtitle = 'Visualiza indicadores de tickets, tiempos de atención y cumplimiento SLA.';

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
$closedTickets = $closedTickets ?? 0;

$avgTTA = $avgTTA ?? 0;
$avgTTR = $avgTTR ?? 0;
$slaPercent = $slaPercent ?? 0;

/*
|--------------------------------------------------------------------------
| Datos para gráficos
|--------------------------------------------------------------------------
| Estas variables deben venir desde admin-dashboard.php.
| Se dejan valores vacíos para evitar errores si todavía no existen.
*/
$ticketsByPriority = $ticketsByPriority ?? [];
$ticketsByCategory = $ticketsByCategory ?? [];
$ticketsByTechnician = $ticketsByTechnician ?? [];

function chartLabels(array $rows, string $labelKey): array
{
    return array_map(static fn($row) => (string)($row[$labelKey] ?? 'Sin dato'), $rows);
}

function chartValues(array $rows, string $valueKey): array
{
    return array_map(static fn($row) => (int)($row[$valueKey] ?? 0), $rows);
}

$priorityLabels = chartLabels($ticketsByPriority, 'priority');
$priorityValues = chartValues($ticketsByPriority, 'total');

$categoryLabels = chartLabels($ticketsByCategory, 'category');
$categoryValues = chartValues($ticketsByCategory, 'total');

$technicianLabels = chartLabels($ticketsByTechnician, 'technician_name');
$technicianValues = chartValues($ticketsByTechnician, 'total');

$slaNotMetPercent = max(0, 100 - (float)$slaPercent);

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">

    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">

        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content">

            <section class="admin-kpi-grid admin-kpi-grid-4">
                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Total tickets</span>
                    <strong class="admin-kpi-value"><?= (int)$totalTickets ?></strong>
                    <p>Total de incidencias registradas.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">TTA promedio</span>
                    <strong class="admin-kpi-value"><?= htmlspecialchars((string)$avgTTA) ?> h</strong>
                    <p>Tiempo promedio de primera atención.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">TTR promedio</span>
                    <strong class="admin-kpi-value"><?= htmlspecialchars((string)$avgTTR) ?> h</strong>
                    <p>Tiempo promedio de resolución.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">% SLA cumplido</span>
                    <strong class="admin-kpi-value"><?= htmlspecialchars((string)$slaPercent) ?>%</strong>
                    <p>Cumplimiento de tickets cerrados.</p>
                </div>
            </section>

            <section class="admin-panel-grid">
                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Tickets por estado</h2>
                        <p>Distribución actual de incidencias registradas.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="ticketsStatusChart"></canvas>
                    </div>
                </div>

                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Cumplimiento SLA</h2>
                        <p>Porcentaje de tickets cerrados dentro del tiempo objetivo.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="slaChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="admin-panel-grid">
                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Tickets por prioridad</h2>
                        <p>Permite identificar la carga operativa según nivel de urgencia.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </div>

                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Tickets por categoría</h2>
                        <p>Ayuda a reconocer los tipos de incidencias más frecuentes.</p>
                    </div>

                    <div class="admin-chart-box">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="card admin-panel-card">
                <div class="admin-panel-card-header">
                    <h2>Tickets por técnico</h2>
                    <p>Vista de carga asignada para apoyar la distribución del trabajo.</p>
                </div>

                <?php if (!empty($ticketsByTechnician)): ?>
                    <div class="admin-dashboard-technician-grid">
                        <div class="admin-chart-box">
                            <canvas id="technicianChart"></canvas>
                        </div>

                        <div class="tickets-table-wrapper">
                            <table class="tickets-table admin-dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Técnico</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketsByTechnician as $technician): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($technician['technician_name'] ?? 'Sin asignar') ?></td>
                                            <td>
                                                <span class="metric-pill neutral-pill">
                                                    <?= (int)($technician['total'] ?? 0) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-ticket-box">
                        <h4>Aún no hay datos por técnico</h4>
                        <p>Cuando existan tickets asignados, aparecerá aquí la carga de trabajo por técnico.</p>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const openTickets = <?= (int)$openTickets ?>;
    const inProgressTickets = <?= (int)$inProgressTickets ?>;
    const closedTickets = <?= (int)$closedTickets ?>;
    const slaPercent = <?= (float)$slaPercent ?>;
    const slaNotMetPercent = <?= (float)$slaNotMetPercent ?>;

    const priorityLabels = <?= json_encode($priorityLabels, JSON_UNESCAPED_UNICODE) ?>;
    const priorityValues = <?= json_encode($priorityValues, JSON_UNESCAPED_UNICODE) ?>;

    const categoryLabels = <?= json_encode($categoryLabels, JSON_UNESCAPED_UNICODE) ?>;
    const categoryValues = <?= json_encode($categoryValues, JSON_UNESCAPED_UNICODE) ?>;

    const technicianLabels = <?= json_encode($technicianLabels, JSON_UNESCAPED_UNICODE) ?>;
    const technicianValues = <?= json_encode($technicianValues, JSON_UNESCAPED_UNICODE) ?>;

    const chartDefaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    };

    new Chart(document.getElementById('ticketsStatusChart'), {
        type: 'bar',
        data: {
            labels: ['Abiertos', 'En proceso', 'Cerrados'],
            datasets: [{
                label: 'Tickets',
                data: [openTickets, inProgressTickets, closedTickets]
            }]
        },
        options: {
            ...chartDefaultOptions,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    new Chart(document.getElementById('slaChart'), {
        type: 'doughnut',
        data: {
            labels: ['Cumplido', 'No cumplido'],
            datasets: [{
                data: [slaPercent, slaNotMetPercent]
            }]
        },
        options: chartDefaultOptions
    });

    new Chart(document.getElementById('priorityChart'), {
        type: 'bar',
        data: {
            labels: priorityLabels,
            datasets: [{
                label: 'Tickets',
                data: priorityValues
            }]
        },
        options: {
            ...chartDefaultOptions,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: categoryLabels,
            datasets: [{
                label: 'Tickets',
                data: categoryValues
            }]
        },
        options: {
            ...chartDefaultOptions,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    const technicianCanvas = document.getElementById('technicianChart');
    if (technicianCanvas && technicianLabels.length > 0) {
        new Chart(technicianCanvas, {
            type: 'bar',
            data: {
                labels: technicianLabels,
                datasets: [{
                    label: 'Tickets asignados',
                    data: technicianValues
                }]
            },
            options: {
                ...chartDefaultOptions,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
