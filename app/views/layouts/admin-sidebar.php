<?php
$adminActiveMenu = $adminActiveMenu ?? '';
?>

<aside class="admin-sidebar" id="adminSidebar">

    <button class="admin-sidebar-toggle" type="button" onclick="toggleAdminSidebar()" title="Contraer/expandir menú">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="admin-sidebar-brand">
        <div class="admin-brand-logo">A</div>

        <div class="admin-brand-text">
            <h2>Mesa Admin</h2>
            <p>Control del sistema</p>
        </div>
    </div>

    <nav class="admin-sidebar-nav">
        <a href="/helpdesk-php/index.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-house"></i>
                <span class="admin-link-text">Panel operativo</span>
            </span>
        </a>

        <a href="/helpdesk-php/admin-tickets.php" class="<?= ($activePage ?? '') === 'tickets' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-ticket"></i>
                <span class="admin-link-text">Gestionar tickets</span>
            </span>
        </a>

        <a href="/helpdesk-php/admin-users.php" class="<?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-users"></i>
                <span class="admin-link-text">Usuarios</span>
            </span>
        </a>

        <a href="/helpdesk-php/admin-dashboard.php" class="<?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-chart-line"></i>
                <span class="admin-link-text">Dashboard</span>
            </span>
        </a>

        <a href="#" class="<?= ($activePage ?? '') === 'tools' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <span class="admin-link-text">Herramientas</span>
            </span>
        </a>

        <a href="#" class="<?= ($activePage ?? '') === 'reports-file' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-file-lines"></i>
                <span class="admin-link-text">Reportes</span>
            </span>
        </a>

        <a href="/helpdesk-php/settings.php" class="<?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
            <span>
                <i class="fa-solid fa-gear"></i>
                <span class="admin-link-text">Ajustes</span>
            </span>
        </a>
    </nav>
</aside>