<?php
$adminPageTitle = $adminPageTitle ?? 'Panel del Administrador';
$adminPageDescription = $adminPageDescription ?? 'Panel de administración del sistema.';

$currentUser = function_exists('user') ? user() : [];
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));
$currentUserId = (int)($currentUser['id'] ?? 0);

$roleLabel = match ($currentRole) {
    'ADMIN' => 'Administrador activo',
    'TECH' => 'Técnico activo',
    default => 'Usuario activo',
};

$roleDropdownLabel = match ($currentRole) {
    'ADMIN' => 'Administrador',
    'TECH' => 'Técnico',
    default => 'Usuario',
};

$canSeeSlaNotifications = in_array($currentRole, ['ADMIN', 'TECH'], true) && $currentUserId > 0;
$notificationItems = [];
$unreadNotificationsCount = 0;

if ($canSeeSlaNotifications && isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/../../helpers/notifications.php';

    syncSlaNotificationsForUser($pdo, $currentUser);
    $unreadNotificationsCount = getUnreadNotificationsCount($pdo, $currentUserId);
    $notificationItems = getUserNotifications($pdo, $currentUserId, 8);
}

$currentUrl = $_SERVER['REQUEST_URI'] ?? '/helpdesk-php/index.php';
$currentUrlEncoded = urlencode($currentUrl);
?>

<header class="admin-topbar">
    <div class="admin-topbar-left">
        <h1><?= htmlspecialchars($pageTitle ?? 'Panel del Administrador') ?></h1>
        <p><?= htmlspecialchars($pageSubtitle ?? 'Monitorea la gestión del mantenimiento correctivo con indicadores operativos del sistema.') ?></p>
    </div>

    <div class="admin-topbar-right">
        <a href="/helpdesk-php/home.php" class="btn-secondary">Ir al inicio</a>

        <?php if ($canSeeSlaNotifications): ?>
            <div class="admin-notification-menu">
                <button class="admin-notification-trigger" type="button" onclick="toggleAdminNotificationsMenu()" title="Notificaciones operativas">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadNotificationsCount > 0): ?>
                        <span class="admin-notification-badge">
                            <?= $unreadNotificationsCount > 99 ? '99+' : (int)$unreadNotificationsCount ?>
                        </span>
                    <?php endif; ?>
                </button>

                <div class="admin-notification-dropdown" id="adminNotificationsDropdown">
                    <div class="admin-notification-header">
                        <div>
                            <strong>Notificaciones</strong>
                            <span>Alertas operativas y seguimiento SLA</span>
                        </div>

                        <?php if ($unreadNotificationsCount > 0): ?>
                            <a href="/helpdesk-php/mark-all-notifications-read.php?redirect=<?= htmlspecialchars($currentUrlEncoded) ?>">
                                Marcar leídas
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="admin-notification-list">
                        <?php if (empty($notificationItems)): ?>
                            <div class="admin-notification-empty">
                                <i class="fa-regular fa-bell"></i>
                                <strong>Sin alertas pendientes</strong>
                                <span>Los SLA se encuentran bajo control.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notificationItems as $notification): ?>
                                <?php
                                    $notificationType = (string)($notification['type'] ?? 'info');
                                    $notificationId = (int)$notification['id'];
                                    $ticketId = $notification['related_ticket_id'] !== null ? (int)$notification['related_ticket_id'] : 0;
                                    $notificationHref = '/helpdesk-php/mark-notification-read.php?id=' . $notificationId;
                                    if ($ticketId > 0) {
                                        $notificationHref .= '&redirect=' . urlencode('/helpdesk-php/ticket-detail.php?id=' . $ticketId);
                                    } else {
                                        $notificationHref .= '&redirect=' . $currentUrlEncoded;
                                    }

                                    $iconClass = match ($notificationType) {
                                        'success' => 'fa-solid fa-circle-check',
                                        'warning' => 'fa-solid fa-triangle-exclamation',
                                        'error' => 'fa-solid fa-circle-exclamation',
                                        default => 'fa-solid fa-circle-info',
                                    };
                                ?>

                                <a href="<?= htmlspecialchars($notificationHref) ?>" class="admin-notification-item <?= ((int)$notification['is_read'] === 0) ? 'unread' : '' ?> type-<?= htmlspecialchars($notificationType) ?>">
                                    <span class="admin-notification-icon">
                                        <i class="<?= htmlspecialchars($iconClass) ?>"></i>
                                    </span>

                                    <span class="admin-notification-content">
                                        <strong><?= htmlspecialchars($notification['title']) ?></strong>
                                        <small><?= htmlspecialchars($notification['message']) ?></small>
                                        <em><?= htmlspecialchars(date('d/m/Y H:i', strtotime($notification['created_at']))) ?></em>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-user-menu">
            <button class="admin-user-trigger" type="button" onclick="toggleAdminUserMenu()">
                <div class="admin-user-avatar">
                    <?= strtoupper(substr((string)($currentUser['name'] ?? 'U'), 0, 1)) ?>
                </div>

                <div class="admin-user-meta">
                    <span><?= htmlspecialchars($roleLabel) ?></span>
                    <strong><?= htmlspecialchars($currentUser['name'] ?? 'Usuario') ?></strong>
                </div>
            </button>

            <div class="admin-user-dropdown" id="adminUserDropdown">
                <div class="admin-user-dropdown-header">
                    <div class="admin-user-avatar large">
                        <?= strtoupper(substr((string)($currentUser['name'] ?? 'U'), 0, 1)) ?>
                    </div>

                    <div>
                        <div class="dropdown-name"><?= htmlspecialchars($currentUser['name'] ?? 'Usuario') ?></div>
                        <div class="dropdown-role"><?= htmlspecialchars($roleDropdownLabel) ?></div>
                    </div>
                </div>

                <div class="admin-user-dropdown-links">
                    <a href="/helpdesk-php/index.php">Panel de control</a>
                    <a href="/helpdesk-php/admin-tickets.php">Gestión de tickets</a>
                    <a href="/helpdesk-php/admin-users.php">Usuarios</a>
                    <a href="/helpdesk-php/admin-dashboard.php">Dashboard</a>
                    <a href="#">Herramientas</a>
                    <a href="/helpdesk-php/settings.php">Ajustes</a>
                    <a href="/helpdesk-php/logout.php" class="danger-link">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleAdminNotificationsMenu() {
    const dropdown = document.getElementById('adminNotificationsDropdown');
    if (!dropdown) return;

    dropdown.classList.toggle('show');
}

document.addEventListener('click', function (event) {
    const menu = document.querySelector('.admin-notification-menu');
    const dropdown = document.getElementById('adminNotificationsDropdown');

    if (!menu || !dropdown) return;

    if (!menu.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});
</script>
