<?php
$company = $company ?? [];
$contacts = $contacts ?? [];
$search = (string) ($search ?? '');
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$totalContacts = (int) ($totalContacts ?? 0);

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

if (!function_exists('securityCompanyUrl')) {
    function securityCompanyUrl(array $changes = []): string
    {
        $query = array_merge($_GET, $changes);
        foreach ($query as $key => $value) { if ($value === '' || $value === null) unset($query[$key]); }
        return '/helpdesk-php/admin-security-company.php?' . http_build_query($query);
    }
}
$companyName = securityTraceCompanyDisplayName($company);
$companyLogo = securityTraceAssetUrl($company['logo_path'] ?? null);
$title = 'Contactos de ' . $companyName;
$activePage = 'system-security';
$pageTitle = 'Contactos de la empresa';
$pageSubtitle = 'Selecciona un contacto para consultar su trazabilidad individual.';
$adminTopbarButtons = [[ 'href' => '/helpdesk-php/admin-security-companies.php', 'class' => 'btn-secondary', 'text' => 'Volver a Empresas' ]];
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>
    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>
        <main class="admin-content admin-settings-content system-security-content">
            <nav class="system-security-breadcrumb"><a href="/helpdesk-php/admin-system-security.php">Seguridad</a><span>›</span><a href="/helpdesk-php/admin-security-companies.php">Empresas</a><span>›</span><strong><?= securityTraceSafe($companyName) ?></strong></nav>
            <section class="system-security-company-hero">
                <span class="system-security-company-hero-logo <?= $companyLogo !== '' ? 'has-logo' : '' ?>"><?php if ($companyLogo !== ''): ?><img src="<?= securityTraceSafe($companyLogo) ?>" alt="Logo de <?= securityTraceSafe($companyName) ?>"><?php else: ?><?= securityTraceSafe(securityTraceInitials($companyName)) ?><?php endif; ?></span>
                <div><span class="settings-eyebrow">Empresa cliente</span><h2><?= securityTraceSafe($companyName) ?></h2><p><?= securityTraceSafe($company['business_name'] ?? '') ?><?php if (!empty($company['ruc'])): ?> · RUC <?= securityTraceSafe($company['ruc']) ?><?php endif; ?></p></div>
                <div class="system-security-company-hero-stats"><span><strong><?= (int) ($company['contact_count'] ?? 0) ?></strong><small>Contactos</small></span><span><strong><?= (int) ($company['event_count'] ?? 0) ?></strong><small>Eventos</small></span></div>
            </section>
            <section class="system-security-card system-security-filter-card">
                <form method="GET" class="system-security-search-form"><input type="hidden" name="company_id" value="<?= (int) ($company['company_id'] ?? 0) ?>"><div class="form-group"><label for="search">Buscar contacto</label><input id="search" name="search" value="<?= securityTraceSafe($search) ?>" placeholder="Nombre, correo, teléfono o cargo"></div><button class="btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button><?php if ($search !== ''): ?><a class="btn-secondary" href="/helpdesk-php/admin-security-company.php?company_id=<?= (int) ($company['company_id'] ?? 0) ?>">Limpiar</a><?php endif; ?></form>
            </section>
            <section class="system-security-card">
                <div class="system-security-section-heading"><div><span>Contactos</span><h3>Personas vinculadas a la empresa</h3><p>Cada historial contiene únicamente eventos asociados a ese contacto.</p></div><strong><?= $totalContacts ?> contactos</strong></div>
                <div class="system-security-contact-grid">
                    <?php if (!$contacts): ?><div class="system-security-empty">No se encontraron contactos en esta empresa.</div><?php endif; ?>
                    <?php foreach ($contacts as $contactItem): ?>
                        <?php $photo = securityTraceAssetUrl($contactItem['profile_photo'] ?? null); ?>
                        <article class="system-security-contact-card">
                            <div class="system-security-contact-head"><span class="system-security-contact-avatar <?= $photo !== '' ? 'has-photo' : '' ?>"><?php if ($photo !== ''): ?><img src="<?= securityTraceSafe($photo) ?>" alt="Foto de <?= securityTraceSafe($contactItem['name'] ?? 'Contacto') ?>"><?php else: ?><?= securityTraceSafe(securityTraceInitials((string) ($contactItem['name'] ?? 'Contacto'))) ?><?php endif; ?></span><span class="system-security-contact-state <?= !empty($contactItem['status']) ? 'is-active' : '' ?>"><?= !empty($contactItem['status']) ? 'Activo' : 'Inactivo' ?></span></div>
                            <div class="system-security-contact-copy"><strong><?= securityTraceSafe($contactItem['name'] ?? 'Contacto') ?></strong><span><?= securityTraceSafe($contactItem['position'] ?: 'Sin cargo registrado') ?></span><small><?= securityTraceSafe($contactItem['email'] ?? '') ?></small></div>
                            <div class="system-security-contact-metrics"><span><strong><?= (int) ($contactItem['event_count'] ?? 0) ?></strong><small>Eventos</small></span><span class="warning"><strong><?= (int) ($contactItem['warning_count'] ?? 0) ?></strong><small>Alertas</small></span><span class="critical"><strong><?= (int) ($contactItem['critical_count'] ?? 0) ?></strong><small>Críticos</small></span></div>
                            <small class="system-security-contact-last">Última actividad: <?= securityTraceSafe(securityTraceDate($contactItem['last_activity'] ?? null)) ?></small>
                            <a class="system-security-contact-action" href="/helpdesk-php/admin-security-contact.php?user_id=<?= (int) ($contactItem['user_id'] ?? 0) ?>">Ver trazabilidad <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?><nav class="system-security-pagination"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="<?= securityTraceSafe(securityCompanyUrl(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
            </section>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
