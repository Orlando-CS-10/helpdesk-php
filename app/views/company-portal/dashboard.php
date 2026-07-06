<?php
/**
 * Variables proporcionadas por company-dashboard.php.
 * Los valores de respaldo permiten que la vista también sea analizada
 * correctamente por Intelephense cuando se abre de forma independiente.
 *
 * @var array<string, mixed>              $account
 * @var array<string, int>                $summary
 * @var array<int, array<string, mixed>>  $recentTickets
 * @var int|null                          $slaPercentage
 */
$account = isset($account) && is_array($account) ? $account : [];

$summaryDefaults = [
    'contacts' => 0,
    'active_contacts' => 0,
    'tickets' => 0,
    'open' => 0,
    'in_progress' => 0,
    'answered' => 0,
    'closed' => 0,
    'sla_met' => 0,
    'sla_measured' => 0,
];
$summary = isset($summary) && is_array($summary)
    ? array_merge($summaryDefaults, $summary)
    : $summaryDefaults;

$recentTickets = isset($recentTickets) && is_array($recentTickets)
    ? $recentTickets
    : [];

$slaPercentage = isset($slaPercentage) && is_numeric($slaPercentage)
    ? (int) $slaPercentage
    : null;

$title = 'Portal corporativo - ' . companyPortalDisplayName($account);
$useCompanyPortalLayout = true;
$companyActivePage = 'dashboard';

$companyName = companyPortalDisplayName($account);
$logoUrl = companyPortalLogoUrl($account['logo_path'] ?? null);
$accountName = trim((string) ($account['name'] ?? 'Cuenta corporativa'));
$passwordNotice = trim((string) ($_SESSION['company_portal_password_notice'] ?? ''));
unset($_SESSION['company_portal_password_notice']);

$companyPageTitle = 'Panel corporativo';
$companyPageDescription = 'Indicadores, tickets y servicio de ' . $companyName . '.';

