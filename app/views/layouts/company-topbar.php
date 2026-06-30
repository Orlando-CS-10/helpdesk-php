<?php
$companyPageTitle = $companyPageTitle ?? 'Panel corporativo';
$companyPageDescription = $companyPageDescription
    ?? 'Consulta la operación y el servicio de tu organización.';
$companyName = $companyName ?? companyPortalDisplayName($account ?? []);
$companyAccountName = trim((string) ($account['name'] ?? 'Cuenta corporativa'));
$companyAccountEmail = trim((string) ($account['email'] ?? ''));
$companyAccountInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($companyAccountName !== '' ? $companyAccountName : 'C', 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($companyAccountName !== '' ? $companyAccountName : 'C', 0, 1));
$companyRoleLabel = !empty($account['is_primary'])
    ? 'Cuenta principal'
    : 'Responsable corporativo';
?>

<header class="admin-topbar company-shared-topbar">
    <div class="admin-topbar-left">
        <h1><?= htmlspecialchars($companyPageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($companyPageDescription, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="admin-topbar-right">
        <span class="company-security-badge">
            <i class="fa-solid fa-shield-halved"></i>
            Sesión corporativa
        </span>

        <a
            href="/helpdesk-php/company-change-password.php"
            class="btn-secondary admin-home-link company-password-link">
            <i class="fa-solid fa-key"></i>
            <span>Contraseña</span>
        </a>

        <div class="admin-user-menu company-user-menu">
            <button
                class="admin-user-trigger"
                id="adminUserTrigger"
                type="button"
                onclick="toggleAdminUserMenu(event)"
                aria-label="Abrir opciones de la cuenta corporativa"
                aria-expanded="false">
                <div class="admin-user-avatar">
                    <?= htmlspecialchars($companyAccountInitial, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="admin-user-meta">
                    <span><?= htmlspecialchars($companyRoleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?= htmlspecialchars($companyAccountName, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <svg class="admin-user-chevron" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5H7z"/>
                </svg>
            </button>

            <div class="admin-user-dropdown" id="adminUserDropdown">
                <div class="admin-user-dropdown-header">
                    <div class="admin-user-avatar large">
                        <?= htmlspecialchars($companyAccountInitial, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="admin-user-dropdown-identity">
                        <div class="dropdown-name"><?= htmlspecialchars($companyAccountName, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="dropdown-role"><?= htmlspecialchars($companyRoleLabel, ENT_QUOTES, 'UTF-8') ?></div>

                        <?php if ($companyAccountEmail !== ''): ?>
                            <div class="dropdown-email"><?= htmlspecialchars($companyAccountEmail, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="admin-user-dropdown-section">
                    <span class="admin-user-dropdown-title">Portal corporativo</span>

                    <a href="/helpdesk-php/company-dashboard.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                        </svg>
                        <span>
                            <strong>Resumen de la empresa</strong>
                            <small>Indicadores y tickets corporativos</small>
                        </span>
                    </a>

                    <a href="/helpdesk-php/company-change-password.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2a5 5 0 0 0-5 5v3H5v12h14V10h-2V7a5 5 0 0 0-5-5zm-3 8V7a3 3 0 0 1 6 0v3H9zm3 4a2 2 0 0 1 1 3.73V20h-2v-2.27A2 2 0 0 1 12 14z"/>
                        </svg>
                        <span>
                            <strong>Seguridad de la cuenta</strong>
                            <small>Cambiar la contraseña corporativa</small>
                        </span>
                    </a>
                </div>

                <div class="admin-user-dropdown-footer">
                    <a href="/helpdesk-php/company-logout.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10 4H4v16h6v-2H6V6h4V4zm5.59 3.59L17 9l-2 2H9v2h6l2 2-1.41 1.41L20 12l-4.41-4.41z"/>
                        </svg>
                        Cerrar sesión corporativa
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
