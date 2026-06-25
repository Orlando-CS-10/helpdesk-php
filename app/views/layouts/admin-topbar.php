<?php
$adminPageTitle = $adminPageTitle ?? 'Panel del Administrador';
$adminPageDescription = $adminPageDescription ?? 'Panel de administración del sistema.';

$currentUser = function_exists('user') ? (array) user() : [];
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentUserName = trim((string)($currentUser['name'] ?? 'Usuario'));
$currentUserEmail = trim((string)($currentUser['email'] ?? ''));
$currentUserInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($currentUserName !== '' ? $currentUserName : 'U', 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($currentUserName !== '' ? $currentUserName : 'U', 0, 1));
$currentUserProfilePhoto = $currentUser['profile_photo'] ?? null;

if (!$currentUserProfilePhoto && $currentUserId > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $columnStatement = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");

        if ($columnStatement && $columnStatement->fetch(PDO::FETCH_ASSOC)) {
            $photoStatement = $pdo->prepare(
                'SELECT profile_photo FROM users WHERE id = :user_id LIMIT 1'
            );
            $photoStatement->execute(['user_id' => $currentUserId]);
            $currentUserProfilePhoto = $photoStatement->fetchColumn() ?: null;
        }
    } catch (Throwable $exception) {
        $currentUserProfilePhoto = null;
    }
}

