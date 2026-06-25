<?php
$systemSecurityReady = (bool) ($systemSecurityReady ?? false);
$securityAuditFilters = $securityAuditFilters ?? [];
$securityAuditResult = $securityAuditResult ?? [
    'items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10,
    'total_pages' => 1, 'from' => 0, 'to' => 0,
];
$securityAuditEventTypes = $securityAuditEventTypes ?? [];
$securityAuditUsers = $securityAuditUsers ?? [];

$title = 'Historial de seguridad';
$activePage = 'system-security';
$pageTitle = 'Historial de seguridad';
$pageSubtitle = 'Consulta y filtra todos los eventos registrados por el sistema.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-system-security.php',
        'class' => 'btn-secondary',
        'text' => 'Volver a Seguridad',
    ],
];

if (!function_exists('securityAuditSafe')) {
    function securityAuditSafe(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('securityAuditDate')) {
    function securityAuditDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'Sin registro';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
    }
}

if (!function_exists('securityAuditEventLabel')) {
    function securityAuditEventLabel(string $eventType): string
    {
        $labels = [
            'LOGIN_SUCCESS' => 'Inicio de sesión correcto',
            'LOGIN_FAILED_PASSWORD' => 'Contraseña incorrecta',
            'LOGIN_FAILED_EMAIL' => 'Correo no registrado',
            'LOGOUT' => 'Cierre de sesión',
            'PASSWORD_CHANGED_BY_USER' => 'Contraseña actualizada',
            'PASSWORD_RESET_BY_ADMIN' => 'Contraseña restablecida',
            'FORCE_PASSWORD_CHANGE_ALL' => 'Cambio global forzado',
            'ACCOUNT_LOCKED' => 'Cuenta bloqueada',
            'ACCOUNT_UNLOCKED' => 'Cuenta desbloqueada',
            'SECURITY_SETTINGS_UPDATED' => 'Políticas actualizadas',
            'SESSION_REVOKED' => 'Sesión cerrada',
        ];

        return $labels[$eventType] ?? ucwords(strtolower(str_replace('_', ' ', $eventType)));
    }
}

if (!function_exists('securityAuditPageUrl')) {
    function securityAuditPageUrl(array $filters, int $page): string
    {
        $params = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'event_type' => trim((string) ($filters['event_type'] ?? '')),
            'severity' => trim((string) ($filters['severity'] ?? '')),
            'user_id' => (int) ($filters['user_id'] ?? 0),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'page' => max(1, $page),
        ];

        $params = array_filter($params, static fn($value) => $value !== '' && $value !== 0 && $value !== 1);
        $query = http_build_query($params);

        return '/helpdesk-php/admin-security-audit.php' . ($query !== '' ? '?' . $query : '');
    }
}

