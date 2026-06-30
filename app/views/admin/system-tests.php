<?php
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$testDefinitions = is_array($testDefinitions ?? null) ? $testDefinitions : [];
$testRun = is_array($testRun ?? null) ? $testRun : null;
$csrfToken = (string) ($csrfToken ?? '');
$results = is_array($testRun['results'] ?? null) ? $testRun['results'] : [];
$summary = is_array($testRun['summary'] ?? null)
    ? $testRun['summary']
    : ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => 0];

$statusLabels = [
    'ok' => 'Correcta',
    'warning' => 'Advertencia',
    'error' => 'Error',
    'not-run' => 'Sin ejecutar',
];

$statusIcons = [
    'ok' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-xmark',
    'not-run' => 'fa-circle-minus',
];

$groupDescriptions = [
    'Base de datos' => 'Disponibilidad, lectura y respuesta del motor MySQL.',
    'Archivos y almacenamiento' => 'Permisos y operaciones temporales controladas.',
    'Documentos y multimedia' => 'Generación de PDF y lectura segura de imágenes.',
    'Servicios internos' => 'ZIP, respaldos y disponibilidad de correo.',
];

$groupedTests = [];
foreach ($testDefinitions as $definition) {
    $group = (string) ($definition['group'] ?? 'Otras pruebas');
    $groupedTests[$group][] = $definition;
}

$lastRunText = 'Sin ejecuciones en esta sesión';
if (!empty($testRun['ran_at'])) {
    $timestamp = strtotime((string) $testRun['ran_at']);
    $lastRunText = $timestamp ? date('d/m/Y H:i:s', $timestamp) : (string) $testRun['ran_at'];
}

$title = 'Pruebas del sistema';
$activePage = 'system-tests';
$pageTitle = 'Pruebas del sistema';
$pageSubtitle = 'Ejecuta comprobaciones controladas sin modificar tickets, usuarios ni configuraciones.';

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
                        <span>Volver al centro</span>
                    </a>
                    <span class="settings-eyebrow">Supervisión</span>
                    <h2>Laboratorio controlado de la plataforma</h2>
                    <p>Comprueba servicios esenciales con operaciones temporales y seguras. Ninguna prueba modifica información funcional del HelpDesk.</p>
                </div>
                <div class="system-tool-page-icon" aria-hidden="true">
                    <i class="fa-solid fa-vial-circle-check"></i>
                </div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Las pruebas funcionan con historial limitado</strong>
                        <p>Ejecuta <code>database/system_tools.sql</code> para registrar cada ejecución en el historial de mantenimiento.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-tests-summary" aria-label="Resumen de pruebas">
                <article class="is-ok">
                    <span><i class="fa-solid fa-circle-check"></i></span>
                    <div><strong><?= (int) ($summary['ok'] ?? 0) ?></strong><small>correctas</small></div>
                </article>
                <article class="is-warning">
                    <span><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div><strong><?= (int) ($summary['warning'] ?? 0) ?></strong><small>advertencias</small></div>
                </article>
                <article class="is-error">
                    <span><i class="fa-solid fa-circle-xmark"></i></span>
                    <div><strong><?= (int) ($summary['error'] ?? 0) ?></strong><small>errores</small></div>
                </article>
                <article class="is-time">
                    <span><i class="fa-solid fa-clock"></i></span>
                    <div><strong><?= (int) ($testRun['duration_ms'] ?? 0) ?> ms</strong><small><?= htmlspecialchars($lastRunText, ENT_QUOTES, 'UTF-8') ?></small></div>
                </article>
            </section>

            <section class="system-tests-control-panel">
                <div>
                    <span class="settings-eyebrow">Ejecución segura</span>
                    <h3>Comprobar todos los componentes</h3>
                    <p>Las pruebas crean recursos temporales, validan su contenido y los eliminan inmediatamente.</p>
                </div>
                <form method="post" action="/helpdesk-php/admin-system-tests.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="run_all">
                    <button class="btn-primary system-tests-run-all" type="submit">
                        <i class="fa-solid fa-play"></i>
                        <span>Ejecutar todas las pruebas</span>
                    </button>
                </form>
            </section>

            <?php foreach ($groupedTests as $groupTitle => $tests): ?>
                <section class="system-tests-group">
                    <div class="system-tests-group-heading">
                        <div>
                            <span><?= htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8') ?></span>
                            <h3><?= htmlspecialchars((string) ($groupDescriptions[$groupTitle] ?? 'Comprobaciones controladas del sistema.'), ENT_QUOTES, 'UTF-8') ?></h3>
                        </div>
                        <small><?= count($tests) ?> prueba<?= count($tests) === 1 ? '' : 's' ?></small>
                    </div>

                    <div class="system-tests-grid">
                        <?php foreach ($tests as $test): ?>
                            <?php
                            $key = (string) ($test['key'] ?? '');
                            $result = is_array($results[$key] ?? null) ? $results[$key] : null;
                            $status = $result ? (string) ($result['status'] ?? 'error') : 'not-run';
                            $detail = $result
                                ? (string) ($result['detail'] ?? 'Sin detalle disponible.')
                                : 'Esta comprobación todavía no se ha ejecutado.';
                            $durationMs = $result ? (int) ($result['duration_ms'] ?? 0) : null;
                            ?>
                            <article class="system-test-card is-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="system-test-card-heading">
                                    <span class="system-test-icon"><i class="fa-solid <?= htmlspecialchars((string) ($test['icon'] ?? 'fa-vial'), ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                    <div>
                                        <strong><?= htmlspecialchars((string) ($test['label'] ?? 'Prueba'), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <p><?= htmlspecialchars((string) ($test['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <span class="system-test-status is-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid <?= htmlspecialchars($statusIcons[$status] ?? 'fa-circle-minus', ENT_QUOTES, 'UTF-8') ?>"></i>
                                        <?= htmlspecialchars($statusLabels[$status] ?? 'Sin ejecutar', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <div class="system-test-result">
                                    <span><i class="fa-solid fa-terminal"></i></span>
                                    <div>
                                        <strong>Resultado</strong>
                                        <p><?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>

                                <div class="system-test-footer">
                                    <small>
                                        <?php if ($result): ?>
                                            <?= $durationMs ?> ms · <?= htmlspecialchars((string) ($result['ran_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        <?php else: ?>
                                            Operación temporal y aislada
                                        <?php endif; ?>
                                    </small>
                                    <form method="post" action="/helpdesk-php/admin-system-tests.php">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="run_single">
                                        <input type="hidden" name="test_key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn-secondary system-test-run-button" type="submit">
                                            <i class="fa-solid fa-rotate"></i>
                                            <span><?= $result ? 'Ejecutar nuevamente' : 'Ejecutar prueba' ?></span>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="system-tests-safety-note">
                <span><i class="fa-solid fa-shield-halved"></i></span>
                <div>
                    <strong>Pruebas sin impacto operativo</strong>
                    <p>No se crean tickets, usuarios ni respaldos reales. Los archivos temporales se eliminan al finalizar y no se muestran credenciales en pantalla.</p>
                </div>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
