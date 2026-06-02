<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';

if ($currentRole !== 'ADMIN') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$title = 'Panel operativo';
$activePage = 'dashboard';
$pageTitle = 'Panel operativo';
$pageSubtitle = 'Vista general de la atención de tickets, cumplimiento SLA y evolución mensual del servicio.';

function dashboardCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)($stmt->fetchColumn() ?: 0);
}

function dashboardMetric(PDO $pdo, string $sql, array $params = []): ?float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null ? (float)$value : null;
}

function formatMinutesAsHours(?float $minutes): string
{
    if ($minutes === null || $minutes <= 0) {
        return '0 h';
    }

    $hours = $minutes / 60;

    if ($hours < 1) {
        return number_format($minutes, 0) . ' min';
    }

    return number_format($hours, 1) . ' h';
}

function spanishMonthLabel(string $yearMonth): string
{
    $months = [
        '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
        '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
        '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
    ];

    [$year, $month] = explode('-', $yearMonth);
    return ($months[$month] ?? $month) . ' ' . $year;
}

$totalTickets = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets");
$openTickets = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE status = 'ABIERTO'");
$inProgressTickets = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE status = 'EN_PROCESO'");
$answeredTickets = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE status = 'RESPONDIDO'");
$closedTickets = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE status = 'CERRADO'");
$activeTickets = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO')");
$escalatedTickets = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE support_level >= 2");

$closedWithinSla = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE closed_at IS NOT NULL AND sla_met = 1");
$closedOutSla = dashboardCount($pdo, "SELECT COUNT(*) FROM tickets WHERE closed_at IS NOT NULL AND sla_met = 0");
$closedForSla = $closedWithinSla + $closedOutSla;
$slaPercent = $closedForSla > 0 ? round(($closedWithinSla / $closedForSla) * 100, 1) : 0;

$avgTTAMinutes = dashboardMetric($pdo, "
    SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at))
    FROM tickets
    WHERE first_response_at IS NOT NULL
");

$avgTTRMinutes = dashboardMetric($pdo, "
    SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at))
    FROM tickets
    WHERE closed_at IS NOT NULL
");

$avgTTA = formatMinutesAsHours($avgTTAMinutes);
$avgTTR = formatMinutesAsHours($avgTTRMinutes);

$statusLabels = ['Abiertos', 'En proceso', 'Respondidos', 'Cerrados'];
$statusValues = [$openTickets, $inProgressTickets, $answeredTickets, $closedTickets];
$slaLabels = ['Cumplido', 'No cumplido'];
$slaValues = [$closedWithinSla, $closedOutSla];

$stmtPriority = $pdo->query("SELECT COALESCE(priority, 'SIN PRIORIDAD') AS label, COUNT(*) AS total FROM tickets GROUP BY priority ORDER BY total DESC");
$priorityRows = $stmtPriority->fetchAll(PDO::FETCH_ASSOC) ?: [];
$priorityLabels = array_map(static fn($row) => ucfirst(strtolower((string)$row['label'])), $priorityRows);
$priorityValues = array_map(static fn($row) => (int)$row['total'], $priorityRows);

$stmtMonthly = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM tickets
    WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_key ASC
");
$monthlyRows = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC) ?: [];
$monthlyMap = [];
foreach ($monthlyRows as $row) {
    $monthlyMap[(string)$row['month_key']] = (int)$row['total'];
}

$monthlyLabels = [];
$monthlyValues = [];
$monthCursor = new DateTime('first day of this month');
$monthCursor->modify('-11 months');

for ($i = 0; $i < 12; $i++) {
    $key = $monthCursor->format('Y-m');
    $monthlyLabels[] = spanishMonthLabel($key);
    $monthlyValues[] = $monthlyMap[$key] ?? 0;
    $monthCursor->modify('+1 month');
}

