<?php
require_once __DIR__ . '/../../helpers/session.php';

$notifications = [];
$unreadNotifications = 0;
$currentUser = null;
$currentRole = null;

if (isLoggedIn()) {
    require_once __DIR__ . '/../../config/database.php';

    $currentUser = user();
    $currentRole = strtoupper((string) ($currentUser['role'] ?? ''));

    /*
     * Recupera la conexión tanto del ámbito actual como del global.
     * El encabezado no debe detener todo el panel si las notificaciones
     * no pueden consultarse por un problema temporal de base de datos.
     */
    $layoutPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);

    if ($layoutPdo instanceof PDO && !empty($currentUser['id'])) {
        try {
            $sqlNotifications = "SELECT id, title, message, type, is_read, related_ticket_id, created_at
                                 FROM notifications
                                 WHERE user_id = :user_id
                                 ORDER BY created_at DESC
                                 LIMIT 8";

            $stmtNotifications = $layoutPdo->prepare($sqlNotifications);
            $stmtNotifications->execute([
                'user_id' => (int) $currentUser['id'],
            ]);

            $notifications = $stmtNotifications->fetchAll(PDO::FETCH_ASSOC);

            $sqlUnread = "SELECT COUNT(*) AS total
                          FROM notifications
                          WHERE user_id = :user_id
                            AND is_read = 0";

            $stmtUnread = $layoutPdo->prepare($sqlUnread);
            $stmtUnread->execute([
                'user_id' => (int) $currentUser['id'],
            ]);

            $unreadNotifications = (int) ($stmtUnread->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } catch (Throwable $exception) {
            $notifications = [];
            $unreadNotifications = 0;
        }
    }
}

/*
 * Se usan tres paquetes de estilos independientes:
 *
 * auth-app.css   -> inicio de sesión.
 * client-app.css -> portada y vistas del cliente.
 * app.css        -> panel administrativo y técnico.
 *
 * Las vistas pueden forzar uno de los diseños definiendo antes de
 * incluir este archivo: $useAuthLayout o $useClientLayout.
 */
$useAuthLayout = (bool) ($useAuthLayout ?? false);
$useClientLayout = (bool) ($useClientLayout ?? false);
$useCompanyPortalLayout = (bool) ($useCompanyPortalLayout ?? false);

$currentScript = basename((string) parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH));
$publicLayoutScripts = ['home.php', 'knowledge-article.php'];
$authLayoutScripts = ['login.php'];

if ($useCompanyPortalLayout) {
    /*
     * El Portal corporativo reutiliza el mismo paquete visual del panel.
     * La sesión continúa siendo independiente; solo se comparte la capa UI.
     */
    $cssEntryFile = 'app.css';
} elseif ($useAuthLayout || in_array($currentScript, $authLayoutScripts, true)) {
    $cssEntryFile = 'auth-app.css';
} elseif ($useClientLayout || in_array($currentScript, $publicLayoutScripts, true)) {
    /*
     * La portada y los artículos siempre usan el diseño público,
     * incluso cuando los abre un administrador o un técnico.
     */
    $cssEntryFile = 'client-app.css';
} elseif (!isLoggedIn()) {
    $cssEntryFile = 'auth-app.css';
} elseif ($currentRole === 'CLIENT') {
    $cssEntryFile = 'client-app.css';
} else {
    $cssEntryFile = 'app.css';
}

$cssBaseUrl = '/helpdesk-php/public/assets/css/';
$projectRoot = dirname(__DIR__, 3);
$cssEntryPath = $projectRoot . '/public/assets/css/' . $cssEntryFile;
$cssVersion = file_exists($cssEntryPath) ? filemtime($cssEntryPath) : time();

$ticketCssScripts = [
    'ticket-detail.php',
    'my-tickets.php',
    'admin-tickets.php',
    'create-ticket.php',
    'edit-message.php',
];
$shouldLoadTicketCss = in_array($currentScript, $ticketCssScripts, true);
$ticketCssPath = $projectRoot . '/public/assets/css/tickets.css';
$ticketCssVersion = file_exists($ticketCssPath) ? filemtime($ticketCssPath) : time();

/*
 * Personalización global del panel.
 * Si la tabla todavía no existe, el helper devuelve la paleta original.
 */
$systemCustomization = [];
$systemCustomizationCss = '';
$systemThemeSetting = 'light';
$systemSidebarDefault = 'expanded';
$systemCustomizationHelper = __DIR__ . '/../../helpers/system_customization.php';

if (is_file($systemCustomizationHelper)) {
    require_once $systemCustomizationHelper;

    if (function_exists('systemCustomizationDefaults')) {
        $systemCustomization = systemCustomizationDefaults();
    }

    if (isset($pdo) && $pdo instanceof PDO && function_exists('getSystemCustomization')) {
        $systemCustomization = getSystemCustomization($pdo);
    }

    if (function_exists('systemCustomizationCssVariables')) {
        $systemCustomizationCss = systemCustomizationCssVariables($systemCustomization);
    }

    $candidateTheme = (string) ($systemCustomization['theme'] ?? 'light');
    $candidateSidebar = (string) ($systemCustomization['sidebar_default'] ?? 'expanded');

    $systemThemeSetting = in_array($candidateTheme, ['light', 'dark', 'auto'], true)
        ? $candidateTheme
        : 'light';
    $systemSidebarDefault = in_array($candidateSidebar, ['expanded', 'collapsed'], true)
        ? $candidateSidebar
        : 'expanded';
}

