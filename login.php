<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/controllers/AuthController.php';
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/helpers/system_security.php';
require_once __DIR__ . '/app/helpers/remember_me.php';
require_once __DIR__ . '/app/helpers/login_two_factor.php';

$reason = trim((string) ($_GET['reason'] ?? ''));
$twoFactorActive = loginTwoFactorActive($pdo);
$skipPersistentRestore = ['idle_timeout', 'absolute_timeout', 'revoked', 'inactive_user'];

if (!isLoggedIn()
    && !$twoFactorActive
    && !in_array($reason, $skipPersistentRestore, true)
) {
    authRememberAttempt($pdo);
}

if (isLoggedIn()) {
    $sessionCheck = enforceCurrentSecuritySession();

    if (!empty($sessionCheck['valid'])) {
        if (!empty($sessionCheck['force_password_change'])) {
            header('Location: /helpdesk-php/change-password.php');
        } else {
            header('Location: /helpdesk-php/index.php');
        }
        exit;
    }
}

$authController = new AuthController($pdo);
$errorMessage = '';
$noticeMessage = '';
$loginCsrfToken = systemSecurityCsrfToken('login');

$reasonMessages = [
    'idle_timeout' => 'Tu sesión se cerró por inactividad. Ingresa nuevamente.',
    'absolute_timeout' => 'Tu sesión alcanzó su duración máxima. Ingresa nuevamente.',
    'revoked' => 'La sesión fue cerrada desde el panel de seguridad.',
    'inactive_user' => 'La cuenta ya no tiene acceso al sistema.',
    'expired' => 'La sesión venció. Ingresa nuevamente.',
    '2fa_expired' => 'El código de verificación venció. Ingresa nuevamente para recibir uno nuevo.',
];

if (isset($reasonMessages[$reason])) {
    $noticeMessage = $reasonMessages[$reason];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'login')) {
        $errorMessage = 'El formulario venció. Recarga la página e inténtalo nuevamente.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rememberMe = !$twoFactorActive && isset($_POST['remember_me']) && (string) $_POST['remember_me'] === '1';

        $result = $authController->login($email, $password, $rememberMe);

        if (!empty($result['requires_2fa'])) {
            header('Location: /helpdesk-php/verify-2fa.php');
            exit;
        }

        if (!empty($result['success'])) {
            if (!empty($result['force_password_change'])) {
                header('Location: /helpdesk-php/change-password.php');
            } else {
                header('Location: /helpdesk-php/index.php');
            }
            exit;
        }

        $errorMessage = $result['message'] ?? 'No se pudo iniciar sesión.';
    }
}

$authCssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/auth-app.css';
$authCssVersion = file_exists($authCssPath) ? filemtime($authCssPath) : time();
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso - HelpDesk Pronet System</title>
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth-app.css?v=<?= $authCssVersion ?>">
    <link rel="icon" type="image/png" href="/helpdesk-php/public/favicon/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/helpdesk-php/public/favicon/favicon.svg">
    <link rel="shortcut icon" href="/helpdesk-php/public/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/helpdesk-php/public/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/helpdesk-php/public/favicon/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="HelpDesk">
    <meta name="application-name" content="HelpDesk">
    <meta name="theme-color" content="#0f3d2e">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
    <script src="/helpdesk-php/public/assets/js/password-visibility.js?v=20260703-login-pro-1" defer></script>
    <script src="/helpdesk-php/public/assets/js/login-submit.js?v=20260704-remember-1" defer></script>
</head>

<body>

    <div class="login-wrapper login-published">
        <section class="login-shell">
            <aside class="login-brand-panel">
                <div class="login-brand-top">
                    <div class="login-brand-mark">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <div>
                        <strong>Mesa de Ayuda</strong>
                        <small>Pronet System</small>
                    </div>
                </div>

                <div class="login-brand-content">
                    <span class="login-mini-label">Soporte corporativo</span>
                    <h1>HelpDesk Pronet System</h1>
                    <p>Gestión de incidencias, atención técnica y seguimiento de solicitudes.</p>
                </div>
            </aside>

            <main class="login-card">
                <div class="login-card-inner">
                    <div class="login-card-header">
                        <div class="login-icon">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div>
                            <h2>Iniciar sesión</h2>
                            <p class="subtitle">Accede con tu cuenta autorizada.</p>
                        </div>
                    </div>

                    <?php if ($noticeMessage !== ''): ?>
                        <div class="alert success login-alert">
                            <i class="fa-solid fa-circle-info"></i>
                            <span><?= htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert error login-alert">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/helpdesk-php/login.php" class="login-form" autocomplete="on" data-login-form>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($loginCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group login-input-group">
                            <label for="email">Correo</label>
                            <div class="login-input-wrap">
                                <i class="fa-solid fa-envelope login-field-icon"></i>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="correo@empresa.com"
                                    autocomplete="email"
                                    required>
                            </div>
                        </div>

                        <div class="form-group login-input-group">
                            <div class="login-label-row">
                                <label for="password">Contraseña</label>
                                <a href="/helpdesk-php/forgot-password.php" class="forgot-link">Recuperar contraseña</a>
                            </div>

                            <div class="login-input-wrap has-password">
                                <i class="fa-solid fa-key login-field-icon"></i>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Ingresa tu contraseña"
                                    autocomplete="current-password"
                                    data-password-input
                                    required>
                                <button
                                    type="button"
                                    class="login-password-toggle"
                                    data-password-toggle
                                    aria-controls="password"
                                    aria-label="Mostrar contraseña"
                                    aria-pressed="false"
                                    title="Mostrar contraseña"
                                >
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <small class="password-caps-warning" data-caps-warning="password" role="status" aria-live="polite" hidden>
                                <i class="fa-solid fa-arrow-up-a-z"></i>
                                Bloq Mayús está activado
                            </small>
                        </div>

                        <?php if (!$twoFactorActive): ?>
                            <label class="login-remember-option">
                                <input
                                    type="checkbox"
                                    name="remember_me"
                                    value="1"
                                    <?= isset($_POST['remember_me']) ? 'checked' : '' ?>
                                >
                                <span class="login-remember-control" aria-hidden="true">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                                <span class="login-remember-copy">
                                    <strong>Recordarme en este dispositivo</strong>
                                    <small>Mantendrá tu acceso durante 14 días.</small>
                                </span>
                            </label>
                        <?php else: ?>
                            <div class="login-remember-option" style="cursor: default;">
                                <span class="login-remember-control" aria-hidden="true">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </span>
                                <span class="login-remember-copy">
                                    <strong>Verificación de dos pasos activa</strong>
                                    <small>Se solicitará un código enviado a tu correo en cada inicio de sesión.</small>
                                </span>
                            </div>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="btn-primary login-submit"
                            data-login-submit
                            data-loading-text="Validando acceso..."
                        >
                            <span data-submit-text>Ingresar</span>
                            <i class="fa-solid fa-arrow-right" data-submit-icon></i>
                        </button>
                    </form>

                    <div class="login-company-entry">
                        <a href="/helpdesk-php/company-login.php">
                            <i class="fa-solid fa-building-shield"></i>
                            <span>Ingresar al Portal corporativo</span>
                        </a>
                    </div>

                    <p class="login-legal-note">
                        <i class="fa-solid fa-lock"></i>
                        Acceso exclusivo para usuarios autorizados.
                    </p>
                </div>
            </main>
        </section>
    </div>

</body>

</html>