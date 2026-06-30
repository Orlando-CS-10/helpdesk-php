<?php
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$historyRows = $historyRows ?? [];
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);

$title = 'Historial de mantenimiento';
$activePage = 'maintenance-history';
$pageTitle = 'Historial de mantenimiento';
$pageSubtitle = 'Consulta las acciones técnicas ejecutadas por los administradores.';
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>
    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>
        <main class="admin-content admin-settings-content system-tools-content">
            <section class="system-tool-page-hero">
                <div>
                    <a href="/helpdesk-php/admin-system-tools.php" class="system-tool-back"><i class="fa-solid fa-arrow-left"></i> Volver al centro</a>
                    <span class="settings-eyebrow">Seguimiento</span>
                    <h2>Memoria técnica del sistema</h2>
                    <p>Cada diagnóstico, respaldo, limpieza y cambio de mantenimiento queda registrado.</p>
                </div>
                <div class="system-tool-page-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert"><span><i class="fa-solid fa-database"></i></span><div><strong>Historial no disponible</strong><p>Ejecuta <code>database/system_tools.sql</code> para comenzar a registrar acciones.</p></div></section>
            <?php endif; ?>

            <section class="system-tool-panel">
                <div class="system-tool-panel-heading"><div><span>Trazabilidad</span><h3>Acciones recientes</h3></div><small><?= count($historyRows) ?> registros en esta página</small></div>
                <?php if (!$historyRows): ?>
                    <div class="system-tool-empty"><i class="fa-solid fa-clock"></i><strong>Aún no hay acciones registradas</strong><p>El historial crecerá a medida que se utilicen las herramientas.</p></div>
                <?php else: ?>
                    <div class="maintenance-history-list">
                        <?php foreach ($historyRows as $row): ?>
                            <article>
                                <span class="maintenance-history-icon <?= htmlspecialchars($row['severity'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid <?= $row['severity'] === 'critical' ? 'fa-triangle-exclamation' : ($row['severity'] === 'warning' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>"></i></span>
                                <div><strong><?= htmlspecialchars($row['action_type'], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') ?></p><small><?= htmlspecialchars((string) ($row['actor_name'] ?? 'Sistema'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $row['created_at'])), ENT_QUOTES, 'UTF-8') ?><?= !empty($row['ip_address']) ? ' · ' . htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8') : '' ?></small></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?><nav class="system-tool-pagination"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
            </section>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
