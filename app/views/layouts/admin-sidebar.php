<?php
$adminActiveMenu = $adminActiveMenu ?? '';

$sidebarCurrentUser = function_exists('user') ? (array) user() : [];
$sidebarUserId = (int)($sidebarCurrentUser['id'] ?? 0);
$sidebarUserName = trim((string)($sidebarCurrentUser['name'] ?? 'Usuario'));
$sidebarUserInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($sidebarUserName !== '' ? $sidebarUserName : 'U', 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($sidebarUserName !== '' ? $sidebarUserName : 'U', 0, 1));
$sidebarProfilePhoto = $sidebarCurrentUser['profile_photo'] ?? null;

if (!$sidebarProfilePhoto && $sidebarUserId > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $columnStatement = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");

        if ($columnStatement && $columnStatement->fetch(PDO::FETCH_ASSOC)) {
            $photoStatement = $pdo->prepare(
                'SELECT profile_photo FROM users WHERE id = :user_id LIMIT 1'
            );
            $photoStatement->execute(['user_id' => $sidebarUserId]);
            $sidebarProfilePhoto = $photoStatement->fetchColumn() ?: null;
        }
    } catch (Throwable $exception) {
        $sidebarProfilePhoto = null;
    }
}

if (!function_exists('adminSidebarProfilePhotoUrl')) {
    function adminSidebarProfilePhotoUrl(?string $photo): ?string
    {
        $photo = trim((string)$photo);

        if ($photo === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $photo)) {
            return $photo;
        }

        if (str_starts_with($photo, '/')) {
            return $photo;
        }

        $photo = ltrim($photo, '/');

        if (str_starts_with($photo, 'public/')) {
            return '/helpdesk-php/' . $photo;
        }

        return '/helpdesk-php/public/uploads/users/' . $photo;
    }
}

$sidebarProfilePhotoUrl = adminSidebarProfilePhotoUrl(
    is_string($sidebarProfilePhoto) ? $sidebarProfilePhoto : null
);

$sidebarActivePage = (string)($activePage ?? $adminActiveMenu ?? '');
?>

<aside class="admin-sidebar" id="adminSidebar">
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

    <div class="admin-sidebar-brand">
        <div class="admin-brand-logo <?= $sidebarProfilePhotoUrl ? 'has-photo' : '' ?>">
            <?php if ($sidebarProfilePhotoUrl): ?>
                <img
                    src="<?= htmlspecialchars($sidebarProfilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Foto de <?= htmlspecialchars($sidebarUserName, ENT_QUOTES, 'UTF-8') ?>"
                    class="admin-avatar-photo">
            <?php else: ?>
                <?= htmlspecialchars($sidebarUserInitial, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </div>

        <div class="admin-brand-text">
            <h2>Mesa Admin</h2>
            <p>Control del sistema</p>
        </div>
    </div>

    <nav class="admin-sidebar-nav" aria-label="Navegación administrativa">
        <a
            href="/helpdesk-php/index.php"
            title="Panel operativo"
            class="<?= $sidebarActivePage === 'dashboard' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-house"></i>
                <span class="admin-link-text">Panel operativo</span>
            </span>
        </a>

        <a
            href="/helpdesk-php/admin-tickets.php"
            title="Gestionar tickets"
            class="<?= $sidebarActivePage === 'tickets' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-ticket"></i>
                <span class="admin-link-text">Gestionar tickets</span>
            </span>
        </a>

        <a
            href="/helpdesk-php/admin-clients.php"
            title="Clientes"
            class="<?= $sidebarActivePage === 'clients' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-building-user"></i>
                <span class="admin-link-text">Clientes</span>
            </span>
        </a>

        <a
            href="/helpdesk-php/admin-users.php"
            title="Usuarios"
            class="<?= $sidebarActivePage === 'users' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-users"></i>
                <span class="admin-link-text">Usuarios</span>
            </span>
        </a>

        <a
            href="/helpdesk-php/admin-dashboard.php"
            title="Dashboard"
            class="<?= in_array($sidebarActivePage, ['reports', 'dashboard-advanced'], true) ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-chart-line"></i>
                <span class="admin-link-text">Dashboard</span>
            </span>
        </a>

        <a
            href="/helpdesk-php/admin-tools.php"
            title="Herramientas"
            class="<?= $sidebarActivePage === 'tools' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <span class="admin-link-text">Herramientas</span>
            </span>
        </a>

        <a
            href="/helpdesk-php/admin-dashboard.php"
            title="Reportes"
            class="<?= $sidebarActivePage === 'reports-file' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-file-lines"></i>
                <span class="admin-link-text">Reportes</span>
            </span>
        </a>

        <a
            href="/helpdesk-php/admin-settings.php"
            title="Ajustes"
            class="<?= in_array($sidebarActivePage, ['settings', 'system-profile', 'system-customization', 'system-security', 'system-sla'], true) ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-gear"></i>
                <span class="admin-link-text">Ajustes</span>
            </span>
        </a>
    </nav>
</aside>

<script>
(function () {
    const STORAGE_KEY = 'helpdesk_admin_sidebar_collapsed';

    function getAdminShell() {
        const sidebar = document.getElementById('adminSidebar');
        return sidebar ? sidebar.closest('.admin-shell') : document.querySelector('.admin-shell');
    }

    function updateSidebarControl(collapsed) {
        const button = document.getElementById('adminSidebarToggle');

        if (!button) {
            return;
        }

        button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        button.setAttribute(
            'aria-label',
            collapsed ? 'Expandir menú lateral' : 'Contraer menú lateral'
        );
        button.setAttribute(
            'title',
            collapsed ? 'Expandir menú' : 'Contraer menú'
        );
    }

    function applySidebarState(collapsed, saveState) {
        const shell = getAdminShell();

        if (!shell) {
            return;
        }

        shell.classList.toggle('sidebar-collapsed', collapsed);
        updateSidebarControl(collapsed);

        if (saveState) {
            try {
                localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
            } catch (error) {
                // El sistema sigue funcionando aunque el navegador bloquee localStorage.
            }
        }
    }

    window.toggleAdminSidebar = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const shell = getAdminShell();

        if (!shell) {
            return;
        }

        applySidebarState(!shell.classList.contains('sidebar-collapsed'), true);
    };

    function initializeSidebar() {
        let collapsed = false;

        try {
            collapsed = localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            collapsed = false;
        }

        applySidebarState(collapsed, false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSidebar, { once: true });
    } else {
        initializeSidebar();
    }
})();
</script>
