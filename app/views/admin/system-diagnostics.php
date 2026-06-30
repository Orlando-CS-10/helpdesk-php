<?php
$diagnostics = $diagnostics ?? ['checks' => [], 'summary' => ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => 0], 'ran_at' => null];
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$csrfToken = (string) ($csrfToken ?? '');

$title = 'Diagnóstico del sistema';
$activePage = 'system-diagnostics';
$pageTitle = 'Diagnóstico del sistema';
$pageSubtitle = 'Comprueba el estado técnico de la plataforma sin modificar información.';
require_once __DIR__ . '/../layouts/header.php';

$groups = [];
foreach ($diagnostics['checks'] as $check) {
    $groups[$check['group']][] = $check;
}
?>
<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>
    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>
        <main class="admin-content admin-settings-content system-tools-content">
            <section class="system-tool-page-hero">
                <div>
                    <a href="/helpdesk-php/admin-system-tools.php" class="system-tool-back"><i class="fa-solid fa-arrow-left"></i> Volver al centro</a>
                    <span class="settings-eyebrow">Supervisión</span>
                    <h2>Radiografía técnica de la plataforma</h2>
                    <p>Revisa conexión, extensiones, permisos y almacenamiento. Esta herramienta solo consulta información.</p>
                </div>
                <div class="system-tool-page-icon"><i class="fa-solid fa-stethoscope"></i></div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert"><span><i class="fa-solid fa-database"></i></span><div><strong>Historial no disponible</strong><p>El diagnóstico funciona, pero debes ejecutar <code>database/system_tools.sql</code> para registrar las ejecuciones.</p></div></section>
            <?php endif; ?>

            <section class="system-diagnostic-summary">
                <article class="ok"><strong><?= (int) $diagnostics['summary']['ok'] ?></strong><span>Correctos</span></article>
                <article class="warning"><strong><?= (int) $diagnostics['summary']['warning'] ?></strong><span>Advertencias</span></article>
                <article class="error"><strong><?= (int) $diagnostics['summary']['error'] ?></strong><span>Errores</span></article>
                <form method="POST" action="/helpdesk-php/admin-system-diagnostics.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn-primary" type="submit"><i class="fa-solid fa-rotate"></i> Ejecutar diagnóstico</button>
                    <small>Última lectura: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $diagnostics['ran_at'])), ENT_QUOTES, 'UTF-8') ?></small>
                </form>
            </section>

            <?php foreach ($groups as $group => $checks): ?>
                <section class="system-tool-panel">
                    <div class="system-tool-panel-heading"><div><span><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></span><h3><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></h3></div><small><?= count($checks) ?> comprobaciones</small></div>
                    <div class="system-diagnostic-list">
                        <?php foreach ($checks as $check): ?>
                            <article>
                                <span class="system-diagnostic-state <?= htmlspecialchars($check['status'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid <?= $check['status'] === 'ok' ? 'fa-check' : ($check['status'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-xmark') ?>"></i></span>
                                <div><strong><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($check['detail'], ENT_QUOTES, 'UTF-8') ?></small></div>
                                <em class="<?= htmlspecialchars($check['status'], ENT_QUOTES, 'UTF-8') ?>"><?= $check['status'] === 'ok' ? 'Correcto' : ($check['status'] === 'warning' ? 'Revisar' : 'Error') ?></em>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
