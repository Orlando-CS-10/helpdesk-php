<?php
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$backups = $backups ?? [];
$csrfToken = (string) ($csrfToken ?? '');
$zipAvailable = (bool) ($zipAvailable ?? false);
$backupDirectoryWritable = (bool) ($backupDirectoryWritable ?? false);
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);

$title = 'Copias de seguridad';
$activePage = 'system-backups';
$pageTitle = 'Copias de seguridad';
$pageSubtitle = 'Crea respaldos protegidos y conserva un historial descargable.';
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
                    <span class="settings-eyebrow">Mantenimiento</span>
                    <h2>Respaldos bajo control</h2>
                    <p>Los archivos se guardan fuera de la carpeta pública y solo un administrador autenticado puede descargarlos.</p>
                </div>
                <div class="system-tool-page-icon"><i class="fa-solid fa-database"></i></div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert"><span><i class="fa-solid fa-database"></i></span><div><strong>Falta preparar la base de datos</strong><p>Ejecuta <code>database/system_tools.sql</code> para registrar los respaldos.</p></div></section>
            <?php endif; ?>

            <section class="system-backup-options">
                <?php
                $options = [
                    ['DATABASE', 'Base de datos', 'Archivo SQL con estructura y registros.', 'fa-solid fa-table-cells-large', true],
                    ['FILES', 'Archivos cargados', 'Adjuntos, logos y fotografías en un ZIP.', 'fa-solid fa-folder-tree', $zipAvailable],
                    ['FULL', 'Respaldo completo', 'Base de datos y archivos dentro de un solo ZIP.', 'fa-solid fa-box-archive', $zipAvailable],
                ];
                ?>
                <?php foreach ($options as [$value, $label, $description, $icon, $enabled]): ?>
                    <form method="POST" action="/helpdesk-php/create-system-backup.php" class="system-backup-option <?= !$enabled ? 'is-disabled' : '' ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="backup_type" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                        <span><i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i></span>
                        <div><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p></div>
                        <button class="btn-primary" type="submit" <?= (!$enabled || !$systemToolsReady || !$backupDirectoryWritable) ? 'disabled' : '' ?>>Crear respaldo</button>
                        <?php if (!$enabled): ?><small>Requiere la extensión ZIP de PHP.</small><?php endif; ?>
                    </form>
                <?php endforeach; ?>
            </section>

            <section class="system-tool-panel">
                <div class="system-tool-panel-heading"><div><span>Historial</span><h3>Respaldos creados</h3></div><small><?= count($backups) ?> en esta página</small></div>
                <?php if (!$backups): ?>
                    <div class="system-tool-empty"><i class="fa-solid fa-box-open"></i><strong>Aún no existen respaldos</strong><p>Crea el primero desde las opciones superiores.</p></div>
                <?php else: ?>
                    <div class="system-backup-list">
                        <?php foreach ($backups as $backup): ?>
                            <article>
                                <span class="system-backup-file-icon"><i class="fa-solid <?= str_ends_with((string) $backup['file_name'], '.zip') ? 'fa-file-zipper' : 'fa-file-code' ?>"></i></span>
                                <div class="system-backup-file-copy">
                                    <strong><?= htmlspecialchars($backup['file_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= htmlspecialchars($backup['backup_type'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(systemToolsFormatBytes((int) $backup['file_size_bytes']), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $backup['created_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                                    <p><?= htmlspecialchars((string) ($backup['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="system-backup-actions">
                                    <a class="btn-secondary" href="/helpdesk-php/download-system-backup.php?id=<?= (int) $backup['id'] ?>"><i class="fa-solid fa-download"></i> Descargar</a>
                                    <form method="POST" action="/helpdesk-php/delete-system-backup.php" onsubmit="return confirm('¿Eliminar este respaldo? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= (int) $backup['id'] ?>">
                                        <button class="system-danger-button" type="submit"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                    <nav class="system-tool-pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="<?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