$items = $securityAuditResult['items'] ?? [];
$total = (int) ($securityAuditResult['total'] ?? 0);
$currentPage = max(1, (int) ($securityAuditResult['page'] ?? 1));
$totalPages = max(1, (int) ($securityAuditResult['total_pages'] ?? 1));
$from = (int) ($securityAuditResult['from'] ?? 0);
$to = (int) ($securityAuditResult['to'] ?? 0);

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-settings-content system-security-content security-audit-content">
            <section class="system-security-hero security-audit-hero">
                <div>
                    <span class="settings-eyebrow">Trazabilidad completa</span>
                    <h2>Historial de eventos de seguridad</h2>
                    <p>Investiga accesos, cambios de contraseña, bloqueos y acciones administrativas sin recargar la pantalla principal.</p>
                </div>

                <div class="security-audit-hero-summary" aria-label="Cantidad total de eventos">
                    <strong><?= $total ?></strong>
                    <span>eventos</span>
                </div>
            </section>

            <?php if (!$systemSecurityReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Falta preparar la base de datos</strong>
                        <p>Ejecuta <code>database/system_security.sql</code> en phpMyAdmin y recarga la página.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-security-card security-audit-filter-card">
                <div class="system-security-section-heading">
                    <div>
                        <span>Filtros</span>
                        <h3>Encuentra un evento específico</h3>
                        <p>Combina búsqueda, tipo, gravedad, usuario y rango de fechas.</p>
                    </div>
                    <?php if (array_filter($securityAuditFilters, static fn($value) => $value !== '' && $value !== 0)): ?>
                        <a href="/helpdesk-php/admin-security-audit.php" class="security-audit-clear-link">
                            <i class="fa-solid fa-eraser"></i> Limpiar filtros
                        </a>
                    <?php endif; ?>
                </div>

                <form method="GET" action="/helpdesk-php/admin-security-audit.php" class="security-audit-filter-form">
                    <div class="form-group security-audit-search-field">
                        <label for="audit_q">Buscar</label>
                        <input
                            type="search"
                            id="audit_q"
                            name="q"
                            maxlength="120"
                            value="<?= securityAuditSafe($securityAuditFilters['q'] ?? '') ?>"
                            placeholder="Descripción, evento, usuario, actor o IP">
                    </div>

                    <div class="form-group">
                        <label for="audit_event_type">Tipo de evento</label>
                        <select id="audit_event_type" name="event_type">
                            <option value="">Todos los tipos</option>
                            <?php foreach ($securityAuditEventTypes as $eventType): ?>
                                <option value="<?= securityAuditSafe($eventType) ?>" <?= ($securityAuditFilters['event_type'] ?? '') === $eventType ? 'selected' : '' ?>>
                                    <?= securityAuditSafe(securityAuditEventLabel((string) $eventType)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="audit_severity">Gravedad</label>
                        <select id="audit_severity" name="severity">
                            <option value="">Todas</option>
                            <option value="info" <?= ($securityAuditFilters['severity'] ?? '') === 'info' ? 'selected' : '' ?>>Informativa</option>
                            <option value="warning" <?= ($securityAuditFilters['severity'] ?? '') === 'warning' ? 'selected' : '' ?>>Advertencia</option>
                            <option value="critical" <?= ($securityAuditFilters['severity'] ?? '') === 'critical' ? 'selected' : '' ?>>Crítica</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="audit_user_id">Usuario relacionado</label>
                        <select id="audit_user_id" name="user_id">
                            <option value="0">Todos los usuarios</option>
                            <?php foreach ($securityAuditUsers as $auditUser): ?>
                                <?php $auditUserId = (int) ($auditUser['id'] ?? 0); ?>
                                <option value="<?= $auditUserId ?>" <?= (int) ($securityAuditFilters['user_id'] ?? 0) === $auditUserId ? 'selected' : '' ?>>
                                    <?= securityAuditSafe(($auditUser['name'] ?? 'Usuario') . ' · ' . ($auditUser['email'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="audit_date_from">Desde</label>
                        <input type="date" id="audit_date_from" name="date_from" value="<?= securityAuditSafe($securityAuditFilters['date_from'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="audit_date_to">Hasta</label>
                        <input type="date" id="audit_date_to" name="date_to" value="<?= securityAuditSafe($securityAuditFilters['date_to'] ?? '') ?>">
                    </div>

                    <div class="security-audit-filter-actions">
                        <a href="/helpdesk-php/admin-security-audit.php" class="btn-secondary">Restablecer</a>
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-filter"></i>
                            Aplicar filtros
                        </button>
                    </div>
                </form>
            </section>

            <section class="system-security-card security-audit-results-card">
                <div class="system-security-section-heading security-audit-results-heading">
                    <div>
                        <span>Resultados</span>
                        <h3>Eventos registrados</h3>
                        <p>
                            <?php if ($total > 0): ?>
                                Mostrando <?= $from ?>–<?= $to ?> de <?= $total ?> registros.
                            <?php else: ?>
                                No se encontraron registros con los filtros seleccionados.
                            <?php endif; ?>
                        </p>
                    </div>
                    <strong>Página <?= $currentPage ?> de <?= $totalPages ?></strong>
                </div>

                <div class="system-security-log-list security-audit-log-list">
                    <?php if (!$items): ?>
                        <div class="system-security-empty security-audit-empty">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <strong>No encontramos eventos</strong>
                            <span>Prueba con otros filtros o elimina parte de la búsqueda.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $log): ?>
                            <?php $severity = in_array(($log['severity'] ?? ''), ['info', 'warning', 'critical'], true) ? $log['severity'] : 'info'; ?>
                            <article class="system-security-log-item security-audit-log-item severity-<?= securityAuditSafe($severity) ?>">
                                <span class="system-security-log-icon"><i class="fa-solid fa-shield"></i></span>
                                <div class="security-audit-log-copy">
                                    <div class="system-security-log-title">
                                        <strong><?= securityAuditSafe($log['description'] ?? 'Evento de seguridad') ?></strong>
                                        <span title="<?= securityAuditSafe($log['event_type'] ?? '') ?>">
                                            <?= securityAuditSafe(securityAuditEventLabel((string) ($log['event_type'] ?? ''))) ?>
                                        </span>
                                    </div>

                                    <div class="security-audit-log-meta">
                                        <span><i class="fa-regular fa-calendar"></i><?= securityAuditSafe(securityAuditDate($log['created_at'] ?? null)) ?></span>
                                        <span><i class="fa-regular fa-user"></i>Usuario: <?= securityAuditSafe($log['user_name'] ?? 'No identificado') ?></span>
                                        <span><i class="fa-solid fa-user-shield"></i>Actor: <?= securityAuditSafe($log['actor_name'] ?? 'Sistema') ?></span>
                                        <span><i class="fa-solid fa-network-wired"></i>IP: <?= securityAuditSafe($log['ip_address'] ?? 'Sin IP') ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="security-audit-pagination" aria-label="Paginación del historial de seguridad">
                        <a
                            href="<?= securityAuditSafe(securityAuditPageUrl($securityAuditFilters, $currentPage - 1)) ?>"
                            class="security-audit-page-button <?= $currentPage <= 1 ? 'is-disabled' : '' ?>"
                            <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            <i class="fa-solid fa-chevron-left"></i>
                            Anterior
                        </a>

                        <div class="security-audit-page-numbers">
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            ?>

                            <?php if ($startPage > 1): ?>
                                <a href="<?= securityAuditSafe(securityAuditPageUrl($securityAuditFilters, 1)) ?>" class="security-audit-page-number">1</a>
                                <?php if ($startPage > 2): ?><span>…</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                                <a
                                    href="<?= securityAuditSafe(securityAuditPageUrl($securityAuditFilters, $pageNumber)) ?>"
                                    class="security-audit-page-number <?= $pageNumber === $currentPage ? 'is-current' : '' ?>"
                                    <?= $pageNumber === $currentPage ? 'aria-current="page"' : '' ?>>
                                    <?= $pageNumber ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?><span>…</span><?php endif; ?>
                                <a href="<?= securityAuditSafe(securityAuditPageUrl($securityAuditFilters, $totalPages)) ?>" class="security-audit-page-number"><?= $totalPages ?></a>
                            <?php endif; ?>
                        </div>

                        <a
                            href="<?= securityAuditSafe(securityAuditPageUrl($securityAuditFilters, $currentPage + 1)) ?>"
                            class="security-audit-page-button <?= $currentPage >= $totalPages ? 'is-disabled' : '' ?>"
                            <?= $currentPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            Siguiente
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </nav>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