$maxMonthly = !empty($monthlyValues) ? max($monthlyValues) : 0;
$maxMonthlyIndex = $maxMonthly > 0 ? array_search($maxMonthly, $monthlyValues, true) : null;
$maxMonthlyLabel = $maxMonthlyIndex !== false && $maxMonthlyIndex !== null ? $monthlyLabels[$maxMonthlyIndex] : 'Sin datos';

$stmtRecent = $pdo->query("
    SELECT
        t.id,
        t.subject,
        t.status,
        t.priority,
        t.category,
        t.created_at,
        u.name AS requester_name,
        a.name AS assigned_name
    FROM tickets t
    INNER JOIN users u ON u.id = t.requester_id
    LEFT JOIN users a ON a.id = t.assigned_to
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recentTickets = $stmtRecent->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/app/views/layouts/header.php';
?>

<div class="admin-shell">
    <?php require_once __DIR__ . '/app/views/layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/app/views/layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-dashboard-content admin-home-panel">
            <section class="admin-dashboard-hero admin-home-hero">
                <div>
                    <span class="admin-dashboard-eyebrow">Centro de control</span>
                    <h2>Estado operativo del soporte técnico</h2>
                    <p>
                        Monitorea la atención de incidencias, el cumplimiento de SLA y la evolución mensual de tickets desde una sola vista.
                    </p>
                </div>

                <div class="admin-dashboard-hero-metrics">
                    <div>
                        <strong><?= (int)$activeTickets ?></strong>
                        <span>Activos</span>
                    </div>
                    <div>
                        <strong><?= (int)$closedTickets ?></strong>
                        <span>Cerrados</span>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars((string)$slaPercent, ENT_QUOTES, 'UTF-8') ?>%</strong>
                        <span>SLA cumplido</span>
                    </div>
                </div>
            </section>

            <section class="admin-kpi-grid admin-kpi-grid-8 admin-home-kpis">
                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">Total tickets</span>
                    <strong class="admin-kpi-value"><?= (int)$totalTickets ?></strong>
                    <p>Incidencias registradas en el sistema.</p>
                </article>
                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">Abiertos</span>
                    <strong class="admin-kpi-value"><?= (int)$openTickets ?></strong>
                    <p>Pendientes de primera atención.</p>
                </article>
                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">En proceso</span>
                    <strong class="admin-kpi-value"><?= (int)$inProgressTickets ?></strong>
                    <p>Casos actualmente gestionados.</p>
                </article>
                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">TTA promedio</span>
                    <strong class="admin-kpi-value admin-kpi-time"><?= htmlspecialchars($avgTTA, ENT_QUOTES, 'UTF-8') ?></strong>
                    <p>Primera respuesta registrada.</p>
                </article>
            </section>

            <section class="admin-panel-grid admin-home-main-grid">
                <article class="admin-panel-card card admin-home-actions-card">
                    <div class="admin-panel-card-header">
                        <h2>Accesos operativos</h2>
                        <p>Atajos para administrar los procesos más usados del helpdesk.</p>
                    </div>

                    <div class="admin-home-actions-grid">
                        <a class="admin-home-action" href="/helpdesk-php/admin-tickets.php">
                            <span><i class="fa-solid fa-ticket"></i></span>
                            <strong>Gestionar tickets</strong>
                            <small>Asignar, filtrar y actualizar incidencias.</small>
                        </a>

                        <a class="admin-home-action" href="/helpdesk-php/admin-users.php">
                            <span><i class="fa-solid fa-users"></i></span>
                            <strong>Usuarios</strong>
                            <small>Administrar clientes, técnicos y permisos.</small>
                        </a>

                        <a class="admin-home-action" href="/helpdesk-php/admin-dashboard.php">
                            <span><i class="fa-solid fa-chart-line"></i></span>
                            <strong>Dashboard avanzado</strong>
                            <small>Ver indicadores por técnico, nivel y SLA.</small>
                        </a>

                        <a class="admin-home-action" href="/helpdesk-php/admin-tickets.php?sla_status=NO_CUMPLIDO">
                            <span><i class="fa-solid fa-triangle-exclamation"></i></span>
                            <strong>Tickets fuera de SLA</strong>
                            <small>Revisar incidencias fuera del objetivo.</small>
                        </a>
                    </div>
                </article>

                <article class="admin-panel-card card">
                    <div class="admin-panel-card-header">
                        <h2>Resumen visual</h2>
                        <p>Distribución animada del estado actual y cumplimiento SLA.</p>
                    </div>

                    <div class="admin-home-mini-charts">
                        <div class="admin-chart-box admin-home-chart-small">
                            <canvas id="homeStatusChart"></canvas>
                        </div>
                        <div class="admin-chart-box admin-home-chart-small">
                            <canvas id="homeSlaChart"></canvas>
                        </div>
                    </div>

                    <div class="admin-home-metric-list">
                        <p><strong><?= htmlspecialchars($avgTTR, ENT_QUOTES, 'UTF-8') ?></strong> tiempo promedio de resolución.</p>
                        <p><strong><?= (int)$escalatedTickets ?></strong> tickets escalados a niveles superiores.</p>
                    </div>
                </article>
            </section>

            <section class="admin-panel-card card admin-home-timeline-card">
                <div class="admin-panel-card-header admin-home-chart-header">
                    <div>
                        <h2>Tendencia mensual de tickets</h2>
                        <p>Línea de tiempo de los últimos 12 meses para identificar temporadas con mayor demanda de soporte.</p>
                    </div>
                    <span class="admin-home-peak-pill">
                        Mes pico: <strong><?= htmlspecialchars($maxMonthlyLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                </div>

                <div class="admin-chart-box admin-home-line-chart">
                    <canvas id="monthlyTicketsChart"></canvas>
                </div>
            </section>

            <section class="admin-panel-grid admin-home-bottom-grid">
                <article class="admin-panel-card card">
                    <div class="admin-panel-card-header">
                        <h2>Prioridad de tickets</h2>
                        <p>Distribución de incidencias según urgencia operativa.</p>
                    </div>
                    <div class="admin-chart-box">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </article>

                <article class="admin-panel-card card">
                    <div class="admin-panel-card-header">
                        <h2>Últimos tickets registrados</h2>
                        <p>Seguimiento rápido de las incidencias más recientes.</p>
                    </div>

                    <?php if (empty($recentTickets)): ?>
                        <div class="empty-ticket-box">
                            <h4>Aún no hay tickets registrados</h4>
                            <p>Cuando se creen incidencias, aparecerán en esta sección.</p>
                        </div>
                    <?php else: ?>
                        <div class="tickets-table-wrapper admin-home-table-wrap">
                            <table class="tickets-table admin-dashboard-table admin-home-recent-table">
                                <thead>
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Estado</th>
                                        <th>Técnico</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?= (int)$ticket['id'] ?> <?= htmlspecialchars($ticket['subject'] ?? 'Sin asunto', ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span><?= htmlspecialchars($ticket['requester_name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td><?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $ticket['status'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($ticket['assigned_name'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </article>
            </section>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartColors = {
        primary: '#0f3d2e',
        accent: '#1f7a5a',
        orange: '#ff7a00',
        danger: '#ef4444',
        muted: '#94a3b8',
        blue: '#38bdf8'
    };

    const chartData = {
        statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>,
        statusValues: <?= json_encode($statusValues, JSON_NUMERIC_CHECK) ?>,
        slaLabels: <?= json_encode($slaLabels, JSON_UNESCAPED_UNICODE) ?>,
        slaValues: <?= json_encode($slaValues, JSON_NUMERIC_CHECK) ?>,
        priorityLabels: <?= json_encode($priorityLabels, JSON_UNESCAPED_UNICODE) ?>,
        priorityValues: <?= json_encode($priorityValues, JSON_NUMERIC_CHECK) ?>,
        monthlyLabels: <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE) ?>,
        monthlyValues: <?= json_encode($monthlyValues, JSON_NUMERIC_CHECK) ?>
    };

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const baseAnimation = prefersReducedMotion ? false : {
        duration: 1450,
        easing: 'easeOutQuart',
        delay: function (context) {
            return context.type === 'data' ? context.dataIndex * 90 : 0;
        }
    };

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: baseAnimation,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        plugins: {
            legend: {
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    color: '#475569',
                    font: { weight: '700' }
                }
            },
            tooltip: {
                backgroundColor: '#0f172a',
                padding: 11,
                cornerRadius: 12,
                titleFont: { weight: '800' },
                bodyFont: { weight: '600' }
            }
        }
    };

    const chartFactories = {
        homeStatusChart: function (canvas) {
            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chartData.statusLabels,
                    datasets: [{
                        label: 'Tickets',
                        data: chartData.statusValues,
                        backgroundColor: 'rgba(31, 122, 90, 0.78)',
                        borderColor: chartColors.primary,
                        borderWidth: 1,
                        borderRadius: 9
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: { ...commonOptions.plugins, legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: '700' } } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.18)' } }
                    }
                }
            });
        },
        homeSlaChart: function (canvas) {
            return new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.slaLabels,
                    datasets: [{
                        data: chartData.slaValues,
                        backgroundColor: [chartColors.accent, chartColors.danger],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    ...commonOptions,
                    cutout: '65%',
                    animation: prefersReducedMotion ? false : {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1500,
                        easing: 'easeOutQuart'
                    }
                }
            });
        },
        monthlyTicketsChart: function (canvas) {
            const gradient = canvas.getContext('2d').createLinearGradient(0, 0, 0, 320);
            gradient.addColorStop(0, 'rgba(255, 122, 0, 0.28)');
            gradient.addColorStop(1, 'rgba(255, 122, 0, 0.02)');

            return new Chart(canvas, {
                type: 'line',
                data: {
                    labels: chartData.monthlyLabels,
                    datasets: [{
                        label: 'Tickets creados',
                        data: chartData.monthlyValues,
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: chartColors.orange,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: chartColors.orange,
                        pointBorderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        tension: 0.38,
                        borderWidth: 3
                    }]
                },
                options: {
                    ...commonOptions,
                    animation: prefersReducedMotion ? false : {
                        duration: 1700,
                        easing: 'easeOutQuart',
                        delay: function (context) {
                            return context.type === 'data' ? context.dataIndex * 110 : 0;
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748b', maxRotation: 0, autoSkip: true, font: { weight: '700' } } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.18)' } }
                    }
                }
            });
        },
        priorityChart: function (canvas) {
            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chartData.priorityLabels,
                    datasets: [{
                        label: 'Tickets',
                        data: chartData.priorityValues,
                        backgroundColor: ['rgba(15, 61, 46, 0.82)', 'rgba(255, 122, 0, 0.72)', 'rgba(31, 122, 90, 0.68)', 'rgba(148, 163, 184, 0.58)'],
                        borderRadius: 9
                    }]
                },
                options: {
                    ...commonOptions,
                    indexAxis: 'y',
                    plugins: { ...commonOptions.plugins, legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.18)' } },
                        y: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: '700' } } }
                    }
                }
            });
        }
    };

    const initializedCharts = new Set();

    function initializeChart(canvas) {
        if (!canvas || initializedCharts.has(canvas.id) || typeof Chart === 'undefined') {
            return;
        }

        const factory = chartFactories[canvas.id];
        if (typeof factory === 'function') {
            factory(canvas);
            initializedCharts.add(canvas.id);
        }
    }

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    initializeChart(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.25 });

        Object.keys(chartFactories).forEach(function (chartId) {
            const canvas = document.getElementById(chartId);
            if (canvas) {
                observer.observe(canvas);
            }
        });
    } else {
        Object.keys(chartFactories).forEach(function (chartId) {
            initializeChart(document.getElementById(chartId));
        });
    }
});
</script>

<?php require_once __DIR__ . '/app/views/layouts/footer.php'; ?>
