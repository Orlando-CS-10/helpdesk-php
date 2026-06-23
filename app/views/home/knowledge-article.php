<?php
require_once __DIR__ . '/../../helpers/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/knowledge_base.php';

$articleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$article = null;
$relatedArticles = [];
$articleAttachments = [];
$imageAttachments = [];
$documentAttachments = [];
$knowledgeAvailable = false;

if ($articleId && $articleId > 0) {
    try {
        $tableStatement = $pdo->query("SHOW TABLES LIKE 'knowledge_base_articles'");
        $knowledgeAvailable = (bool)($tableStatement && $tableStatement->fetchColumn());

        if ($knowledgeAvailable) {
            $contentColumnStatement = $pdo->query("SHOW COLUMNS FROM knowledge_base_articles LIKE 'content_html'");
            $contentColumnAvailable = (bool)($contentColumnStatement && $contentColumnStatement->fetchColumn());
            $contentSelect = $contentColumnAvailable ? 'k.content_html' : 'NULL AS content_html';

            $articleSql = "SELECT
                               k.id,
                               k.title,
                               k.category_id,
                               k.problem_summary,
                               k.solution_steps,
                               {$contentSelect},
                               k.keywords,
                               k.created_at,
                               k.updated_at,
                               COALESCE(c.name, 'General') AS category_name,
                               COALESCE(c.code, 'GENERAL') AS category_code,
                               COALESCE(c.color, '#ff7a00') AS category_color
                           FROM knowledge_base_articles k
                           LEFT JOIN ticket_categories c ON c.id = k.category_id
                           WHERE k.id = :article_id
                             AND k.is_active = 1
                             AND (c.id IS NULL OR c.is_active = 1)
                           LIMIT 1";

            $articleStatement = $pdo->prepare($articleSql);
            $articleStatement->execute(['article_id' => (int)$articleId]);
            $article = $articleStatement->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($article) {
                $storedContent = trim((string)($article['content_html'] ?? ''));
                if ($storedContent === '') {
                    $storedContent = knowledgeBaseLegacyToHtml((string)($article['solution_steps'] ?? ''));
                }
                $article['content_html'] = knowledgeBaseSanitizeHtml($storedContent);

                $attachmentsTableStatement = $pdo->query("SHOW TABLES LIKE 'knowledge_base_attachments'");
                $attachmentsAvailable = (bool)($attachmentsTableStatement && $attachmentsTableStatement->fetchColumn());

                if ($attachmentsAvailable) {
                    $attachmentStatement = $pdo->prepare(
                        'SELECT id, original_name, mime_type, file_size, is_image, created_at
                         FROM knowledge_base_attachments
                         WHERE article_id = :article_id
                         ORDER BY is_image DESC, created_at ASC, id ASC'
                    );
                    $attachmentStatement->execute(['article_id' => (int)$articleId]);
                    $articleAttachments = $attachmentStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    foreach ($articleAttachments as &$attachment) {
                        $attachment['formatted_size'] = knowledgeBaseFormatBytes((int)($attachment['file_size'] ?? 0));
                        $attachment['view_url'] = '/helpdesk-php/knowledge-attachment.php?id=' . (int)$attachment['id'];
                        $attachment['download_url'] = '/helpdesk-php/knowledge-attachment.php?id=' . (int)$attachment['id'] . '&download=1';
                    }
                    unset($attachment);

                    $imageAttachments = array_values(array_filter(
                        $articleAttachments,
                        static fn(array $attachment): bool => (int)($attachment['is_image'] ?? 0) === 1
                    ));
                    $documentAttachments = array_values(array_filter(
                        $articleAttachments,
                        static fn(array $attachment): bool => (int)($attachment['is_image'] ?? 0) !== 1
                    ));
                }
                $relatedSql = "SELECT
                                   k.id,
                                   k.title,
                                   k.problem_summary,
                                   COALESCE(c.name, 'General') AS category_name,
                                   COALESCE(c.code, 'GENERAL') AS category_code,
                                   COALESCE(c.color, '#ff7a00') AS category_color
                               FROM knowledge_base_articles k
                               LEFT JOIN ticket_categories c ON c.id = k.category_id
                               WHERE k.is_active = 1
                                 AND k.id <> :article_id
                                 AND (c.id IS NULL OR c.is_active = 1)";

                $relatedParams = ['article_id' => (int)$articleId];

                if (!empty($article['category_id'])) {
                    $relatedSql .= " ORDER BY CASE WHEN k.category_id = :category_id THEN 0 ELSE 1 END,
                                             k.updated_at DESC,
                                             k.title ASC";
                    $relatedParams['category_id'] = (int)$article['category_id'];
                } else {
                    $relatedSql .= " ORDER BY k.updated_at DESC, k.title ASC";
                }

                $relatedSql .= " LIMIT 4";

                $relatedStatement = $pdo->prepare($relatedSql);
                $relatedStatement->execute($relatedParams);
                $relatedArticles = $relatedStatement->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $exception) {
        $article = null;
        $relatedArticles = [];
    }
}

if (!$article) {
    http_response_code(404);
}

$title = $article
    ? ($article['title'] . ' - Base de conocimientos')
    : 'Artículo no encontrado - Base de conocimientos';

$useClientLayout = true;
require_once __DIR__ . '/../layouts/header.php';

$unreadNotifications = $unreadNotifications ?? 0;
$notifications = $notifications ?? [];

$contentHtml = $article ? (string)($article['content_html'] ?? '') : '';
$keywords = $article
    ? array_values(array_filter(array_map('trim', explode(',', (string)($article['keywords'] ?? '')))))
    : [];
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

<main class="knowledge-detail-page">
    <div class="container">
        <nav class="knowledge-breadcrumb" aria-label="Ruta de navegación">
            <a href="/helpdesk-php/home.php">Inicio</a>
            <span>/</span>
            <a href="/helpdesk-php/home.php#base-conocimientos">Base de conocimientos</a>
            <?php if ($article): ?>
                <span>/</span>
                <span><?= htmlspecialchars($article['title']) ?></span>
            <?php endif; ?>
        </nav>

        <?php if ($article): ?>
            <div class="knowledge-detail-layout">
                <article class="knowledge-detail-article">
                    <header class="knowledge-detail-header">
                        <div class="knowledge-detail-meta">
                            <span
                                class="knowledge-category-badge"
                                style="--knowledge-color: <?= htmlspecialchars($article['category_color'] ?? '#ff7a00') ?>;">
                                <?= htmlspecialchars($article['category_name'] ?? 'General') ?>
                            </span>
                            <span>Actualizado el <?= date('d/m/Y', strtotime((string)$article['updated_at'])) ?></span>
                        </div>

                        <h1><?= htmlspecialchars($article['title']) ?></h1>
                        <p><?= nl2br(htmlspecialchars($article['problem_summary'] ?? '')) ?></p>
                    </header>

                    <section class="knowledge-detail-solution">
                        <div class="knowledge-section-heading">
                            <span>Contenido</span>
                            <h2>Desarrollo del artículo</h2>
                        </div>

                        <?php if ($contentHtml !== ''): ?>
                            <div class="knowledge-rich-content">
                                <?= $contentHtml ?>
                            </div>
                        <?php else: ?>
                            <p class="knowledge-solution-text">Este artículo todavía no tiene contenido registrado.</p>
                        <?php endif; ?>
                    </section>

                    <?php if (!empty($imageAttachments)): ?>
                        <section class="knowledge-detail-media">
                            <div class="knowledge-section-heading">
                                <span>Imágenes</span>
                                <h2>Material visual</h2>
                            </div>

                            <div class="knowledge-image-gallery">
                                <?php foreach ($imageAttachments as $attachment): ?>
                                    <a
                                        class="knowledge-image-card"
                                        href="<?= htmlspecialchars($attachment['view_url']) ?>"
                                        target="_blank"
                                        rel="noopener">
                                        <img
                                            src="<?= htmlspecialchars($attachment['view_url']) ?>"
                                            alt="<?= htmlspecialchars($attachment['original_name'] ?? 'Imagen del artículo') ?>"
                                            loading="lazy">
                                        <span>
                                            <strong><?= htmlspecialchars($attachment['original_name'] ?? 'Imagen') ?></strong>
                                            <small><?= htmlspecialchars($attachment['formatted_size'] ?? '') ?></small>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if (!empty($documentAttachments)): ?>
                        <section class="knowledge-detail-attachments">
                            <div class="knowledge-section-heading">
                                <span>Archivos</span>
                                <h2>Documentos adjuntos</h2>
                            </div>

                            <div class="knowledge-attachment-list">
                                <?php foreach ($documentAttachments as $attachment): ?>
                                    <?php
                                    $extension = strtoupper((string)pathinfo((string)($attachment['original_name'] ?? ''), PATHINFO_EXTENSION));
                                    ?>
                                    <div class="knowledge-attachment-card">
                                        <span class="knowledge-attachment-type"><?= htmlspecialchars($extension !== '' ? $extension : 'ARCHIVO') ?></span>
                                        <span class="knowledge-attachment-info">
                                            <strong><?= htmlspecialchars($attachment['original_name'] ?? 'Archivo adjunto') ?></strong>
                                            <small><?= htmlspecialchars($attachment['formatted_size'] ?? '') ?></small>
                                        </span>
                                        <span class="knowledge-attachment-actions">
                                            <a href="<?= htmlspecialchars($attachment['view_url']) ?>" target="_blank" rel="noopener">Ver</a>
                                            <a href="<?= htmlspecialchars($attachment['download_url']) ?>">Descargar</a>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if (!empty($keywords)): ?>
                        <section class="knowledge-detail-keywords">
                            <strong>Temas relacionados</strong>
                            <div>
                                <?php foreach ($keywords as $keyword): ?>
                                    <span><?= htmlspecialchars($keyword) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="knowledge-detail-help">
                        <div>
                            <strong>¿El problema continúa?</strong>
                            <p>Registra un ticket para que el equipo técnico pueda revisar tu caso.</p>
                        </div>

                        <?php if (isLoggedIn()): ?>
                            <a href="/helpdesk-php/app/views/client/create-ticket.php" class="btn-primary">Registrar ticket</a>
                        <?php else: ?>
                            <a href="/helpdesk-php/login.php" class="btn-primary">Iniciar sesión</a>
                        <?php endif; ?>
                    </section>
                </article>

                <aside class="knowledge-related-panel">
                    <div class="knowledge-related-header">
                        <span>También podría interesarte</span>
                        <h2>Artículos relacionados</h2>
                        <p>Otras soluciones de la base de conocimientos que podrían ayudarte.</p>
                    </div>

                    <?php if (!empty($relatedArticles)): ?>
                        <div class="knowledge-related-list">
                            <?php foreach ($relatedArticles as $related): ?>
                                <a
                                    class="knowledge-related-card"
                                    href="/helpdesk-php/knowledge-article.php?id=<?= (int)$related['id'] ?>">
                                    <span
                                        class="knowledge-related-icon"
                                        style="--knowledge-color: <?= htmlspecialchars($related['category_color'] ?? '#ff7a00') ?>;">
                                        <?= htmlspecialchars(strtoupper(substr((string)($related['category_name'] ?? 'G'), 0, 1))) ?>
                                    </span>

                                    <span class="knowledge-related-content">
                                        <small><?= htmlspecialchars($related['category_name'] ?? 'General') ?></small>
                                        <strong><?= htmlspecialchars($related['title']) ?></strong>
                                        <span><?= htmlspecialchars(mb_strimwidth((string)($related['problem_summary'] ?? ''), 0, 105, '…', 'UTF-8')) ?></span>
                                    </span>

                                    <span class="knowledge-related-arrow" aria-hidden="true">→</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="knowledge-related-empty">
                            <strong>No hay más artículos publicados</strong>
                            <p>Pronto aparecerán nuevas soluciones en esta sección.</p>
                        </div>
                    <?php endif; ?>

                    <a class="knowledge-back-link" href="/helpdesk-php/home.php#base-conocimientos">
                        ← Volver a la base de conocimientos
                    </a>
                </aside>
            </div>
        <?php else: ?>
            <section class="knowledge-not-found">
                <span>404</span>
                <h1>Artículo no encontrado</h1>
                <p>El artículo fue eliminado, está desactivado o el enlace no es válido.</p>
                <a href="/helpdesk-php/home.php#base-conocimientos" class="btn-primary">Ver base de conocimientos</a>
            </section>
        <?php endif; ?>
    </div>
</main>

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
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
