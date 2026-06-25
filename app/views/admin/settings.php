<?php
$settingsModules = $settingsModules ?? [];
$availableSettingsCount = count(array_filter(
    $settingsModules,
    static fn (array $module): bool => !empty($module['available'])
));

$title = 'Ajustes del sistema';
$activePage = 'settings';
$pageTitle = 'Ajustes del sistema';
$pageSubtitle = 'Administra la configuración institucional y operativa desde un centro organizado.';

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-settings-content">
            <section class="settings-hub-hero">
                <div>
                    <span class="settings-eyebrow">Centro de configuración</span>
                    <h2>Organiza el sistema sin llenar la pantalla</h2>
                    <p>Cada opción abre una subpágina independiente. Comenzaremos con el Perfil del sistema y activaremos los demás módulos progresivamente.</p>
                </div>

                <div class="settings-hub-summary" aria-label="Resumen de módulos">
                    <strong><?= count($settingsModules) ?></strong>
                    <span>módulos</span>
                </div>
            </section>

            <section class="settings-hub-panel">
                <div class="settings-hub-heading">
                    <div>
                        <span>Configuración</span>
                        <h2>Selecciona una sección</h2>
                    </div>
                    <small><?= (int) $availableSettingsCount ?> módulo<?= $availableSettingsCount === 1 ? '' : 's' ?> disponible<?= $availableSettingsCount === 1 ? '' : 's' ?></small>
                </div>

                <div class="settings-module-list">
                    <?php foreach ($settingsModules as $module): ?>
                        <?php $available = !empty($module['available']); ?>

                        <?php if ($available): ?>
                            <a class="settings-module-row is-available" href="<?= htmlspecialchars($module['href'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <div class="settings-module-row is-coming" aria-disabled="true">
                        <?php endif; ?>

                            <span class="settings-module-icon" aria-hidden="true">
                                <i class="<?= htmlspecialchars($module['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            </span>

                            <span class="settings-module-copy">
                                <strong><?= htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars($module['description'], ENT_QUOTES, 'UTF-8') ?></small>
                            </span>

                            <span class="settings-module-status <?= $available ? 'is-ready' : '' ?>">
                                <?= htmlspecialchars($module['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <span class="settings-module-arrow" aria-hidden="true">
                                <i class="fa-solid <?= $available ? 'fa-chevron-right' : 'fa-lock' ?>"></i>
                            </span>

                        <?php if ($available): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
