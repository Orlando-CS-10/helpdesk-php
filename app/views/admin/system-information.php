<?php
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$systemInformation = is_array($systemInformation ?? null) ? $systemInformation : [];

$platform = (array) ($systemInformation['platform'] ?? []);
$technology = (array) ($systemInformation['technology'] ?? []);
$database = (array) ($systemInformation['database'] ?? []);
$statistics = (array) ($systemInformation['statistics'] ?? []);
$storage = (array) ($systemInformation['storage'] ?? []);
$activity = (array) ($systemInformation['activity'] ?? []);

$formatBytes = static function (mixed $bytes): string {
    return function_exists('systemToolsFormatBytes')
        ? systemToolsFormatBytes((int) $bytes)
        : number_format((float) $bytes, 0) . ' B';
};

$formatDate = static function (?array $event): string {
    $value = trim((string) ($event['created_at'] ?? ''));
    if ($value === '') {
        return 'Sin registros';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
};

$eventActor = static function (?array $event): string {
    $name = trim((string) ($event['actor_name'] ?? ''));
    return $name !== '' ? $name : 'Sistema';
};

$title = 'Información técnica del sistema';
$activePage = 'system-information';
$pageTitle = 'Información técnica';
$pageSubtitle = 'Consulta el entorno, la tecnología, el almacenamiento y las cifras generales de la plataforma.';

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-settings-content system-tools-content">
            <section class="system-tool-page-hero">
                <div>
                    <a class="system-tool-back" href="/helpdesk-php/admin-system-tools.php">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Volver a Herramientas del sistema</span>
                    </a>
                    <span class="settings-eyebrow">Supervisión</span>
                    <h2>Ficha técnica de la plataforma</h2>
                    <p>Una vista de solo lectura con la identidad del sistema, versiones, base de datos, almacenamiento y actividad técnica reciente.</p>
                </div>
                <div class="system-tool-page-icon" aria-hidden="true">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Información parcial</strong>
                        <p>La ficha técnica funciona, pero la actividad de mantenimiento aparecerá cuando ejecutes <code>database/system_tools.sql</code>.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-information-summary" aria-label="Resumen técnico">
                <article>
                    <span><i class="fa-solid fa-display"></i></span>
                    <div>
                        <small>Sistema</small>
                        <strong><?= htmlspecialchars((string) ($platform['system_name'] ?? 'Mesa de Ayuda'), ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars((string) ($platform['environment'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </article>
                <article>
                    <span><i class="fa-brands fa-php"></i></span>
                    <div>
                        <small>Servidor PHP</small>
                        <strong><?= htmlspecialchars((string) ($technology['php_version'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars((string) ($technology['php_sapi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </article>
                <article>
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <small>Base de datos</small>
                        <strong><?= $formatBytes($database['size_bytes'] ?? 0) ?></strong>
                        <p><?= (int) ($database['tables'] ?? 0) ?> tablas</p>
                    </div>
                </article>
                <article>
                    <span><i class="fa-solid fa-box-archive"></i></span>
                    <div>
                        <small>Archivos administrados</small>
                        <strong><?= $formatBytes($systemInformation['storage_total_bytes'] ?? 0) ?></strong>
                        <p>adjuntos, imágenes y respaldos</p>
                    </div>
                </article>
            </section>

            <div class="system-information-grid">
                <section class="system-information-card">
                    <div class="system-information-card-heading">
                        <span><i class="fa-solid fa-fingerprint"></i></span>
                        <div>
                            <small>Identidad</small>
                            <h3>Plataforma</h3>
                        </div>
                    </div>
                    <dl class="system-information-list">
                        <div><dt>Nombre del sistema</dt><dd><?= htmlspecialchars((string) ($platform['system_name'] ?? 'No configurado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Empresa responsable</dt><dd><?= htmlspecialchars((string) ($platform['company_name'] ?? 'No configurada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Nombre comercial</dt><dd><?= htmlspecialchars((string) ($platform['commercial_name'] ?? 'No configurado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Versión instalada</dt><dd><?= htmlspecialchars((string) ($platform['version'] ?? 'No configurada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Entorno</dt><dd><span class="system-information-pill is-environment"><?= htmlspecialchars((string) ($platform['environment'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></span></dd></div>
                        <div><dt>Fecha y hora del servidor</dt><dd><?= htmlspecialchars((string) ($platform['server_datetime'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Zona horaria</dt><dd><?= htmlspecialchars((string) ($platform['timezone'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    </dl>
                </section>

                <section class="system-information-card">
                    <div class="system-information-card-heading">
                        <span><i class="fa-solid fa-server"></i></span>
                        <div>
                            <small>Infraestructura</small>
                            <h3>Tecnología</h3>
                        </div>
                    </div>
                    <dl class="system-information-list">
                        <div><dt>PHP</dt><dd><?= htmlspecialchars((string) ($technology['php_version'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Interfaz PHP</dt><dd><?= htmlspecialchars((string) ($technology['php_sapi'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Servidor web</dt><dd><?= htmlspecialchars((string) ($technology['server_software'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Sistema operativo</dt><dd><?= htmlspecialchars((string) ($technology['operating_system'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Límite de memoria</dt><dd><?= htmlspecialchars((string) ($technology['memory_limit'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Límite de carga</dt><dd><?= htmlspecialchars((string) ($technology['upload_limit'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Límite de solicitud</dt><dd><?= htmlspecialchars((string) ($technology['post_limit'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Tiempo máximo</dt><dd><?= htmlspecialchars((string) ($technology['max_execution_time'] ?? 'No identificado'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    </dl>
                </section>

                <section class="system-information-card">
                    <div class="system-information-card-heading">
                        <span><i class="fa-solid fa-database"></i></span>
                        <div>
                            <small>Persistencia</small>
                            <h3>Base de datos</h3>
                        </div>
                    </div>
                    <dl class="system-information-list">
                        <div><dt>Nombre</dt><dd><?= htmlspecialchars((string) ($database['name'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Motor y versión</dt><dd><?= htmlspecialchars((string) ($database['version'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Tablas</dt><dd><?= (int) ($database['tables'] ?? 0) ?></dd></div>
                        <div><dt>Tamaño estimado</dt><dd><?= $formatBytes($database['size_bytes'] ?? 0) ?></dd></div>
                        <div><dt>Codificación</dt><dd><?= htmlspecialchars((string) ($database['charset'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Intercalación</dt><dd><?= htmlspecialchars((string) ($database['collation'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt>Zona horaria</dt><dd><?= htmlspecialchars((string) ($database['timezone'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    </dl>
                </section>

                <section class="system-information-card">
                    <div class="system-information-card-heading">
                        <span><i class="fa-solid fa-chart-column"></i></span>
                        <div>
                            <small>Volumen actual</small>
                            <h3>Estadísticas</h3>
                        </div>
                    </div>
                    <div class="system-information-stats">
                        <article><strong><?= (int) ($statistics['companies'] ?? 0) ?></strong><span>Empresas</span><small><?= (int) ($statistics['active_companies'] ?? 0) ?> activas</small></article>
                        <article><strong><?= (int) ($statistics['users'] ?? 0) ?></strong><span>Usuarios</span><small><?= (int) ($statistics['active_users'] ?? 0) ?> activos</small></article>
                        <article><strong><?= (int) ($statistics['tickets'] ?? 0) ?></strong><span>Tickets</span><small>registrados</small></article>
                        <article><strong><?= (int) ($statistics['open_tickets'] ?? 0) ?></strong><span>En atención</span><small>no cerrados</small></article>
                        <article><strong><?= (int) ($statistics['closed_tickets'] ?? 0) ?></strong><span>Cerrados</span><small>históricos</small></article>
                        <article><strong><?= (int) ($database['tables'] ?? 0) ?></strong><span>Tablas</span><small>en MySQL</small></article>
                    </div>
                </section>
            </div>

            <section class="system-information-card system-information-wide-card">
                <div class="system-information-card-heading">
                    <span><i class="fa-solid fa-hard-drive"></i></span>
                    <div>
                        <small>Uso de disco</small>
                        <h3>Almacenamiento administrado</h3>
                    </div>
                    <div class="system-information-storage-total">
                        <strong><?= $formatBytes($systemInformation['storage_total_bytes'] ?? 0) ?></strong>
                        <span>archivos del sistema</span>
                    </div>
                </div>

                <div class="system-information-storage-grid">
                    <?php foreach ($storage as $item): ?>
                        <?php
                        $exists = !empty($item['exists']);
                        $writable = !empty($item['writable']);
                        $stateClass = !$exists ? 'is-missing' : ($writable ? 'is-ready' : 'is-warning');
                        $stateText = !$exists ? 'No disponible' : ($writable ? 'Disponible' : 'Solo lectura');
                        ?>
                        <article>
                            <span class="system-information-storage-icon"><i class="fa-solid fa-folder"></i></span>
                            <div>
                                <strong><?= htmlspecialchars((string) ($item['label'] ?? 'Directorio'), ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= $formatBytes($item['bytes'] ?? 0) ?></small>
                            </div>
                            <em class="<?= $stateClass ?>"><?= $stateText ?></em>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="system-information-disk-summary">
                    <div>
                        <span>Espacio libre</span>
                        <strong><?= $systemInformation['disk_free_bytes'] !== null ? $formatBytes($systemInformation['disk_free_bytes']) : 'No disponible' ?></strong>
                    </div>
                    <div>
                        <span>Capacidad del disco</span>
                        <strong><?= $systemInformation['disk_total_bytes'] !== null ? $formatBytes($systemInformation['disk_total_bytes']) : 'No disponible' ?></strong>
                    </div>
                    <div>
                        <span>Ruta del proyecto</span>
                        <strong title="<?= htmlspecialchars((string) ($platform['project_root'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($platform['project_root'] ?? 'No identificada'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                </div>
            </section>

            <section class="system-information-card system-information-wide-card">
                <div class="system-information-card-heading">
                    <span><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <div>
                        <small>Seguimiento técnico</small>
                        <h3>Actividad reciente</h3>
                    </div>
                </div>

                <div class="system-information-activity-grid">
                    <?php
                    $activityItems = [
                        ['label' => 'Último diagnóstico', 'icon' => 'fa-stethoscope', 'event' => $activity['last_diagnostic'] ?? null],
                        ['label' => 'Último respaldo', 'icon' => 'fa-box-archive', 'event' => $activity['last_backup'] ?? null],
                        ['label' => 'Última limpieza', 'icon' => 'fa-broom', 'event' => $activity['last_cleanup'] ?? null],
                        ['label' => 'Cambio de mantenimiento', 'icon' => 'fa-person-digging', 'event' => $activity['last_maintenance_change'] ?? null],
                    ];
                    ?>
                    <?php foreach ($activityItems as $activityItem): ?>
                        <?php $event = is_array($activityItem['event']) ? $activityItem['event'] : null; ?>
                        <article>
                            <span><i class="fa-solid <?= htmlspecialchars($activityItem['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                            <div>
                                <small><?= htmlspecialchars($activityItem['label'], ENT_QUOTES, 'UTF-8') ?></small>
                                <strong><?= htmlspecialchars($formatDate($event), ENT_QUOTES, 'UTF-8') ?></strong>
                                <p><?= $event ? 'Responsable: ' . htmlspecialchars($eventActor($event), ENT_QUOTES, 'UTF-8') : 'Aún no hay actividad registrada.' ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
