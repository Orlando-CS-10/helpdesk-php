<?php
$companies = $companies ?? [];
$search = (string) ($search ?? '');
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$totalCompanies = (int) ($totalCompanies ?? 0);

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

if (!function_exists('securityCompaniesUrl')) {
    function securityCompaniesUrl(array $changes = []): string
    {
        $query = array_merge($_GET, $changes);
        foreach ($query as $key => $value) { if ($value === '' || $value === null) unset($query[$key]); }
        return '/helpdesk-php/admin-security-companies.php' . ($query ? '?' . http_build_query($query) : '');
    }
}
$title = 'Trazabilidad por empresa';
$activePage = 'system-security';
$pageTitle = 'Trazabilidad por empresa';
$pageSubtitle = 'Selecciona una empresa para revisar la actividad de seguridad de sus contactos.';
$adminTopbarButtons = [[ 'href' => '/helpdesk-php/admin-system-security.php', 'class' => 'btn-secondary', 'text' => 'Volver a Seguridad' ]];
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>
    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>
        <main class="admin-content admin-settings-content system-security-content">
            <nav class="system-security-breadcrumb"><a href="/helpdesk-php/admin-system-security.php">Seguridad</a><span>›</span><strong>Empresas</strong></nav>
            <section class="system-security-hero system-security-trace-hero">
                <div><span class="settings-eyebrow">Trazabilidad organizada</span><h2>Empresas cliente</h2><p>Elige una empresa para consultar sus contactos y acceder al historial individual de cada uno.</p></div>
                <div class="system-security-hero-icon"><i class="fa-solid fa-building-shield"></i></div>
            </section>
            <section class="system-security-card system-security-filter-card">
                <form method="GET" class="system-security-search-form">
                    <div class="form-group"><label for="search">Buscar empresa</label><input id="search" name="search" value="<?= securityTraceSafe($search) ?>" placeholder="Nombre, razón social o RUC"></div>
                    <button class="btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                    <?php if ($search !== ''): ?><a class="btn-secondary" href="/helpdesk-php/admin-security-companies.php">Limpiar</a><?php endif; ?>
                </form>
            </section>
            <section class="system-security-card">
                <div class="system-security-section-heading"><div><span>Directorio</span><h3>Empresas con contactos</h3><p>La cifra de eventos corresponde a la actividad de todos sus contactos.</p></div><strong><?= $totalCompanies ?> empresas</strong></div>
                <div class="system-security-company-grid">
                    <?php if (!$companies): ?><div class="system-security-empty">No se encontraron empresas.</div><?php endif; ?>
                    <?php foreach ($companies as $companyItem): ?>
                        <?php $companyName = securityTraceCompanyDisplayName($companyItem); $logo = securityTraceAssetUrl($companyItem['logo_path'] ?? null); ?>
                        <a class="system-security-company-card" href="/helpdesk-php/admin-security-company.php?company_id=<?= (int) ($companyItem['company_id'] ?? 0) ?>">
                            <span class="system-security-company-logo <?= $logo !== '' ? 'has-logo' : '' ?>"><?php if ($logo !== ''): ?><img src="<?= securityTraceSafe($logo) ?>" alt="Logo de <?= securityTraceSafe($companyName) ?>"><?php else: ?><?= securityTraceSafe(securityTraceInitials($companyName)) ?><?php endif; ?></span>
                            <span class="system-security-company-card-copy"><small>Empresa cliente</small><strong><?= securityTraceSafe($companyName) ?></strong><em><?= securityTraceSafe(($companyItem['ruc'] ?? '') !== '' ? 'RUC ' . $companyItem['ruc'] : 'RUC no registrado') ?></em></span>
                            <span class="system-security-company-stats"><span><strong><?= (int) ($companyItem['contact_count'] ?? 0) ?></strong><small>Contactos</small></span><span><strong><?= (int) ($companyItem['event_count'] ?? 0) ?></strong><small>Eventos</small></span></span>
                            <span class="system-security-company-last">Última actividad: <?= securityTraceSafe(securityTraceDate($companyItem['last_activity'] ?? null)) ?></span>
                            <span class="system-security-company-open">Ver contactos <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?><nav class="system-security-pagination" aria-label="Paginación"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="<?= securityTraceSafe(securityCompaniesUrl(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
            </section>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
