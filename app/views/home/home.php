<?php
$title = 'Inicio - Mesa de Ayuda';
$useClientLayout = true;

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../helpers/session.php';
require_once __DIR__ . '/../../config/database.php';

$unreadNotifications = $unreadNotifications ?? 0;
$notifications = $notifications ?? [];
$knowledgeArticles = [];
$knowledgeCategories = [];

/*
 * Carga únicamente artículos activos de la base de conocimientos.
 * La validación de la tabla evita que el home falle si el módulo
 * Herramientas todavía no fue instalado en la base de datos.
 */
try {
    $knowledgeTableExists = false;
    $tableStatement = $pdo->query("SHOW TABLES LIKE 'knowledge_base_articles'");

    if ($tableStatement && $tableStatement->fetchColumn()) {
        $knowledgeTableExists = true;
    }

    if ($knowledgeTableExists) {
        $knowledgeSql = "SELECT
                            k.id,
                            k.title,
                            k.problem_summary,
                            k.solution_steps,
                            k.keywords,
                            COALESCE(c.name, 'General') AS category_name,
                            COALESCE(c.code, 'GENERAL') AS category_code,
                            COALESCE(c.color, '#ff7a00') AS category_color
                         FROM knowledge_base_articles k
                         LEFT JOIN ticket_categories c ON c.id = k.category_id
                         WHERE k.is_active = 1
                           AND (c.id IS NULL OR c.is_active = 1)
                         ORDER BY k.updated_at DESC, k.title ASC
                         LIMIT 30";

        $knowledgeStatement = $pdo->query($knowledgeSql);
        $knowledgeArticles = $knowledgeStatement
            ? $knowledgeStatement->fetchAll(PDO::FETCH_ASSOC)
            : [];

        foreach ($knowledgeArticles as $article) {
            $categoryCode = (string)($article['category_code'] ?? 'GENERAL');

            if (!isset($knowledgeCategories[$categoryCode])) {
                $knowledgeCategories[$categoryCode] = [
                    'code' => $categoryCode,
                    'name' => (string)($article['category_name'] ?? 'General'),
                ];
            }
        }
    }
} catch (Throwable $exception) {
    $knowledgeArticles = [];
    $knowledgeCategories = [];
}
?>

<?php
$publicHeaderUser = function_exists('user') ? (array) user() : [];
$publicHeaderRole = strtoupper((string)($publicHeaderUser['role'] ?? ''));
$publicHeaderUserId = (int)($publicHeaderUser['id'] ?? 0);
$publicHeaderUserName = trim((string)($publicHeaderUser['name'] ?? 'Usuario'));
$publicHeaderUserEmail = trim((string)($publicHeaderUser['email'] ?? ''));
$publicHeaderProfilePhoto = $publicHeaderUser['profile_photo'] ?? null;

if (!$publicHeaderProfilePhoto && $publicHeaderUserId > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $publicPhotoColumnStatement = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");

        if ($publicPhotoColumnStatement && $publicPhotoColumnStatement->fetch(PDO::FETCH_ASSOC)) {
            $publicPhotoStatement = $pdo->prepare(
                'SELECT profile_photo FROM users WHERE id = :user_id LIMIT 1'
            );
            $publicPhotoStatement->execute(['user_id' => $publicHeaderUserId]);
            $publicHeaderProfilePhoto = $publicPhotoStatement->fetchColumn() ?: null;
        }
    } catch (Throwable $publicPhotoException) {
        $publicHeaderProfilePhoto = null;
    }
}

