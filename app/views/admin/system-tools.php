<?php
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$maintenanceSettings = $maintenanceSettings ?? [];
$databaseStats = $databaseStats ?? ['tables' => 0, 'size_bytes' => 0];
$backupCount = (int) ($backupCount ?? 0);
$recentActionCount = (int) ($recentActionCount ?? 0);
$technicalLogCount = (int) ($technicalLogCount ?? 0);

$title = 'Herramientas del sistema';
$activePage = 'system-tools';
$pageTitle = 'Herramientas del sistema';
$pageSubtitle = 'Supervisa, protege y mantén la plataforma desde un centro técnico ordenado.';

require_once __DIR__ . '/../layouts/header.php';

$groups = [
    'Supervisión' => [
        [
            'title' => 'Diagnóstico del sistema',
            'description' => 'Comprueba MySQL, PHP, extensiones, carpetas y espacio disponible.',
            'href' => '/helpdesk-php/admin-system-diagnostics.php',
            'icon' => 'fa-solid fa-stethoscope',
            'available' => true,
            'meta' => $systemToolsReady ? 'Listo para revisar' : 'Requiere instalación',
        ],
        [
            'title' => 'Información técnica',
            'description' => 'Consulta versiones, entorno, almacenamiento y estadísticas de la plataforma.',
            'href' => '/helpdesk-php/admin-system-information.php',
            'icon' => 'fa-solid fa-circle-info',
            'available' => true,
            'meta' => 'Consulta disponible',
            'state' => 'success',
        ],
        [
            'title' => 'Pruebas del sistema',
            'description' => 'Ejecuta comprobaciones controladas de MySQL, PDF, imágenes, ZIP y escritura.',
            'href' => '/helpdesk-php/admin-system-tests.php',
            'icon' => 'fa-solid fa-vial-circle-check',
            'available' => true,
            'meta' => '8 pruebas disponibles',
            'state' => 'success',
        ],
    ],
    'Mantenimiento' => [
        [
            'title' => 'Copias de seguridad',
            'description' => 'Crea, descarga y elimina respaldos protegidos de datos y archivos.',
            'href' => '/helpdesk-php/admin-system-backups.php',
            'icon' => 'fa-solid fa-database',
            'available' => true,
            'meta' => $backupCount . ' respaldo' . ($backupCount === 1 ? '' : 's'),
        ],
        [
            'title' => 'Limpieza y mantenimiento',
            'description' => 'Analiza temporales, sesiones antiguas y archivos huérfanos antes de borrar.',
            'href' => '/helpdesk-php/admin-system-cleanup.php',
            'icon' => 'fa-solid fa-broom',
            'available' => true,
            'meta' => 'Análisis seguro',
        ],
        [
            'title' => 'Modo mantenimiento',
            'description' => 'Restringe temporalmente el acceso sin bloquear a los administradores.',
            'href' => '/helpdesk-php/admin-maintenance-mode.php',
            'icon' => 'fa-solid fa-person-digging',
            'available' => true,
            'meta' => !empty($maintenanceSettings['is_enabled']) ? 'Activo' : 'Inactivo',
            'state' => !empty($maintenanceSettings['is_enabled']) ? 'warning' : 'success',
        ],
    ],
    'Seguimiento' => [
        [
            'title' => 'Registros técnicos',
            'description' => 'Consulta errores internos organizados por módulo, gravedad y fecha.',
            'href' => '/helpdesk-php/admin-system-logs.php',
            'icon' => 'fa-solid fa-file-waveform',
            'available' => true,
            'meta' => $technicalLogCount . ' registro' . ($technicalLogCount === 1 ? '' : 's') . ' este mes',
            'state' => $technicalLogCount > 0 ? 'warning' : 'success',
        ],
        [
            'title' => 'Historial de mantenimiento',
            'description' => 'Revisa diagnósticos, respaldos, limpiezas y cambios de mantenimiento.',
            'href' => '/helpdesk-php/admin-maintenance-history.php',
            'icon' => 'fa-solid fa-clock-rotate-left',
            'available' => true,
            'meta' => $recentActionCount . ' acción' . ($recentActionCount === 1 ? '' : 'es') . ' este mes',
        ],
    ],
];
?>

<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-settings-content system-tools-content">
            <section class="system-tools-hero">
                <div>
                    <span class="settings-eyebrow">Centro técnico</span>
                    <h2>Un taller ordenado para mantener la plataforma saludable</h2>
                    <p>Cada herramienta abre su propia página. Así las acciones delicadas no se mezclan y el panel principal conserva una lectura limpia.</p>
                </div>
                <div class="system-tools-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-toolbox"></i>
                </div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Falta preparar la base de datos</strong>
                        <p>Ejecuta <code>database/system_tools.sql</code> en phpMyAdmin para habilitar respaldos, historial y modo mantenimiento.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-tools-summary-grid">
                <article>
                    <span><i class="fa-solid fa-table-list"></i></span>
                    <div><strong><?= (int) ($databaseStats['tables'] ?? 0) ?></strong><small>tablas en MySQL</small></div>
                </article>
                <article>
                    <span><i class="fa-solid fa-hard-drive"></i></span>
                    <div><strong><?= htmlspecialchars(function_exists('systemToolsFormatBytes') ? systemToolsFormatBytes((int) ($databaseStats['size_bytes'] ?? 0)) : '0 B', ENT_QUOTES, 'UTF-8') ?></strong><small>tamaño de la base</small></div>
                </article>
                <article>
                    <span><i class="fa-solid fa-box-archive"></i></span>
                    <div><strong><?= $backupCount ?></strong><small>respaldos registrados</small></div>
                </article>
                <article>
                    <span><i class="fa-solid fa-signal"></i></span>
                    <div><strong><?= !empty($maintenanceSettings['is_enabled']) ? 'Mantenimiento' : 'Operativo' ?></strong><small>estado de acceso</small></div>
                </article>
            </section>

            <?php foreach ($groups as $groupTitle => $items): ?>
                <section class="system-tools-group">
                    <div class="system-tools-group-heading">
                        <div>
                            <span><?= htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8') ?></span>
                            <h3><?= $groupTitle === 'Supervisión' ? 'Estado y comprobaciones' : ($groupTitle === 'Mantenimiento' ? 'Acciones de cuidado' : 'Registro y trazabilidad') ?></h3>
                        </div>
                        <small><?= count($items) ?> herramientas</small>
                    </div>

                    <div class="system-tools-grid">
                        <?php foreach ($items as $item): ?>
                            <?php if (!empty($item['available'])): ?>
                                <a class="system-tool-card is-available" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <div class="system-tool-card is-coming" aria-disabled="true">
                            <?php endif; ?>
                                <span class="system-tool-card-icon"><i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                <div class="system-tool-card-copy">
                                    <strong><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <p><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <span class="system-tool-meta <?= htmlspecialchars((string) ($item['state'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($item['meta'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <span class="system-tool-card-arrow"><i class="fa-solid <?= !empty($item['available']) ? 'fa-arrow-right' : 'fa-lock' ?>"></i></span>
                            <?php if (!empty($item['available'])): ?>
                                </a>
                            <?php else: ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