$statusLabels = [
    'ABIERTO' => 'Abierto',
    'EN_PROCESO' => 'En proceso',
    'RESPONDIDO' => 'Respondido',
    'CERRADO' => 'Cerrado',
];
$priorityLabels = ['BAJA' => 'Baja', 'MEDIA' => 'Media', 'ALTA' => 'Alta'];

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell company-portal-dashboard">
    <?php require_once __DIR__ . '/../layouts/company-sidebar.php'; ?>

    <main class="admin-main">
        <?php require_once __DIR__ . '/../layouts/company-topbar.php'; ?>

        <section class="admin-content admin-dashboard-content company-dashboard-content">
            <?php if ($passwordNotice !== ''): ?>
                <div class="company-shared-alert is-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($passwordNotice, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <section class="admin-dashboard-hero company-dashboard-hero-shared">
                <div>
                    <span class="admin-dashboard-eyebrow">Vista corporativa</span>
                    <h2>Control centralizado para <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>.</h2>
                    <p>
                        El portal limita automáticamente cada consulta a la organización autenticada.
                        No permite acceder al panel administrativo general ni a información de otras empresas.
                    </p>
                </div>

                <div class="company-contract-summary">
                    <span>Servicio contratado</span>
                    <strong><?= (($account['sla_contract_type'] ?? '8_5') === '24_7') ? 'Soporte 24/7' : 'Soporte 8×5' ?></strong>
                    <small>RUC <?= htmlspecialchars((string) ($account['ruc'] ?? 'No registrado'), ENT_QUOTES, 'UTF-8') ?></small>
                </div>
            </section>

            <section class="admin-kpi-grid company-dashboard-kpi-grid" aria-label="Resumen corporativo">
                <article class="admin-kpi-card company-kpi-card">
                    <span class="company-kpi-icon is-blue"><i class="fa-solid fa-users"></i></span>
                    <div>
                        <span class="admin-kpi-label">Contactos</span>
                        <strong class="admin-kpi-value"><?= (int) $summary['contacts'] ?></strong>
                        <p><?= (int) $summary['active_contacts'] ?> cuentas activas</p>
                    </div>
                </article>

                <article class="admin-kpi-card company-kpi-card">
                    <span class="company-kpi-icon is-orange"><i class="fa-solid fa-ticket"></i></span>
                    <div>
                        <span class="admin-kpi-label">Tickets totales</span>
                        <strong class="admin-kpi-value"><?= (int) $summary['tickets'] ?></strong>
                        <p>Historial de la empresa</p>
                    </div>
                </article>

                <article class="admin-kpi-card company-kpi-card">
                    <span class="company-kpi-icon is-purple"><i class="fa-solid fa-spinner"></i></span>
                    <div>
                        <span class="admin-kpi-label">En atención</span>
                        <strong class="admin-kpi-value"><?= (int) ($summary['open'] + $summary['in_progress'] + $summary['answered']) ?></strong>
                        <p>Abiertos y en seguimiento</p>
                    </div>
                </article>

                <article class="admin-kpi-card company-kpi-card">
                    <span class="company-kpi-icon is-green"><i class="fa-solid fa-gauge-high"></i></span>
                    <div>
                        <span class="admin-kpi-label">Cumplimiento SLA</span>
                        <strong class="admin-kpi-value company-kpi-value-text">
                            <?= $slaPercentage === null ? 'Sin datos' : (int) $slaPercentage . '%' ?>
                        </strong>
                        <p><?= (int) $summary['sla_measured'] ?> casos medidos</p>
                    </div>
                </article>
            </section>

            <section class="admin-panel-grid admin-panel-grid-wide company-dashboard-panels">
                <article class="admin-panel-card company-ticket-panel">
                    <div class="admin-panel-card-header company-panel-heading">
                        <div>
                            <span class="company-panel-eyebrow">Actividad reciente</span>
                            <h2>Últimos tickets de la empresa</h2>
                            <p>Se muestran los seis registros corporativos más recientes.</p>
                        </div>
                        <small>Máximo 6 resultados</small>
                    </div>

                    <?php if (!$recentTickets): ?>
                        <div class="empty-ticket-box company-empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <h4>No hay tickets registrados</h4>
                            <p>Los tickets vinculados a la empresa aparecerán en esta sección.</p>
                        </div>
                    <?php else: ?>
                        <div class="company-ticket-list-shared">
                            <?php foreach ($recentTickets as $ticket): ?>
                                <?php
                                $status = (string) ($ticket['status'] ?? 'ABIERTO');
                                $priority = (string) ($ticket['priority'] ?? 'MEDIA');
                                ?>
                                <article class="company-ticket-row">
                                    <span class="company-ticket-id">#<?= (int) $ticket['id'] ?></span>

                                    <div class="company-ticket-copy">
                                        <strong><?= htmlspecialchars((string) $ticket['subject'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small>
                                            <?= htmlspecialchars((string) ($ticket['requester_name'] ?? 'Contacto'), ENT_QUOTES, 'UTF-8') ?>
                                            · <?= htmlspecialchars((string) ($ticket['category'] ?? 'OTROS'), ENT_QUOTES, 'UTF-8') ?>
                                            · <?= date('d/m/Y H:i', strtotime((string) $ticket['created_at'])) ?>
                                        </small>
                                    </div>

                                    <div class="company-ticket-tags">
                                        <em class="status-<?= strtolower($status) ?>">
                                            <?= htmlspecialchars($statusLabels[$status] ?? $status, ENT_QUOTES, 'UTF-8') ?>
                                        </em>
                                        <em class="priority-<?= strtolower($priority) ?>">
                                            <?= htmlspecialchars($priorityLabels[$priority] ?? $priority, ENT_QUOTES, 'UTF-8') ?>
                                        </em>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <aside class="admin-panel-card company-status-panel">
                    <div class="admin-panel-card-header">
                        <span class="company-panel-eyebrow">Distribución actual</span>
                        <h2>Estado de tickets</h2>
                        <p>Panorama operativo de los casos registrados.</p>
                    </div>

                    <div class="company-status-list-shared">
                        <div>
                            <span><i class="fa-regular fa-circle"></i> Abiertos</span>
                            <strong><?= (int) $summary['open'] ?></strong>
                        </div>
                        <div>
                            <span><i class="fa-solid fa-spinner"></i> En proceso</span>
                            <strong><?= (int) $summary['in_progress'] ?></strong>
                        </div>
                        <div>
                            <span><i class="fa-regular fa-message"></i> Respondidos</span>
                            <strong><?= (int) $summary['answered'] ?></strong>
                        </div>
                        <div>
                            <span><i class="fa-solid fa-circle-check"></i> Cerrados</span>
                            <strong><?= (int) $summary['closed'] ?></strong>
                        </div>
                    </div>

                    <div class="company-next-module">
                        <i class="fa-solid fa-users-gear"></i>
                        <div>
                            <strong>Siguiente módulo</strong>
                            <p>Gestión de usuarios, contactos y permisos internos.</p>
                        </div>
                    </div>
                </aside>
            </section>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/../layouts/company-footer.php'; ?>