$sidebarStorageKey = $useCompanyPortalLayout
    ? 'helpdesk_company_sidebar_collapsed'
    : 'helpdesk_admin_sidebar_collapsed';
?>
<!doctype html>
<html lang="es" data-system-theme-setting="<?= htmlspecialchars($systemThemeSetting, ENT_QUOTES, 'UTF-8') ?>" data-system-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Mesa de Ayuda', ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="icon" type="image/png" href="/helpdesk-php/public/favicon/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/helpdesk-php/public/favicon/favicon.svg">
    <link rel="shortcut icon" href="/helpdesk-php/public/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/helpdesk-php/public/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/helpdesk-php/public/favicon/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="HelpDesk">
    <meta name="application-name" content="HelpDesk">
    <meta name="theme-color" content="#0f3d2e">

    <script>
        (function() {
            const root = document.documentElement;
            const setting = root.dataset.systemThemeSetting || 'light';
            const media = window.matchMedia ?
                window.matchMedia('(prefers-color-scheme: dark)') :
                null;

            function applyResolvedTheme() {
                const resolved = setting === 'auto' ?
                    (media && media.matches ? 'dark' : 'light') :
                    (setting === 'dark' ? 'dark' : 'light');

                root.dataset.systemTheme = resolved;
                root.style.colorScheme = resolved;
            }

            applyResolvedTheme();

            if (setting === 'auto' && media) {
                if (typeof media.addEventListener === 'function') {
                    media.addEventListener('change', applyResolvedTheme);
                } else if (typeof media.addListener === 'function') {
                    media.addListener(applyResolvedTheme);
                }
            }

            let sidebarCollapsed = <?= $systemSidebarDefault === 'collapsed' ? 'true' : 'false' ?>;

            try {
                const savedSidebarState = localStorage.getItem(<?= json_encode($sidebarStorageKey, JSON_UNESCAPED_SLASHES) ?>);

                if (savedSidebarState !== null) {
                    sidebarCollapsed = savedSidebarState === '1';
                }
            } catch (error) {
                // Se conserva el valor predeterminado cuando localStorage no está disponible.
            }

            root.classList.toggle('admin-sidebar-initial-collapsed', sidebarCollapsed);
        })();
    </script>

    <link rel="stylesheet"
        href="<?= htmlspecialchars($cssBaseUrl . $cssEntryFile, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int) $cssVersion ?>">

    <?php if (!empty($shouldLoadTicketCss)): ?>
        <link rel="stylesheet"
            href="<?= htmlspecialchars($cssBaseUrl . 'tickets.css', ENT_QUOTES, 'UTF-8') ?>?v=<?= (int) $ticketCssVersion ?>">
    <?php endif; ?>

    <?php if ($systemCustomizationCss !== ''): ?>
        <style id="systemCustomizationVariables">
            <?= $systemCustomizationCss ?>
        </style>
    <?php endif; ?>

    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    $toastMessage = '';
    $toastType = '';

    if (!empty($_SESSION['ticket_success'])) {
        $toastMessage = (string) $_SESSION['ticket_success'];
        $toastType = 'success';
        unset($_SESSION['ticket_success']);
    } elseif (!empty($_SESSION['ticket_error'])) {
        $toastMessage = (string) $_SESSION['ticket_error'];
        $toastType = 'error';
        unset($_SESSION['ticket_error']);
    } elseif (!empty($_SESSION['settings_success'])) {
        $toastMessage = (string) $_SESSION['settings_success'];
        $toastType = 'success';
        unset($_SESSION['settings_success']);
    } elseif (!empty($_SESSION['settings_error'])) {
        $toastMessage = (string) $_SESSION['settings_error'];
        $toastType = 'error';
        unset($_SESSION['settings_error']);
    }
    ?>

    <?php if ($toastMessage !== ''): ?>
        <div class="toast-container">
            <div class="toast toast-<?= htmlspecialchars($toastType, ENT_QUOTES, 'UTF-8') ?>" id="appToast">
                <div class="toast-content">
                    <strong class="toast-title">
                        <?= $toastType === 'success' ? 'Correcto' : 'Atención' ?>
                    </strong>
                    <p class="toast-message"><?= htmlspecialchars($toastMessage, ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <button type="button" class="toast-close" onclick="closeToast()" aria-label="Cerrar notificación">×</button>
            </div>
        </div>

        <script>
            function closeToast() {
                const toast = document.getElementById('appToast');

                if (!toast) {
                    return;
                }

                toast.classList.add('toast-hide');
                window.setTimeout(() => toast.remove(), 300);
            }

            window.setTimeout(closeToast, 4000);
        </script>
    <?php endif; ?>