if (!function_exists('publicHeaderProfilePhotoUrl')) {
    function publicHeaderProfilePhotoUrl(?string $photo): ?string
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

$publicHeaderProfilePhotoUrl = publicHeaderProfilePhotoUrl(
    is_string($publicHeaderProfilePhoto) ? $publicHeaderProfilePhoto : null
);

$publicHeaderInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($publicHeaderUserName !== '' ? $publicHeaderUserName : 'U', 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($publicHeaderUserName !== '' ? $publicHeaderUserName : 'U', 0, 1));

$publicHeaderRoleLabel = match ($publicHeaderRole) {
    'ADMIN' => 'Administrador',
    'TECH' => 'Técnico',
    'CLIENT' => 'Cliente',
    default => 'Usuario',
};

$publicHeaderStatusLabel = match ($publicHeaderRole) {
    'ADMIN' => 'Administrador activo',
    'TECH' => 'Técnico activo',
    default => 'Sesión activa',
};

$publicHeaderPrimaryUrl = match ($publicHeaderRole) {
    'CLIENT' => '/helpdesk-php/app/views/client/create-ticket.php',
    'ADMIN', 'TECH' => '/helpdesk-php/index.php',
    default => '/helpdesk-php/login.php',
};

$publicHeaderPrimaryLabel = match ($publicHeaderRole) {
    'CLIENT' => 'Nuevo ticket',
    'ADMIN', 'TECH' => 'Panel operativo',
    default => 'Iniciar sesión',
};
?>

<header class="site-header public-site-header public-site-header-clean">
    <div class="container header-inner public-header-inner-clean">
        <a class="brand-box public-brand-box-clean" href="/helpdesk-php/home.php" aria-label="Ir al inicio">
            <img
                src="/helpdesk-php/public/assets/img/logo.png"
                alt="Logo de Pronet System"
                class="public-company-logo">

            <span class="public-brand-copy-clean">
                <strong>Mesa de Ayuda</strong>
                <span>Soporte técnico inteligente y centralizado</span>
            </span>
        </a>

        <div class="public-header-actions-clean">
            <?php if (isLoggedIn()): ?>
                <a
                    class="public-header-primary-action"
                    href="<?= htmlspecialchars($publicHeaderPrimaryUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <?php if ($publicHeaderRole === 'CLIENT'): ?>
                            <path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5z"/>
                        <?php else: ?>
                            <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                        <?php endif; ?>
                    </svg>
                    <span><?= htmlspecialchars($publicHeaderPrimaryLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </a>

                <div class="notification-menu">
                    <button
                        class="notification-trigger public-notification-trigger"
                        type="button"
                        onclick="toggleNotificationMenu()"
                        aria-label="Abrir notificaciones">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1l-2-2z"/>
                        </svg>

                        <?php if ((int)$unreadNotifications > 0): ?>
                            <span class="notification-badge"><?= (int)$unreadNotifications ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-dropdown-header">
                            <div class="notification-title-block">
                                <h3>Notificaciones</h3>
                                <p>Últimos movimientos de tus tickets.</p>
                            </div>

                            <span class="notification-unread-chip">
                                <?= (int)$unreadNotifications ?> sin leer
                            </span>
                        </div>

                        <div class="notification-dropdown-actions">
                            <form action="/helpdesk-php/mark-all-notifications-read.php" method="POST">
                                <button type="submit" class="notification-action-btn">Marcar todo como leído</button>
                            </form>

                            <form
                                action="/helpdesk-php/delete-read-notifications.php"
                                method="POST"
                                onsubmit="return confirm('¿Eliminar las notificaciones leídas?');">
                                <button type="submit" class="notification-action-btn danger">Eliminar leídas</button>
                            </form>
                        </div>

                        <div class="notification-dropdown-list">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $isUnread = (int)($notification['is_read'] ?? 0) === 0;
                                    $notificationUrl = !empty($notification['related_ticket_id'])
                                        ? '/helpdesk-php/ticket-detail.php?id=' . (int)$notification['related_ticket_id']
                                        : '#';
                                    ?>
                                    <a
                                        class="notification-item <?= $isUnread ? 'unread' : '' ?>"
                                        href="<?= htmlspecialchars($notificationUrl, ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="notification-state-dot <?= $isUnread ? 'active' : '' ?>"></span>

                                        <div class="notification-item-body">
                                            <div class="notification-item-top">
                                                <strong><?= htmlspecialchars($notification['title'] ?? 'Notificación') ?></strong>
                                                <span><?= htmlspecialchars(date('d/m H:i', strtotime($notification['created_at'] ?? 'now'))) ?></span>
                                            </div>

                                            <p><?= htmlspecialchars($notification['message'] ?? '') ?></p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notification-empty">
                                    <strong>Sin notificaciones</strong>
                                    <p>Cuando ocurra algo importante en tus tickets, aparecerá aquí.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="user-menu public-user-menu">
                    <button
                        class="user-menu-trigger public-user-trigger"
                        type="button"
                        onclick="toggleUserMenu()"
                        aria-label="Abrir menú del usuario"
                        aria-expanded="false">
                        <span class="public-user-avatar <?= $publicHeaderProfilePhotoUrl ? 'has-photo' : '' ?>">
                            <?php if ($publicHeaderProfilePhotoUrl): ?>
                                <img
                                    src="<?= htmlspecialchars($publicHeaderProfilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Foto de <?= htmlspecialchars($publicHeaderUserName, ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <?= htmlspecialchars($publicHeaderInitial, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>

                        <span class="public-user-summary">
                            <small><?= htmlspecialchars($publicHeaderStatusLabel, ENT_QUOTES, 'UTF-8') ?></small>
                            <strong><?= htmlspecialchars($publicHeaderUserName, ENT_QUOTES, 'UTF-8') ?></strong>
                        </span>

                        <svg class="public-user-chevron" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m7 10 5 5 5-5H7z"/>
                        </svg>
                    </button>

                    <div class="user-dropdown public-user-dropdown" id="userDropdown">
                        <div class="public-user-dropdown-head">
                            <span class="public-user-avatar public-user-avatar-large <?= $publicHeaderProfilePhotoUrl ? 'has-photo' : '' ?>">
                                <?php if ($publicHeaderProfilePhotoUrl): ?>
                                    <img
                                        src="<?= htmlspecialchars($publicHeaderProfilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Foto de <?= htmlspecialchars($publicHeaderUserName, ENT_QUOTES, 'UTF-8') ?>">
                                <?php else: ?>
                                    <?= htmlspecialchars($publicHeaderInitial, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </span>

                            <div class="public-user-dropdown-identity">
                                <strong><?= htmlspecialchars($publicHeaderUserName, ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars($publicHeaderRoleLabel, ENT_QUOTES, 'UTF-8') ?></span>

                                <?php if ($publicHeaderUserEmail !== ''): ?>
                                    <small><?= htmlspecialchars($publicHeaderUserEmail, ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($publicHeaderRole === 'ADMIN'): ?>
                            <div class="public-dropdown-section">
                                <span class="public-dropdown-section-title">Administración</span>

                                <a href="/helpdesk-php/index.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                                    </svg>
                                    <span>
                                        <strong>Panel de control</strong>
                                        <small>Resumen operativo del sistema</small>
                                    </span>
                                </a>

                                <a href="/helpdesk-php/admin-tickets.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 5h16v4a2 2 0 0 0 0 4v6H4v-6a2 2 0 0 0 0-4V5z"/>
                                    </svg>
                                    <span>
                                        <strong>Gestión de tickets</strong>
                                        <small>Consultar y administrar incidencias</small>
                                    </span>
                                </a>

                                <a href="/helpdesk-php/admin-users.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm6 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 21v-2a6 6 0 0 1 12 0v2H2zm13-6.8c3 .4 5 2.3 5 4.8v2h-4v-2c0-1.8-.4-3.4-1-4.8z"/>
                                    </svg>
                                    <span>
                                        <strong>Usuarios</strong>
                                        <small>Cuentas, roles y permisos</small>
                                    </span>
                                </a>

                                <a href="/helpdesk-php/admin-tools.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M14.7 6.3a4 4 0 0 0-5-5l2.1 2.1-2.4 2.4-2.1-2.1a4 4 0 0 0 5 5L20 16.4 16.4 20l-7.7-7.7a4 4 0 0 0-5 5l2.1-2.1 2.4 2.4-2.1 2.1a4 4 0 0 0 5-5l7.7 7.7 3.6-3.6-7.7-7.7z"/>
                                    </svg>
                                    <span>
                                        <strong>Herramientas</strong>
                                        <small>Catálogos y recursos de soporte</small>
                                    </span>
                                </a>
                            </div>
                        <?php elseif ($publicHeaderRole === 'TECH'): ?>
                            <div class="public-dropdown-section">
                                <span class="public-dropdown-section-title">Soporte técnico</span>

                                <a href="/helpdesk-php/index.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/>
                                    </svg>
                                    <span>
                                        <strong>Panel técnico</strong>
                                        <small>Resumen de atención asignada</small>
                                    </span>
                                </a>

                                <a href="/helpdesk-php/admin-tickets.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 5h16v4a2 2 0 0 0 0 4v6H4v-6a2 2 0 0 0 0-4V5z"/>
                                    </svg>
                                    <span>
                                        <strong>Tickets asignados</strong>
                                        <small>Casos pendientes y en atención</small>
                                    </span>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="public-dropdown-section">
                                <span class="public-dropdown-section-title">Mi cuenta</span>

                                <a href="/helpdesk-php/my-tickets.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 5h16v4a2 2 0 0 0 0 4v6H4v-6a2 2 0 0 0 0-4V5z"/>
                                    </svg>
                                    <span>
                                        <strong>Mis tickets</strong>
                                        <small>Revisar solicitudes y avances</small>
                                    </span>
                                </a>

                                <a href="/helpdesk-php/app/views/client/create-ticket.php">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5z"/>
                                    </svg>
                                    <span>
                                        <strong>Registrar ticket</strong>
                                        <small>Crear una nueva solicitud</small>
                                    </span>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="public-dropdown-section public-dropdown-account">
                            <span class="public-dropdown-section-title">Cuenta</span>

                            <a href="/helpdesk-php/settings.php">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m19.4 13 .1-1-.1-1 2-1.6-2-3.4-2.4 1a8 8 0 0 0-1.7-1L15 3h-4l-.4 3a8 8 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.6-.1 1 .1 1-2 1.6 2 3.4 2.4-1a8 8 0 0 0 1.7 1l.4 3h4l.4-3a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6zM13 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
                                </svg>
                                <span>
                                    <strong>Ajustes</strong>
                                    <small>Preferencias y datos personales</small>
                                </span>
                            </a>
                        </div>

                        <div class="public-dropdown-footer">
                            <a href="/helpdesk-php/logout.php">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M10 17v-3H3v-4h7V7l5 5-5 5zm4-14h7v18h-7v-2h5V5h-5V3z"/>
                                </svg>
                                <span>Cerrar sesión</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a class="public-header-login" href="/helpdesk-php/login.php">Iniciar sesión</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<section class="hero">
    <div class="container hero-grid">
        <div class="hero-text">
            <span class="badge">Sistema web de soporte técnico</span>

            <h1>Bienvenido a la Mesa de Ayuda</h1>

            <p>
                Centraliza tus solicitudes, registra incidencias, consulta el estado de tus tickets
                y mejora la atención de soporte con una plataforma más organizada, trazable y eficiente.
            </p>

            <div class="quick-action-box">
                <?php if (isLoggedIn()): ?>
                    <?php
                    $currentUser = user();
                    $userId = (int)($currentUser['id'] ?? 0);
                    $tickets = [];

                    if ($userId > 0) {
                        $ticketSql = "SELECT id, subject, description, status, priority, created_at
                                      FROM tickets
                                      WHERE requester_id = :user_id
                                      ORDER BY created_at DESC
                                      LIMIT 5";

                        $ticketStatement = $pdo->prepare($ticketSql);
                        $ticketStatement->execute(['user_id' => $userId]);
                        $tickets = $ticketStatement->fetchAll(PDO::FETCH_ASSOC);
                    }
                    ?>

                    <div class="quick-action-header">
                        <div>
                            <h3>Mis tickets recientes</h3>
                            <p>Aquí puedes visualizar las últimas incidencias registradas y su estado actual.</p>
                        </div>

                        <div>
                            <a href="/helpdesk-php/app/views/client/create-ticket.php" class="btn-primary">Crear Ticket</a>
                        </div>
                    </div>

                    <?php if (!empty($tickets)): ?>
                        <div class="ticket-list">
                            <?php foreach ($tickets as $ticket): ?>
                                <div class="ticket-card">
                                    <div class="ticket-card-top">
                                        <div>
                                            <h4><?= htmlspecialchars($ticket['subject']) ?></h4>
                                            <span class="ticket-date">
                                                Creado: <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                            </span>
                                        </div>

                                        <div class="ticket-badges">
                                            <span class="ticket-status status-<?= strtolower($ticket['status']) ?>">
                                                <?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $ticket['status'])))) ?>
                                            </span>
                                            <span class="ticket-priority priority-<?= strtolower($ticket['priority']) ?>">
                                                <?= htmlspecialchars($ticket['priority']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <p class="ticket-description"><?= htmlspecialchars($ticket['description']) ?></p>

                                    <div class="ticket-card-footer">
                                        <span class="ticket-code">Ticket #<?= (int)$ticket['id'] ?></span>
                                        <a href="/helpdesk-php/ticket-detail.php?id=<?= (int)$ticket['id'] ?>" class="ticket-link">Ver detalle</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-ticket-box">
                            <h4>No hay ningún ticket registrado</h4>
                            <p>
                                Aún no has creado incidencias en el sistema. Cuando registres tu primer ticket,
                                aparecerá aquí con su información y estado.
                            </p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="quick-action-content">
                        <h3>Acceso requerido</h3>
                        <p>Para registrar incidencias y ver tus tickets, necesitas iniciar sesión.</p>

                        <div class="quick-actions-buttons">
                            <a href="/helpdesk-php/login.php" class="btn-primary">Iniciar sesión</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="hero-card knowledge-home-card" id="base-conocimientos">
            <div class="hero-card-header knowledge-home-header">
                <div>
                    <span class="card-tag">Centro de ayuda</span>
                    <h3>Base de conocimientos</h3>
                    <p>Busca una solución rápida antes de registrar una nueva incidencia.</p>
                </div>

                <span class="knowledge-total" id="knowledgeTotal">
                    <?= count($knowledgeArticles) ?> artículo<?= count($knowledgeArticles) === 1 ? '' : 's' ?>
                </span>
            </div>

            <div class="knowledge-filters">
                <label class="knowledge-search-field" for="knowledgeSearch">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.3-4.3"></path>
                    </svg>
                    <input
                        type="search"
                        id="knowledgeSearch"
                        placeholder="Buscar problema o palabra clave"
                        autocomplete="off">
                </label>

                <select id="knowledgeCategory" aria-label="Filtrar por categoría">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($knowledgeCategories as $category): ?>
                        <option value="<?= htmlspecialchars($category['code']) ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($knowledgeArticles)): ?>
                <div class="knowledge-list" id="knowledgeList">
                    <?php foreach ($knowledgeArticles as $index => $article): ?>
                        <?php
                        $searchText = implode(' ', [
                            $article['title'] ?? '',
                            $article['problem_summary'] ?? '',
                            $article['keywords'] ?? '',
                            $article['category_name'] ?? '',
                        ]);
                        ?>
                        <a
                            class="knowledge-article knowledge-article-link"
                            href="/helpdesk-php/knowledge-article.php?id=<?= (int)($article['id'] ?? 0) ?>"
                            data-knowledge-article
                            data-category="<?= htmlspecialchars($article['category_code'] ?? 'GENERAL') ?>"
                            data-search="<?= htmlspecialchars($searchText, ENT_QUOTES) ?>"
                            aria-label="Leer artículo <?= htmlspecialchars($article['title'] ?? 'Artículo', ENT_QUOTES) ?>">
                            <span
                                class="knowledge-article-icon"
                                style="--knowledge-color: <?= htmlspecialchars($article['category_color'] ?? '#ff7a00') ?>;">
                                <?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?>
                            </span>

                            <span class="knowledge-article-heading">
                                <strong><?= htmlspecialchars($article['title'] ?? 'Artículo') ?></strong>
                                <small><?= htmlspecialchars($article['category_name'] ?? 'General') ?></small>
                            </span>

                            <span class="knowledge-chevron" aria-hidden="true">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="knowledge-empty-filter" id="knowledgeEmptyFilter" hidden>
                    <strong>No encontramos una solución relacionada</strong>
                    <p>Prueba con otra palabra o selecciona una categoría diferente.</p>
                </div>
            <?php else: ?>
                <div class="knowledge-empty-state">
                    <span>KB</span>
                    <strong>Aún no hay artículos publicados</strong>
                    <p>Las soluciones creadas y activadas desde Herramientas aparecerán en este espacio.</p>
                </div>
            <?php endif; ?>

            <div class="knowledge-home-footer">
                <span>¿No encontraste la solución?</span>
                <?php if (isLoggedIn()): ?>
                    <a href="/helpdesk-php/app/views/client/create-ticket.php">Registrar ticket</a>
                <?php else: ?>
                    <a href="/helpdesk-php/login.php">Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="info-section" id="beneficios">
    <div class="container">
        <div class="section-title">
            <h2>¿Por qué usar este sistema?</h2>
            <p>Diseñado para resolver los problemas comunes de la atención manual, la desorganización y la falta de seguimiento.</p>
        </div>

        <div class="cards-grid">
            <div class="info-card">
                <h3>Atención centralizada</h3>
                <p>Todas las incidencias se registran en un solo lugar, evitando pérdidas de información por llamadas, mensajes o correos dispersos.</p>
            </div>

            <div class="info-card">
                <h3>Trazabilidad completa</h3>
                <p>Cada ticket cuenta con historial, estado, responsable y seguimiento, permitiendo un mejor control del servicio.</p>
            </div>

            <div class="info-card">
                <h3>Mayor eficiencia</h3>
                <p>El sistema ayuda a reducir tiempos de respuesta, mejorar la organización y elevar la calidad de atención al usuario.</p>
            </div>
        </div>
    </div>
</section>

<section class="about-section">
    <div class="container about-box">
        <div>
            <h2>Un sistema pensado para resolver problemáticas reales</h2>
            <p>
                Esta plataforma nace para mejorar la gestión de soporte, reducir la demora en la atención,
                disminuir la desorganización y ofrecer una experiencia más clara tanto para usuarios como para técnicos.
            </p>
        </div>

        <div>
            <h3>Objetivo principal</h3>
            <p>Brindar una solución web que permita gestionar incidencias de manera ordenada, ágil y medible.</p>
        </div>
    </div>
</section>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>© 2026 Mesa de Ayuda. Todos los derechos reservados.</p>
        <p>Proyecto de sistema web de soporte técnico</p>
    </div>
</footer>

<script>
(function () {
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const userMenu = document.querySelector('.user-menu');
    const notificationMenu = document.querySelector('.notification-menu');

    window.toggleUserMenu = function () {
        if (notificationDropdown) notificationDropdown.classList.remove('show');
        if (userDropdown) userDropdown.classList.toggle('show');
    };

    window.toggleNotificationMenu = function () {
        if (userDropdown) userDropdown.classList.remove('show');
        if (notificationDropdown) notificationDropdown.classList.toggle('show');
    };

    document.addEventListener('click', function (event) {
        if (userMenu && userDropdown && !userMenu.contains(event.target)) {
            userDropdown.classList.remove('show');
        }

        if (notificationMenu && notificationDropdown && !notificationMenu.contains(event.target)) {
            notificationDropdown.classList.remove('show');
        }
    });

    const searchInput = document.getElementById('knowledgeSearch');
    const categorySelect = document.getElementById('knowledgeCategory');
    const articleElements = Array.from(document.querySelectorAll('[data-knowledge-article]'));
    const emptyFilter = document.getElementById('knowledgeEmptyFilter');
    const totalLabel = document.getElementById('knowledgeTotal');

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function filterKnowledge() {
        if (!articleElements.length) return;

        const term = normalizeText(searchInput ? searchInput.value : '');
        const category = categorySelect ? categorySelect.value : '';
        let visibleCount = 0;

        articleElements.forEach(function (article) {
            const articleText = normalizeText(article.dataset.search);
            const articleCategory = article.dataset.category || '';
            const matchesTerm = !term || articleText.includes(term);
            const matchesCategory = !category || articleCategory === category;
            const isVisible = matchesTerm && matchesCategory;

            article.hidden = !isVisible;
            if (isVisible) visibleCount += 1;
        });

        if (emptyFilter) emptyFilter.hidden = visibleCount !== 0;

        if (totalLabel) {
            totalLabel.textContent = visibleCount + ' artículo' + (visibleCount === 1 ? '' : 's');
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterKnowledge);
    if (categorySelect) categorySelect.addEventListener('change', filterKnowledge);
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
