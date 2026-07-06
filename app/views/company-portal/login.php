<?php
/**
 * Variables entregadas por company-login.php.
 * Los valores predeterminados mantienen la vista estable y permiten que
 * Intelephense reconozca correctamente los tipos de cada variable.
 *
 * @var bool   $moduleReady
 * @var string $loginCsrfToken
 * @var string $noticeMessage
 * @var string $errorMessage
 */
$moduleReady   = isset($moduleReady) ? (bool) $moduleReady : false;
$loginCsrfToken = isset($loginCsrfToken) ? (string) $loginCsrfToken : '';
$noticeMessage  = isset($noticeMessage) ? (string) $noticeMessage : '';
$errorMessage   = isset($errorMessage) ? (string) $errorMessage : '';

$cssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/company-portal.css';
$cssVersion = is_file($cssPath) ? filemtime($cssPath) : time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal corporativo - HelpDesk Pronet System</title>
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth-app.css?v=20260704-remember-1">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/company-portal.css?v=<?= $cssVersion ?>">
    <link rel="icon" type="image/png" href="/helpdesk-php/public/favicon/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/helpdesk-php/public/favicon/favicon.svg">
    <link rel="shortcut icon" href="/helpdesk-php/public/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/helpdesk-php/public/favicon/apple-touch-icon.png">
    <meta name="theme-color" content="#123c37">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
    <script src="/helpdesk-php/public/assets/js/password-visibility.js?v=20260703-login-pro-1" defer></script>
    <script src="/helpdesk-php/public/assets/js/login-submit.js?v=20260704-remember-1" defer></script>
</head>
<body class="company-portal-login-body">
<div class="company-login-wrapper">
    <section class="company-login-shell">
        <aside class="company-login-brand">
            <div class="company-login-brand-top">
                <span class="company-login-brand-mark"><i class="fa-solid fa-building-shield"></i></span>
                <div>
                    <strong>Portal corporativo</strong>
                    <small>HelpDesk Pronet System</small>
                </div>
            </div>

            <div class="company-login-brand-copy">
                <span>Gestión exclusiva por empresa</span>
                <h1>Tu organización, sus usuarios y todos sus tickets en un solo lugar.</h1>
                <p>Consulta el servicio contratado, supervisa incidencias y administra el acceso de los contactos de tu empresa.</p>
            </div>

            <div class="company-login-trust">
                <div><i class="fa-solid fa-shield-halved"></i><span>Acceso separado del panel general</span></div>
                <div><i class="fa-solid fa-lock"></i><span>Información limitada a tu organización</span></div>
            </div>
        </aside>

        <main class="company-login-card">
            <div class="company-login-card-inner">
                <div class="company-login-heading">
                    <span><i class="fa-solid fa-building-user"></i></span>
                    <div>
                        <small>Cuenta de empresa</small>
                        <h2>Ingresar al portal</h2>
                        <p>Utiliza las credenciales corporativas asignadas por Pronet.</p>
                    </div>
                </div>

                <?php if (!$moduleReady): ?>
                    <div class="company-alert is-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Falta ejecutar <strong>database/company_portal.sql</strong>.</span>
                    </div>
                <?php endif; ?>

                <?php if ($noticeMessage !== ''): ?>
                    <div class="company-alert is-info">
                        <i class="fa-solid fa-circle-info"></i>
                        <span><?= htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="company-alert is-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/helpdesk-php/company-login.php" class="company-login-form" autocomplete="on" data-login-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($loginCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <label class="company-form-field" for="company_email">
                        <span>Correo corporativo</span>
                        <div>
                            <i class="fa-solid fa-envelope company-field-icon"></i>
                            <input
                                type="email"
                                id="company_email"
                                name="email"
                                value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="portal@empresa.com"
                                autocomplete="email"
                                required
                                <?= !$moduleReady ? 'disabled' : '' ?>
                            >
                        </div>
                    </label>

                    <label class="company-form-field" for="company_password">
                        <span>Contraseña</span>
                        <div class="company-password-wrap">
                            <i class="fa-solid fa-key company-field-icon"></i>
                            <input
                                type="password"
                                id="company_password"
                                name="password"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="current-password"
                                data-password-input
                                required
                                <?= !$moduleReady ? 'disabled' : '' ?>
                            >
                            <button
                                type="button"
                                class="company-password-toggle"
                                data-password-toggle
                                aria-controls="company_password"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false"
                                title="Mostrar contraseña"
                                <?= !$moduleReady ? 'disabled' : '' ?>
                            >
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small class="password-caps-warning company-caps-warning" data-caps-warning="company_password" role="status" aria-live="polite" hidden>
                            <i class="fa-solid fa-arrow-up-a-z"></i>
                            Bloq Mayús está activado
                        </small>
                    </label>

                    <label class="login-remember-option company-remember-option">
                        <input
                            type="checkbox"
                            name="remember_me"
                            value="1"
                            <?= isset($_POST['remember_me']) ? 'checked' : '' ?>
                            <?= !$moduleReady ? 'disabled' : '' ?>
                        >
                        <span class="login-remember-control" aria-hidden="true">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        <span class="login-remember-copy">
                            <strong>Recordar esta cuenta corporativa</strong>
                            <small>Mantendrá el acceso durante 14 días en este dispositivo.</small>
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="company-primary-button"
                        data-login-submit
                        data-loading-text="Validando portal..."
                        <?= !$moduleReady ? 'disabled' : '' ?>
                    >
                        <span data-submit-text>Ingresar al portal</span>
                        <i class="fa-solid fa-arrow-right" data-submit-icon></i>
                    </button>
                </form>

                <div class="company-login-divider"><span>otro tipo de acceso</span></div>

                <a href="/helpdesk-php/login.php" class="company-login-back">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Ingresar como usuario</span>
                </a>

                <p class="company-login-legal">
                    <i class="fa-solid fa-lock"></i>
                    Acceso exclusivo para representantes autorizados de la empresa.
                </p>
            </div>
        </main>
    </section>
</div>
</body>
</html>
