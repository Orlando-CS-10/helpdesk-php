<?php
$contact = $contact ?? [];
$logs = $logs ?? [];
$filters = $filters ?? [];
$eventTypes = $eventTypes ?? [];
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$totalLogs = (int) ($totalLogs ?? 0);

if (!function_exists('securityTraceSafe')) {
    function securityTraceSafe(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('securityTraceDate')) {
    function securityTraceDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') return 'Sin actividad';
        $time = strtotime($value);
        return $time ? date('d/m/Y H:i', $time) : $value;
    }
}
if (!function_exists('securityTraceInitials')) {
    function securityTraceInitials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $value = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $value .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
        }
        return function_exists('mb_strtoupper') ? mb_strtoupper($value ?: 'EM', 'UTF-8') : strtoupper($value ?: 'EM');
    }
}
if (!function_exists('securityTraceAssetUrl')) {
    function securityTraceAssetUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') return '';
        if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, '/helpdesk-php/')) return $path;
        return str_starts_with($path, '/') ? '/helpdesk-php' . $path : '/helpdesk-php/' . ltrim($path, '/');
    }
}
if (!function_exists('securityTraceCompanyDisplayName')) {
    function securityTraceCompanyDisplayName(array $company): string
    {
        $trade = trim((string) ($company['trade_name'] ?? ''));
        return $trade !== '' ? $trade : trim((string) ($company['business_name'] ?? 'Empresa'));
    }
}

if (!function_exists('securityContactTraceUrl')) {
    function securityContactTraceUrl(array $changes = []): string
    {
        $query = array_merge($_GET, $changes);
        foreach ($query as $key => $value) { if ($value === '' || $value === null) unset($query[$key]); }
        return '/helpdesk-php/admin-security-contact.php?' . http_build_query($query);
    }
}
$companyName = securityTraceCompanyDisplayName($contact);
$photo = securityTraceAssetUrl($contact['profile_photo'] ?? null);
$title = 'Trazabilidad de ' . ($contact['name'] ?? 'Contacto');
$activePage = 'system-security';
$pageTitle = 'Trazabilidad del contacto';
$pageSubtitle = 'Historial de accesos y eventos de seguridad asociados a una sola persona.';
$adminTopbarButtons = [[ 'href' => '/helpdesk-php/admin-security-company.php?company_id=' . (int) ($contact['company_id'] ?? 0), 'class' => 'btn-secondary', 'text' => 'Volver a Contactos' ]];
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>
    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>
        <main class="admin-content admin-settings-content system-security-content">
            <nav class="system-security-breadcrumb"><a href="/helpdesk-php/admin-system-security.php">Seguridad</a><span>›</span><a href="/helpdesk-php/admin-security-companies.php">Empresas</a><span>›</span><a href="/helpdesk-php/admin-security-company.php?company_id=<?= (int) ($contact['company_id'] ?? 0) ?>"><?= securityTraceSafe($companyName) ?></a><span>›</span><strong><?= securityTraceSafe($contact['name'] ?? 'Contacto') ?></strong></nav>
            <section class="system-security-contact-hero"><span class="system-security-contact-hero-avatar <?= $photo !== '' ? 'has-photo' : '' ?>"><?php if ($photo !== ''): ?><img src="<?= securityTraceSafe($photo) ?>" alt="Foto de <?= securityTraceSafe($contact['name'] ?? 'Contacto') ?>"><?php else: ?><?= securityTraceSafe(securityTraceInitials((string) ($contact['name'] ?? 'Contacto'))) ?><?php endif; ?></span><div><span class="settings-eyebrow">Contacto de <?= securityTraceSafe($companyName) ?></span><h2><?= securityTraceSafe($contact['name'] ?? 'Contacto') ?></h2><p><?= securityTraceSafe($contact['email'] ?? '') ?><?php if (!empty($contact['position'])): ?> · <?= securityTraceSafe($contact['position']) ?><?php endif; ?></p></div><div class="system-security-contact-hero-total"><strong><?= $totalLogs ?></strong><span>eventos encontrados</span></div></section>
            <section class="system-security-card system-security-filter-card">
                <form method="GET" class="system-security-trace-filters"><input type="hidden" name="user_id" value="<?= (int) ($contact['user_id'] ?? 0) ?>"><div class="form-group trace-search"><label for="search">Buscar</label><input id="search" name="search" value="<?= securityTraceSafe($filters['search'] ?? '') ?>" placeholder="Descripción, evento, actor o IP"></div><div class="form-group"><label for="severity">Gravedad</label><select id="severity" name="severity"><option value="">Todas</option><option value="info" <?= ($filters['severity'] ?? '') === 'info' ? 'selected' : '' ?>>Informativa</option><option value="warning" <?= ($filters['severity'] ?? '') === 'warning' ? 'selected' : '' ?>>Advertencia</option><option value="critical" <?= ($filters['severity'] ?? '') === 'critical' ? 'selected' : '' ?>>Crítica</option></select></div><div class="form-group"><label for="event_type">Evento</label><select id="event_type" name="event_type"><option value="">Todos</option><?php foreach ($eventTypes as $type): ?><option value="<?= securityTraceSafe($type) ?>" <?= ($filters['event_type'] ?? '') === $type ? 'selected' : '' ?>><?= securityTraceSafe($type) ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="date_from">Desde</label><input type="date" id="date_from" name="date_from" value="<?= securityTraceSafe($filters['date_from'] ?? '') ?>"></div><div class="form-group"><label for="date_to">Hasta</label><input type="date" id="date_to" name="date_to" value="<?= securityTraceSafe($filters['date_to'] ?? '') ?>"></div><div class="system-security-filter-actions"><button class="btn-primary" type="submit">Filtrar</button><a class="btn-secondary" href="/helpdesk-php/admin-security-contact.php?user_id=<?= (int) ($contact['user_id'] ?? 0) ?>">Limpiar</a></div></form>
            </section>
            <section class="system-security-card"><div class="system-security-section-heading"><div><span>Historial individual</span><h3>Eventos del contacto</h3><p>Los resultados no incluyen eventos de otros contactos ni acciones globales.</p></div><strong><?= $totalLogs ?> registros</strong></div><div class="system-security-timeline"><?php if (!$logs): ?><div class="system-security-empty">No existen eventos con los filtros seleccionados.</div><?php endif; ?><?php foreach ($logs as $log): ?><article class="system-security-timeline-item severity-<?= securityTraceSafe($log['severity'] ?? 'info') ?>"><span class="system-security-timeline-dot"><i class="fa-solid fa-shield-halved"></i></span><div class="system-security-timeline-body"><div class="system-security-log-title"><strong><?= securityTraceSafe($log['description'] ?? 'Evento de seguridad') ?></strong><span><?= securityTraceSafe($log['event_type'] ?? '') ?></span></div><div class="system-security-timeline-meta"><span><i class="fa-regular fa-clock"></i><?= securityTraceSafe(securityTraceDate($log['created_at'] ?? null)) ?></span><span><i class="fa-solid fa-user-shield"></i>Actor: <?= securityTraceSafe($log['actor_name'] ?? 'Sistema') ?></span><span><i class="fa-solid fa-network-wired"></i><?= securityTraceSafe($log['ip_address'] ?? 'Sin IP') ?></span></div></div></article><?php endforeach; ?></div><?php if ($totalPages > 1): ?><nav class="system-security-pagination"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="<?= securityTraceSafe(securityContactTraceUrl(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?></section>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
