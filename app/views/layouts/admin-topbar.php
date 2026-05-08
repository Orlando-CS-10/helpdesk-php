<?php
$adminPageTitle = $adminPageTitle ?? 'Panel del Administrador';
$adminPageDescription = $adminPageDescription ?? 'Panel de administración del sistema.';
?>

<header class="admin-topbar">
    <div class="admin-topbar-left">
        <h1><?= htmlspecialchars($pageTitle ?? 'Panel del Administrador') ?></h1>
        <p><?= htmlspecialchars($pageSubtitle ?? 'Monitorea la gestión del mantenimiento correctivo con indicadores operativos del sistema.') ?></p>
    </div>

    <div class="admin-topbar-right">
        <a href="/helpdesk-php/home.php" class="btn-secondary">Ir al inicio</a>

        <div class="admin-user-menu">
            <button class="admin-user-trigger" type="button" onclick="toggleAdminUserMenu()">
                <div class="admin-user-avatar">
                    <?= strtoupper(substr(user()['name'], 0, 1)) ?>
                </div>

                <div class="admin-user-meta">
                    <span>Administrador activo</span>
                    <strong><?= htmlspecialchars(user()['name']) ?></strong>
                </div>
            </button>

            <div class="admin-user-dropdown" id="adminUserDropdown">
                <div class="admin-user-dropdown-header">
                    <div class="admin-user-avatar large">
                        <?= strtoupper(substr(user()['name'], 0, 1)) ?>
                    </div>

                    <div>
                        <div class="dropdown-name"><?= htmlspecialchars(user()['name']) ?></div>
                        <div class="dropdown-role">Administrador</div>
                    </div>
                </div>

                <div class="admin-user-dropdown-links">
                    <a href="/helpdesk-php/index.php">Panel de control</a>
                    <a href="/helpdesk-php/admin-tickets.php">Gestión de tickets</a>
                    <a href="/helpdesk-php/admin-users.php">Usuarios</a>
                    <a href="#">Dashboard</a>
                    <a href="#">Herramientas</a>
                    <a href="/helpdesk-php/settings.php">Ajustes</a>
                    <a href="/helpdesk-php/logout.php" class="danger-link">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
</header>