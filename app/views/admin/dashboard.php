<?php
require_once __DIR__ . '/../../helpers/session.php';
requireRole('ADMIN');

$title = 'Panel del Administrador';

/*
|--------------------------------------------------------------------------
| Variables para layouts admin
|--------------------------------------------------------------------------
| Estas variables las pueden usar:
| - admin-sidebar.php para marcar el menú activo
| - admin-topbar.php para mostrar título/subtítulo
*/
$activePage = 'dashboard';
$pageTitle = 'Panel del Administrador';
$pageSubtitle = 'Monitorea la gestión del mantenimiento correctivo con indicadores operativos del sistema.';

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">

    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">

        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content">
            <!-- KPI PRINCIPALES -->
            <section class="admin-kpi-grid admin-kpi-grid-6">
                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Tickets abiertos</span>
                    <strong class="admin-kpi-value"><?= $openTickets ?></strong>
                    <p>Incidencias pendientes de atención o seguimiento inicial.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Tickets en proceso</span>
                    <strong class="admin-kpi-value"><?= $inProgressTickets ?></strong>
                    <p>Casos que están siendo trabajados actualmente.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Tickets cerrados</span>
                    <strong class="admin-kpi-value"><?= $closedTickets ?></strong>
                    <p>Incidencias finalizadas dentro del sistema.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">Calificación promedio</span>
                    <strong class="admin-kpi-value"><?= $avgRating ?></strong>
                    <p>Promedio de satisfacción registrada por los clientes.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">TTA promedio (h)</span>
                    <strong class="admin-kpi-value"><?= $avgTTA ?></strong>
                    <p>Tiempo promedio desde la apertura hasta la primera atención.</p>
                </div>

                <div class="admin-kpi-card">
                    <span class="admin-kpi-label">% SLA cumplido</span>
                    <strong class="admin-kpi-value"><?= $slaPercent ?>%</strong>
                    <p>Porcentaje de tickets cerrados dentro del tiempo objetivo.</p>
                </div>
            </section>

            <!-- SEGUNDA FILA -->
            <section class="admin-panel-grid">
                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Gestión rápida</h2>
                        <p>Accesos importantes para la administración del sistema.</p>
                    </div>

                    <div class="admin-quick-links-grid">
                        <a href="/helpdesk-php/admin-tickets.php" class="admin-quick-card">
                            <div class="admin-quick-card-icon"><i class="fa-solid fa-ticket"></i></div>
                            <strong>Gestionar tickets</strong>
                            <span>Ver, asignar y actualizar tickets del sistema.</span>
                        </a>

                        <a href="/helpdesk-php/admin-users.php" class="admin-quick-card">
                            <div class="admin-quick-card-icon"><i class="fa-solid fa-users"></i></div>
                            <strong>Usuarios</strong>
                            <span>Administrar clientes, técnicos y administradores.</span>
                        </a>

                        <a href="#" class="admin-quick-card">
                            <div class="admin-quick-card-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <strong>Dashboard</strong>
                            <span>Explorar indicadores y reportes del sistema.</span>
                        </a>

                        <a href="#" class="admin-quick-card">
                            <div class="admin-quick-card-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                            <strong>Herramientas</strong>
                            <span>Respuestas rápidas y utilidades administrativas.</span>
                        </a>
                    </div>
                </div>

                <div class="card admin-panel-card">
                    <div class="admin-panel-card-header">
                        <h2>Indicadores clave</h2>
                        <p>Resumen de tiempos y cumplimiento para el mantenimiento correctivo.</p>
                    </div>

                    <div class="admin-dashboard-placeholder">
                        <div class="admin-chart-box">
                            <canvas id="ticketsStatusChart"></canvas>
                        </div>

                        <div class="admin-chart-box">
                            <canvas id="slaChart"></canvas>
                        </div>

                        <div class="admin-summary-list">
                            <div class="admin-summary-item">
                                <strong><?= $avgTTA ?> h</strong>
                                <span>Tiempo promedio de primera atención</span>
                            </div>

                            <div class="admin-summary-item">
                                <strong><?= $avgTTR ?> h</strong>
                                <span>Tiempo promedio de resolución</span>
                            </div>

                            <div class="admin-summary-item">
                                <strong><?= $slaPercent ?>%</strong>
                                <span>Cumplimiento promedio de SLA</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ESPACIO INFERIOR -->
            <section class="card admin-panel-card">
                <div class="admin-panel-card-header">
                    <h2>Lectura operativa del sistema</h2>
                    <p>Este panel resume el comportamiento de la atención de incidencias del sistema.</p>
                </div>

                <div class="admin-workspace-box">
                    <p>
                        Con estos indicadores puedes evaluar la eficiencia de atención, la capacidad de resolución y el cumplimiento
                        del nivel de servicio. Luego puedes ampliar esta sección con métricas por prioridad, técnico, categoría
                        o tendencias mensuales.
                    </p>
                </div>
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

    new Chart(document.getElementById('ticketsStatusChart'), {
        type: 'bar',
        data: {
            labels: ['Abiertos', 'En proceso', 'Cerrados'],
            datasets: [{
                label: 'Cantidad de tickets',
                data: [openTickets, inProgressTickets, closedTickets]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
                data: [slaPercent, 100 - slaPercent]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>