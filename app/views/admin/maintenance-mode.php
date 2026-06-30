<?php
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$maintenanceSettings = $maintenanceSettings ?? [];
$csrfToken = (string) ($csrfToken ?? '');
$enabled = !empty($maintenanceSettings['is_enabled']);

$title = 'Modo mantenimiento';
$activePage = 'maintenance-mode';
$pageTitle = 'Modo mantenimiento';
$pageSubtitle = 'Controla temporalmente el acceso mientras realizas cambios técnicos.';
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>
    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>
        <main class="admin-content admin-settings-content system-tools-content">
            <section class="system-tool-page-hero <?= $enabled ? 'is-warning' : '' ?>">
                <div>
                    <a href="/helpdesk-php/admin-system-tools.php" class="system-tool-back"><i class="fa-solid fa-arrow-left"></i> Volver al centro</a>
                    <span class="settings-eyebrow">Mantenimiento</span>
                    <h2><?= $enabled ? 'El modo mantenimiento está activo' : 'Prepara una ventana de mantenimiento segura' ?></h2>
                    <p>Los administradores conservan el acceso. Clientes y técnicos pueden ser bloqueados según la configuración.</p>
                </div>
                <div class="system-tool-page-icon"><i class="fa-solid fa-person-digging"></i></div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert"><span><i class="fa-solid fa-database"></i></span><div><strong>Falta preparar la base de datos</strong><p>Ejecuta <code>database/system_tools.sql</code> para habilitar esta herramienta.</p></div></section>
            <?php endif; ?>

            <section class="maintenance-mode-layout">
                <form method="POST" action="/helpdesk-php/update-maintenance-mode.php" class="system-tool-panel maintenance-mode-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="system-tool-panel-heading"><div><span>Configuración</span><h3>Acceso temporal</h3></div><span class="maintenance-state <?= $enabled ? 'on' : 'off' ?>"><?= $enabled ? 'Activo' : 'Inactivo' ?></span></div>

                    <label class="maintenance-main-switch">
                        <input type="checkbox" name="is_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                        <span></span>
                        <div><strong>Activar modo mantenimiento</strong><small>Restringe el acceso según los roles seleccionados.</small></div>
                    </label>

                    <div class="form-group">
                        <label for="message">Mensaje para los usuarios</label>
                        <textarea id="message" name="message" maxlength="500" required><?= htmlspecialchars((string) ($maintenanceSettings['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="system-tools-form-grid">
                        <div class="form-group">
                            <label for="estimated_return_at">Regreso estimado</label>
                            <input type="datetime-local" id="estimated_return_at" name="estimated_return_at" value="<?= !empty($maintenanceSettings['estimated_return_at']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $maintenanceSettings['estimated_return_at'])), ENT_QUOTES, 'UTF-8') : '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="admin_password">Contraseña del administrador</label>
                            <input type="password" id="admin_password" name="admin_password" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="maintenance-role-grid">
                        <label><input type="checkbox" name="allow_admin" value="1" checked disabled><span><strong>Administradores</strong><small>Acceso obligatorio para poder desactivar el modo.</small></span></label>
                        <input type="hidden" name="allow_admin" value="1">
                        <label><input type="checkbox" name="block_tech" value="1" <?= !empty($maintenanceSettings['block_tech']) ? 'checked' : '' ?>><span><strong>Bloquear técnicos</strong><small>No podrán usar herramientas ni atender tickets.</small></span></label>
                        <label><input type="checkbox" name="block_client" value="1" <?= !empty($maintenanceSettings['block_client']) ? 'checked' : '' ?>><span><strong>Bloquear clientes</strong><small>Verán la página pública de mantenimiento.</small></span></label>
                    </div>

                    <div class="form-group">
                        <label for="confirmation">Confirmación para activar</label>
                        <input type="text" id="confirmation" name="confirmation" autocomplete="off" placeholder="MANTENIMIENTO">
                        <small>Solo es obligatorio cuando el interruptor queda activado.</small>
                    </div>

                    <div class="system-tools-form-actions">
                        <a class="btn-secondary" href="/helpdesk-php/maintenance.php" target="_blank"><i class="fa-solid fa-eye"></i> Ver página pública</a>
                        <button class="btn-primary" type="submit" <?= !$systemToolsReady ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk"></i> Guardar configuración</button>
                    </div>
                </form>

                <aside class="maintenance-preview-card">
                    <span>Vista previa</span>
                    <div class="maintenance-preview-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                    <h3>Sistema en mantenimiento</h3>
                    <p><?= htmlspecialchars((string) ($maintenanceSettings['message'] ?? 'El sistema se encuentra temporalmente en mantenimiento.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($maintenanceSettings['estimated_return_at'])): ?><small><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $maintenanceSettings['estimated_return_at'])), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                    <dl>
                        <div><dt>Última modificación</dt><dd><?= !empty($maintenanceSettings['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $maintenanceSettings['updated_at'])), ENT_QUOTES, 'UTF-8') : 'Sin cambios' ?></dd></div>
                        <div><dt>Responsable</dt><dd><?= htmlspecialchars((string) ($maintenanceSettings['updated_by_name'] ?? 'No registrado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    </dl>
                </aside>
            </section>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
