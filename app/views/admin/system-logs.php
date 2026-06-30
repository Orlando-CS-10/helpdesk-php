<?php
$technicalLogsReady = (bool) ($technicalLogsReady ?? false);
$filters = is_array($filters ?? null) ? $filters : [];
$technicalLogs = is_array($technicalLogs ?? null) ? $technicalLogs : [];
$technicalSummary = is_array($technicalSummary ?? null)
    ? $technicalSummary
    : ['total' => 0, 'info' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0];
$technicalModules = is_array($technicalModules ?? null) ? $technicalModules : [];
$technicalUsers = is_array($technicalUsers ?? null) ? $technicalUsers : [];
$totalLogs = (int) ($totalLogs ?? 0);
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$csrfToken = (string) ($csrfToken ?? '');

$levelLabels = [
    'info' => 'Información',
    'warning' => 'Advertencia',
    'error' => 'Error',
    'critical' => 'Crítico',
];
$levelIcons = [
    'info' => 'fa-circle-info',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-xmark',
    'critical' => 'fa-skull-crossbones',
];

$buildQuery = static function (array $changes = []) use ($filters): string {
    $query = array_merge($filters, $changes);
    $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== 0 && $value !== null);
    return http_build_query($query);
};

$title = 'Registros técnicos';
$activePage = 'system-logs';
$pageTitle = 'Registros técnicos';
$pageSubtitle = 'Consulta incidencias internas organizadas por módulo, gravedad y fecha.';

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
                    <span class="settings-eyebrow">Seguimiento</span>
                    <h2>Bitácora técnica de la plataforma</h2>
                    <p>Revisa advertencias y errores sin mezclar esta información con la auditoría de seguridad ni con el historial de mantenimiento.</p>
                </div>
                <div class="system-tool-page-icon" aria-hidden="true">
                    <i class="fa-solid fa-file-waveform"></i>
                </div>
            </section>

            <?php if (!$technicalLogsReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>La tabla de registros técnicos no está disponible</strong>
                        <p>Ejecuta <code>database/system_tools.sql</code> en phpMyAdmin para habilitar esta herramienta.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-logs-summary" aria-label="Resumen de registros técnicos">
                <article class="is-total">
                    <span><i class="fa-solid fa-layer-group"></i></span>
                    <div><strong><?= (int) ($technicalSummary['total'] ?? 0) ?></strong><small>registros encontrados</small></div>
                </article>
                <article class="is-warning">
                    <span><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div><strong><?= (int) ($technicalSummary['warning'] ?? 0) ?></strong><small>advertencias</small></div>
                </article>
                <article class="is-error">
                    <span><i class="fa-solid fa-circle-xmark"></i></span>
                    <div><strong><?= (int) ($technicalSummary['error'] ?? 0) ?></strong><small>errores</small></div>
                </article>
                <article class="is-critical">
                    <span><i class="fa-solid fa-skull-crossbones"></i></span>
                    <div><strong><?= (int) ($technicalSummary['critical'] ?? 0) ?></strong><small>críticos</small></div>
                </article>
            </section>

            <section class="system-logs-filter-panel">
                <div class="system-logs-filter-heading">
                    <div>
                        <span class="settings-eyebrow">Filtros</span>
                        <h3>Encontrar un evento técnico</h3>
                        <p>Busca por descripción, módulo, usuario o contenido contextual.</p>
                    </div>
                    <a class="btn-secondary" href="/helpdesk-php/admin-system-logs.php">
                        <i class="fa-solid fa-filter-circle-xmark"></i>
                        <span>Limpiar filtros</span>
                    </a>
                </div>

                <form class="system-logs-filters" method="get" action="/helpdesk-php/admin-system-logs.php">
                    <div class="form-group system-logs-search-field">
                        <label for="technical_log_q">Buscar</label>
                        <input id="technical_log_q" name="q" type="search" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Mensaje, módulo o usuario">
                    </div>
                    <div class="form-group">
                        <label for="technical_log_level">Gravedad</label>
                        <select id="technical_log_level" name="level">
                            <option value="">Todas</option>
                            <?php foreach ($levelLabels as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['level'] ?? '') === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="technical_log_module">Módulo</label>
                        <select id="technical_log_module" name="module">
                            <option value="">Todos</option>
                            <?php foreach ($technicalModules as $module): ?>
                                <option value="<?= htmlspecialchars((string) $module, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['module'] ?? '') === (string) $module ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $module, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="technical_log_user">Usuario</label>
                        <select id="technical_log_user" name="user_id">
                            <option value="0">Todos</option>
                            <?php foreach ($technicalUsers as $logUser): ?>
                                <option value="<?= (int) ($logUser['id'] ?? 0) ?>" <?= (int) ($filters['user_id'] ?? 0) === (int) ($logUser['id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($logUser['name'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="technical_log_date_from">Desde</label>
                        <input id="technical_log_date_from" name="date_from" type="date" value="<?= htmlspecialchars((string) ($filters['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="technical_log_date_to">Hasta</label>
                        <input id="technical_log_date_to" name="date_to" type="date" value="<?= htmlspecialchars((string) ($filters['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="system-logs-filter-actions">
                        <button class="btn-primary" type="submit">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Aplicar filtros</span>
                        </button>
                        <?php if ($technicalLogsReady && $totalLogs > 0): ?>
                            <a class="btn-secondary" href="/helpdesk-php/export-system-logs.php?<?= htmlspecialchars($buildQuery(), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-file-csv"></i>
                                <span>Exportar CSV</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="system-tool-panel system-logs-panel">
                <div class="system-tool-panel-heading">
                    <div>
                        <span>Resultados</span>
                        <h3>Eventos técnicos registrados</h3>
                    </div>
                    <small><?= $totalLogs ?> resultado<?= $totalLogs === 1 ? '' : 's' ?> · 15 por página</small>
                </div>

                <?php if (!$technicalLogs): ?>
                    <div class="system-tool-empty">
                        <i class="fa-solid fa-file-circle-check"></i>
                        <strong>No hay registros para mostrar</strong>
                        <p>El sistema no encontró eventos con los filtros actuales. Las advertencias o errores de las pruebas aparecerán aquí automáticamente.</p>
                    </div>
                <?php else: ?>
                    <div class="system-logs-list">
                        <?php foreach ($technicalLogs as $log): ?>
                            <?php
                            $level = (string) ($log['level'] ?? 'info');
                            $context = is_array($log['context'] ?? null) ? $log['context'] : [];
                            $request = is_array($context['request'] ?? null) ? $context['request'] : [];
                            $createdAt = strtotime((string) ($log['created_at'] ?? ''));
                            $contextJson = $context
                                ? json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                : '';
                            ?>
                            <article class="system-log-entry is-<?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="system-log-level-icon">
                                    <i class="fa-solid <?= htmlspecialchars($levelIcons[$level] ?? 'fa-circle-info', ENT_QUOTES, 'UTF-8') ?>"></i>
                                </span>
                                <div class="system-log-main">
                                    <div class="system-log-heading">
                                        <div>
                                            <span class="system-log-level is-<?= htmlspecialchars($level, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($levelLabels[$level] ?? 'Información', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <strong><?= htmlspecialchars((string) ($log['message'] ?? 'Evento técnico'), ENT_QUOTES, 'UTF-8') ?></strong>
                                        </div>
                                        <span class="system-log-module"><?= htmlspecialchars((string) ($log['module'] ?? 'system'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>

                                    <div class="system-log-meta">
                                        <span><i class="fa-regular fa-calendar"></i><?= $createdAt ? date('d/m/Y H:i:s', $createdAt) : 'Fecha no disponible' ?></span>
                                        <span><i class="fa-regular fa-user"></i><?= htmlspecialchars((string) ($log['user_name'] ?? 'Sistema'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($request['ip'])): ?>
                                            <span><i class="fa-solid fa-network-wired"></i><?= htmlspecialchars((string) $request['ip'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($contextJson !== ''): ?>
                                        <details class="system-log-details">
                                            <summary><i class="fa-solid fa-code"></i>Ver detalles técnicos</summary>
                                            <pre><?= htmlspecialchars($contextJson, ENT_QUOTES, 'UTF-8') ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                    <nav class="system-tool-pagination" aria-label="Paginación de registros técnicos">
                        <?php if ($page > 1): ?>
                            <a href="?<?= htmlspecialchars($buildQuery(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++):
                        ?>
                            <a class="<?= $pageNumber === $page ? 'active' : '' ?>" href="?<?= htmlspecialchars($buildQuery(['page' => $pageNumber]), ENT_QUOTES, 'UTF-8') ?>"><?= $pageNumber ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= htmlspecialchars($buildQuery(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página siguiente"><i class="fa-solid fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>

            <section class="system-logs-cleanup-panel">
                <div>
                    <span class="settings-eyebrow">Conservación</span>
                    <h3>Eliminar registros técnicos antiguos</h3>
                    <p>Esta acción no elimina tickets, usuarios, auditoría de seguridad ni historial de mantenimiento.</p>
                </div>
                <form method="post" action="/helpdesk-php/cleanup-system-logs.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="technical_log_days">Antigüedad mínima</label>
                        <select id="technical_log_days" name="days" required>
                            <option value="30">Más de 30 días</option>
                            <option value="90" selected>Más de 90 días</option>
                            <option value="180">Más de 180 días</option>
                            <option value="365">Más de 1 año</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="technical_log_password">Contraseña del administrador</label>
                        <input id="technical_log_password" name="admin_password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="form-group">
                        <label for="technical_log_confirmation">Escribe ELIMINAR</label>
                        <input id="technical_log_confirmation" name="confirmation" type="text" autocomplete="off" placeholder="ELIMINAR" required>
                    </div>
                    <button class="system-danger-button is-large" type="submit">
                        <i class="fa-solid fa-trash-can"></i>
                        <span>Eliminar antiguos</span>
                    </button>
                </form>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
