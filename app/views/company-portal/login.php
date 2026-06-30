<?php
$cssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/company-portal.css';
$cssVersion = is_file($cssPath) ? filemtime($cssPath) : time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal corporativo - HelpDesk Pronet System</title>
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth-app.css">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/company-portal.css?v=<?= $cssVersion ?>">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
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

                <form method="POST" action="/helpdesk-php/company-login.php" class="company-login-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($loginCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <label class="company-form-field" for="company_email">
                        <span>Correo corporativo</span>
                        <div>
                            <i class="fa-solid fa-envelope"></i>
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
                        <div>
                            <i class="fa-solid fa-key"></i>
                            <input
                                type="password"
                                id="company_password"
                                name="password"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="current-password"
                                required
                                <?= !$moduleReady ? 'disabled' : '' ?>
                            >
                        </div>
                    </label>

                    <button type="submit" class="company-primary-button" <?= !$moduleReady ? 'disabled' : '' ?>>
                        <span>Ingresar al portal</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <div class="company-login-divider"><span>otro tipo de acceso</span></div>

                <a href="/helpdesk-php/login.php" class="company-login-back">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Ingresar como usuario, técnico o administrador</span>
                </a>
            </div>
        </main>
    </section>
</div>
</body>
</html>
