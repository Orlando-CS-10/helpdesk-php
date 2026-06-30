<?php
$systemToolsReady = (bool) ($systemToolsReady ?? false);
$cleanupAnalysis = $cleanupAnalysis ?? [];
$csrfToken = (string) ($csrfToken ?? '');
$totalCount = array_sum(array_map(static fn ($item) => (int) ($item['count'] ?? 0), $cleanupAnalysis));
$totalBytes = array_sum(array_map(static fn ($item) => (int) ($item['bytes'] ?? 0), $cleanupAnalysis));

$title = 'Limpieza y mantenimiento';
$activePage = 'system-cleanup';
$pageTitle = 'Limpieza y mantenimiento';
$pageSubtitle = 'Analiza primero y elimina únicamente elementos seguros y seleccionados.';
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
                    <h2>Una escoba con límites claros</h2>
                    <p>El sistema detecta residuos sin tocar tickets, mensajes ni adjuntos válidos.</p>
                </div>
                <div class="system-tool-page-icon"><i class="fa-solid fa-broom"></i></div>
            </section>

            <?php if (!$systemToolsReady): ?>
                <section class="settings-setup-alert"><span><i class="fa-solid fa-database"></i></span><div><strong>Falta preparar la base de datos</strong><p>Ejecuta <code>database/system_tools.sql</code> antes de realizar una limpieza.</p></div></section>
            <?php endif; ?>

            <section class="system-cleanup-overview">
                <div><span><i class="fa-solid fa-list-check"></i></span><strong><?= $totalCount ?></strong><small>elementos detectados</small></div>
                <div><span><i class="fa-solid fa-hard-drive"></i></span><strong><?= htmlspecialchars(systemToolsFormatBytes($totalBytes), ENT_QUOTES, 'UTF-8') ?></strong><small>espacio potencial</small></div>
                <div class="safe"><span><i class="fa-solid fa-shield-halved"></i></span><strong>Protegidos</strong><small>tickets y archivos válidos</small></div>
            </section>

            <form method="POST" action="/helpdesk-php/run-system-cleanup.php" class="system-tool-panel system-cleanup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="system-tool-panel-heading"><div><span>Análisis previo</span><h3>Selecciona qué deseas limpiar</h3></div><small>Nada se elimina automáticamente</small></div>

                <div class="system-cleanup-grid">
                    <?php foreach ($cleanupAnalysis as $key => $item): ?>
                        <label class="system-cleanup-item <?= (int) $item['count'] === 0 ? 'is-empty' : '' ?>">
                            <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= (int) $item['count'] === 0 ? 'disabled' : '' ?>>
                            <span class="system-cleanup-check"><i class="fa-solid fa-check"></i></span>
                            <div><strong><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= (int) $item['count'] ?> elementos · <?= htmlspecialchars(systemToolsFormatBytes((int) $item['bytes']), ENT_QUOTES, 'UTF-8') ?></small></div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="system-cleanup-confirmation">
                    <div><strong>Confirmación requerida</strong><p>Escribe <b>LIMPIAR</b>. La operación se registrará en el historial técnico.</p></div>
                    <input type="text" name="confirmation" autocomplete="off" placeholder="LIMPIAR" required>
                    <button class="system-danger-button is-large" type="submit" <?= (!$systemToolsReady || $totalCount === 0) ? 'disabled' : '' ?>><i class="fa-solid fa-broom"></i> Ejecutar limpieza</button>
                </div>
            </form>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
