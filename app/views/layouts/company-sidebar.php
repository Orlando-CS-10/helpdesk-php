<?php
$companyActivePage = (string) ($companyActivePage ?? 'dashboard');
$companyName = $companyName ?? companyPortalDisplayName($account ?? []);
$companyLogoUrl = $logoUrl ?? companyPortalLogoUrl($account['logo_path'] ?? null);
$companyAccountName = trim((string) ($account['name'] ?? 'Cuenta corporativa'));
$companyAccountType = !empty($account['is_primary'])
    ? 'Cuenta principal'
    : 'Responsable corporativo';
?>

<aside class="admin-sidebar company-shared-sidebar" id="adminSidebar">
    <button
        class="admin-sidebar-toggle"
        id="adminSidebarToggle"
        type="button"
        onclick="toggleAdminSidebar(event)"
        title="Contraer menú"
        aria-label="Contraer menú lateral"
        aria-expanded="true">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"/>
        </svg>
    </button>

    <div class="admin-sidebar-brand company-shared-brand">
        <div class="admin-brand-logo company-brand-logo <?= $companyLogoUrl ? 'has-company-logo' : '' ?>">
            <?php if ($companyLogoUrl): ?>
                <img
                    src="<?= htmlspecialchars($companyLogoUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Logo de <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
                <i class="fa-solid fa-building"></i>
            <?php endif; ?>
        </div>

        <div class="admin-brand-text">
            <h2><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Portal corporativo</p>
        </div>
    </div>

    <nav class="admin-sidebar-nav company-sidebar-nav" aria-label="Navegación corporativa">
        <span class="admin-sidebar-section">Inicio</span>

        <a
            href="/helpdesk-php/company-dashboard.php"
            title="Resumen corporativo"
            class="<?= $companyActivePage === 'dashboard' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-chart-pie"></i>
                <span class="admin-link-text">Resumen</span>
            </span>
        </a>

        <span class="admin-sidebar-section">Gestión</span>

        <a href="#" class="is-planned" aria-disabled="true" title="Usuarios y permisos">
            <span>
                <i class="fa-solid fa-users-gear"></i>
                <span class="admin-link-text">Usuarios y permisos</span>
            </span>
            <small class="admin-sidebar-badge">Próximamente</small>
        </a>

        <a href="#" class="is-planned" aria-disabled="true" title="Tickets de la empresa">
            <span>
                <i class="fa-solid fa-ticket"></i>
                <span class="admin-link-text">Tickets de la empresa</span>
            </span>
            <small class="admin-sidebar-badge">Próximamente</small>
        </a>

        <span class="admin-sidebar-section">Análisis</span>

        <a href="#" class="is-planned" aria-disabled="true" title="Reportes corporativos">
            <span>
                <i class="fa-solid fa-chart-line"></i>
                <span class="admin-link-text">Reportes</span>
            </span>
            <small class="admin-sidebar-badge">Próximamente</small>
        </a>

        <span class="admin-sidebar-section">Empresa</span>

        <a href="#" class="is-planned" aria-disabled="true" title="Perfil y contrato">
            <span>
                <i class="fa-solid fa-building-circle-check"></i>
                <span class="admin-link-text">Perfil y contrato</span>
            </span>
            <small class="admin-sidebar-badge">Próximamente</small>
        </a>

        <a href="/helpdesk-php/company-change-password.php" title="Seguridad de la cuenta">
            <span>
                <i class="fa-solid fa-key"></i>
                <span class="admin-link-text">Seguridad</span>
            </span>
        </a>
    </nav>

    <div class="company-sidebar-account">
        <span class="company-sidebar-account-icon">
            <i class="fa-solid fa-user-shield"></i>
        </span>

        <div class="company-sidebar-account-copy">
            <strong><?= htmlspecialchars($companyAccountName, ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($companyAccountType, ENT_QUOTES, 'UTF-8') ?></small>
        </div>

        <a href="/helpdesk-php/company-logout.php" title="Cerrar sesión" aria-label="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</aside>