if (!function_exists('adminTopbarProfilePhotoUrl')) {
    function adminTopbarProfilePhotoUrl(?string $photo): ?string
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

$currentUserProfilePhotoUrl = adminTopbarProfilePhotoUrl(
    is_string($currentUserProfilePhoto) ? $currentUserProfilePhoto : null
);

$roleLabel = match ($currentRole) {
    'ADMIN' => 'Administrador activo',
    'TECH' => 'Técnico activo',
    'CLIENT' => 'Cliente activo',
    default => 'Usuario activo',
};

$roleDropdownLabel = match ($currentRole) {
    'ADMIN' => 'Administrador',
    'TECH' => 'Técnico',
    'CLIENT' => 'Cliente',
    default => 'Usuario',
};

$canSeeSlaNotifications = in_array($currentRole, ['ADMIN', 'TECH'], true) && $currentUserId > 0;
$notificationItems = [];
$unreadNotificationsCount = 0;

if ($canSeeSlaNotifications && isset($pdo) && $pdo instanceof PDO) {
    $notificationsHelper = __DIR__ . '/../../helpers/notifications.php';

    if (is_file($notificationsHelper)) {
        require_once $notificationsHelper;

        if (function_exists('syncSlaNotificationsForUser')) {
            syncSlaNotificationsForUser($pdo, $currentUser);
        }

        if (function_exists('getUnreadNotificationsCount')) {
            $unreadNotificationsCount = getUnreadNotificationsCount($pdo, $currentUserId);
        }

        if (function_exists('getUserNotifications')) {
            $notificationItems = getUserNotifications($pdo, $currentUserId, 8);
        }
    }
}

$baseUrl = '/helpdesk-php';
$accountSettingsUrl = $currentRole === 'ADMIN'
    ? $baseUrl . '/admin-settings.php'
    : $baseUrl . '/settings.php';
$accountSettingsTitle = $currentRole === 'ADMIN' ? 'Ajustes del sistema' : 'Ajustes';
$accountSettingsDescription = $currentRole === 'ADMIN'
    ? 'Identidad y configuración institucional'
    : 'Preferencias y datos personales';
$currentUrl = $_SERVER['REQUEST_URI'] ?? $baseUrl . '/index.php';
$currentUrlEncoded = rawurlencode($currentUrl);
?>

<header class="admin-topbar">
    <div class="admin-topbar-left">
        <h1><?= htmlspecialchars($pageTitle ?? $adminPageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($pageSubtitle ?? $adminPageDescription, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="admin-topbar-right">
        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/home.php" class="btn-secondary admin-home-link">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 3 3 10v11h6v-7h6v7h6V10l-9-7z"/>
            </svg>
            <span>Ir al inicio</span>
        </a>

        <?php if ($canSeeSlaNotifications): ?>
            <div class="admin-notification-menu">
                <button
                    class="admin-notification-trigger"
                    id="adminNotificationsTrigger"
                    type="button"
                    onclick="toggleAdminNotificationsMenu(event)"
                    title="Notificaciones operativas"
                    aria-label="Abrir notificaciones"
                    aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1l-2-2z"/>
                    </svg>

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
                            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/mark-all-notifications-read.php?redirect=<?= htmlspecialchars($currentUrlEncoded, ENT_QUOTES, 'UTF-8') ?>">
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
                                $notificationId = (int)($notification['id'] ?? 0);
                                $ticketId = isset($notification['related_ticket_id'])
                                    ? (int)$notification['related_ticket_id']
                                    : 0;
                                $redirectTo = $ticketId > 0
                                    ? $baseUrl . '/ticket-detail.php?id=' . $ticketId
                                    : $currentUrl;
                                $notificationHref = $baseUrl
                                    . '/mark-notifications-read.php?id='
                                    . $notificationId
                                    . '&redirect='
                                    . rawurlencode($redirectTo);

                                $iconClass = match ($notificationType) {
                                    'success' => 'fa-solid fa-circle-check',
                                    'warning' => 'fa-solid fa-triangle-exclamation',
                                    'error' => 'fa-solid fa-circle-exclamation',
                                    default => 'fa-solid fa-circle-info',
                                };
                                ?>

                                <a
                                    href="<?= htmlspecialchars($notificationHref, ENT_QUOTES, 'UTF-8') ?>"
                                    class="admin-notification-item <?= ((int)($notification['is_read'] ?? 0) === 0) ? 'unread' : '' ?> type-<?= htmlspecialchars($notificationType, ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="admin-notification-icon">
                                        <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>"></i>
                                    </span>

                                    <span class="admin-notification-content">
                                        <strong><?= htmlspecialchars($notification['title'] ?? 'Notificación', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars($notification['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                                        <em><?= htmlspecialchars(date('d/m/Y H:i', strtotime($notification['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></em>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-user-menu">
            <button
                class="admin-user-trigger"
                id="adminUserTrigger"
                type="button"
                onclick="toggleAdminUserMenu(event)"
                aria-label="Abrir opciones del usuario"
                aria-expanded="false">
                <div class="admin-user-avatar <?= $currentUserProfilePhotoUrl ? 'has-photo' : '' ?>">
                    <?php if ($currentUserProfilePhotoUrl): ?>
                        <img
                            src="<?= htmlspecialchars($currentUserProfilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Foto de <?= htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8') ?>"
                            class="admin-avatar-photo">
                    <?php else: ?>
                        <?= htmlspecialchars($currentUserInitial, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>

                <div class="admin-user-meta">
                    <span><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?= htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <svg class="admin-user-chevron" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5H7z"/>
                </svg>
            </button>

            <div class="admin-user-dropdown" id="adminUserDropdown">
                <div class="admin-user-dropdown-header">
                    <div class="admin-user-avatar large <?= $currentUserProfilePhotoUrl ? 'has-photo' : '' ?>">
                        <?php if ($currentUserProfilePhotoUrl): ?>
                            <img
                                src="<?= htmlspecialchars($currentUserProfilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                alt="Foto de <?= htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8') ?>"
                                class="admin-avatar-photo">
                        <?php else: ?>
                            <?= htmlspecialchars($currentUserInitial, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>

                    <div class="admin-user-dropdown-identity">
                        <div class="dropdown-name"><?= htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="dropdown-role"><?= htmlspecialchars($roleDropdownLabel, ENT_QUOTES, 'UTF-8') ?></div>

                        <?php if ($currentUserEmail !== ''): ?>
                            <div class="dropdown-email"><?= htmlspecialchars($currentUserEmail, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($currentRole === 'ADMIN'): ?>
                    <div class="admin-user-dropdown-section">
                        <span class="admin-user-dropdown-title">Administración</span>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/index.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                            </svg>
                            <span>
                                <strong>Panel operativo</strong>
                                <small>Resumen general del soporte</small>
                            </span>
                        </a>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-tickets.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 5h16v4a2 2 0 0 0 0 4v6H4v-6a2 2 0 0 0 0-4V5z"/>
                            </svg>
                            <span>
                                <strong>Gestión de tickets</strong>
                                <small>Consultar y administrar incidencias</small>
                            </span>
                        </a>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-clients.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 21V4h10v5h8v12H3zm3-3h2v-2H6v2zm0-5h2v-2H6v2zm0-5h2V6H6v2zm5 10h2v-2h-2v2zm0-5h2v-2h-2v2zm5 5h2v-2h-2v2zm0-5h2v-2h-2v2z"/>
                            </svg>
                            <span>
                                <strong>Clientes</strong>
                                <small>Empresas, contratos y contactos</small>
                            </span>
                        </a>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-users.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm6 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 21v-2a6 6 0 0 1 12 0v2H2zm13-6.8c3 .4 5 2.3 5 4.8v2h-4v-2c0-1.8-.4-3.4-1-4.8z"/>
                            </svg>
                            <span>
                                <strong>Usuarios</strong>
                                <small>Cuentas, roles y permisos</small>
                            </span>
                        </a>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-dashboard.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 20h16v2H2V4h2v16zm3-3v-6h3v6H7zm5 0V7h3v10h-3zm5 0V3h3v14h-3z"/>
                            </svg>
                            <span>
                                <strong>Dashboard</strong>
                                <small>Indicadores, SLA y rendimiento</small>
                            </span>
                        </a>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-tools.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M14.7 6.3a4 4 0 0 0-5-5l2.1 2.1-2.4 2.4-2.1-2.1a4 4 0 0 0 5 5L20 16.4 16.4 20l-7.7-7.7a4 4 0 0 0-5 5l2.1-2.1 2.4 2.4-2.1 2.1a4 4 0 0 0 5-5l7.7 7.7 3.6-3.6-7.7-7.7z"/>
                            </svg>
                            <span>
                                <strong>Herramientas</strong>
                                <small>Catálogos y recursos de soporte</small>
                            </span>
                        </a>
                    </div>
                <?php elseif ($currentRole === 'TECH'): ?>
                    <div class="admin-user-dropdown-section">
                        <span class="admin-user-dropdown-title">Soporte técnico</span>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-tickets.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 5h16v4a2 2 0 0 0 0 4v6H4v-6a2 2 0 0 0 0-4V5z"/>
                            </svg>
                            <span>
                                <strong>Tickets asignados</strong>
                                <small>Casos pendientes y en atención</small>
                            </span>
                        </a>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-dashboard.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 20h16v2H2V4h2v16zm3-3v-6h3v6H7zm5 0V7h3v10h-3zm5 0V3h3v14h-3z"/>
                            </svg>
                            <span>
                                <strong>Dashboard</strong>
                                <small>Indicadores de atención técnica</small>
                            </span>
                        </a>

                        <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin-tools.php">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M14.7 6.3a4 4 0 0 0-5-5l2.1 2.1-2.4 2.4-2.1-2.1a4 4 0 0 0 5 5L20 16.4 16.4 20l-7.7-7.7a4 4 0 0 0-5 5l2.1-2.1 2.4 2.4-2.1 2.1a4 4 0 0 0 5-5l7.7 7.7 3.6-3.6-7.7-7.7z"/>
                            </svg>
                            <span>
                                <strong>Herramientas</strong>
                                <small>Plantillas y recursos de soporte</small>
                            </span>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="admin-user-dropdown-section admin-user-dropdown-account">
                    <span class="admin-user-dropdown-title">Cuenta</span>

                    <a href="<?= htmlspecialchars($accountSettingsUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m19.4 13 .1-1-.1-1 2-1.6-2-3.4-2.4 1a8 8 0 0 0-1.7-1L15 3h-4l-.4 3a8 8 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.6-.1 1 .1 1-2 1.6 2 3.4 2.4-1a8 8 0 0 0 1.7 1l.4 3h4l.4-3a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6zM13 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
                        </svg>
                        <span>
                            <strong><?= htmlspecialchars($accountSettingsTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($accountSettingsDescription, ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </a>
                </div>

                <div class="admin-user-dropdown-footer">
                    <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/logout.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10 17v-3H3v-4h7V7l5 5-5 5zm4-14h7v18h-7v-2h5V5h-5V3z"/>
                        </svg>
                        <span>Cerrar sesión</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
(function () {
    function getUserDropdown() {
        return document.getElementById('adminUserDropdown');
    }

    function getNotificationsDropdown() {
        return document.getElementById('adminNotificationsDropdown');
    }

    function setUserMenuOpen(open) {
        const dropdown = getUserDropdown();
        const trigger = document.getElementById('adminUserTrigger');

        if (!dropdown) {
            return;
        }

        dropdown.classList.toggle('show', open);

        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function setNotificationsMenuOpen(open) {
        const dropdown = getNotificationsDropdown();
        const trigger = document.getElementById('adminNotificationsTrigger');

        if (!dropdown) {
            return;
        }

        dropdown.classList.toggle('show', open);

        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    window.toggleAdminUserMenu = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const dropdown = getUserDropdown();
        const shouldOpen = dropdown ? !dropdown.classList.contains('show') : false;

        setNotificationsMenuOpen(false);
        setUserMenuOpen(shouldOpen);
    };

    window.toggleAdminNotificationsMenu = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const dropdown = getNotificationsDropdown();
        const shouldOpen = dropdown ? !dropdown.classList.contains('show') : false;

        setUserMenuOpen(false);
        setNotificationsMenuOpen(shouldOpen);
    };

    function bindAdminTopbarEvents() {
        if (document.documentElement.dataset.adminTopbarEventsBound === '1') {
            return;
        }

        document.documentElement.dataset.adminTopbarEventsBound = '1';

        document.addEventListener('click', function (event) {
            const userMenu = document.querySelector('.admin-user-menu');
            const notificationsMenu = document.querySelector('.admin-notification-menu');

            if (!userMenu || !userMenu.contains(event.target)) {
                setUserMenuOpen(false);
            }

            if (!notificationsMenu || !notificationsMenu.contains(event.target)) {
                setNotificationsMenuOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setUserMenuOpen(false);
                setNotificationsMenuOpen(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAdminTopbarEvents, { once: true });
    } else {
        bindAdminTopbarEvents();
    }
})();
</script>
