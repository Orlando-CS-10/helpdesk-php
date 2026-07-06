<?php
/**
 * @var string $errorMessage
 * @var string $noticeMessage
 * @var string $email
 * @var string $forgotCsrfToken
 * @var string $authCssVersion
 */
$errorMessage = isset($errorMessage) ? (string) $errorMessage : '';
$noticeMessage = isset($noticeMessage) ? (string) $noticeMessage : '';
$email = isset($email) ? (string) $email : '';
$forgotCsrfToken = isset($forgotCsrfToken) ? (string) $forgotCsrfToken : '';
$authCssVersion = isset($authCssVersion) ? (string) $authCssVersion : (string) time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Recuperar contraseña - HelpDesk Pronet System</title>

    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth-app.css?v=<?= htmlspecialchars($authCssVersion, ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
</head>
<body>

<div class="login-wrapper login-published">
    <section class="login-shell">
        <aside class="login-brand-panel">
            <div class="login-brand-top">
                <div class="login-brand-mark"><i class="fa-solid fa-headset"></i></div>
                <div>
                    <strong>Mesa de Ayuda</strong>
                    <small>Recuperación segura de acceso</small>
                </div>
            </div>

            <div class="login-brand-content">
                <span class="login-mini-label">Seguridad</span>
                <h1>Recupera el acceso a tu cuenta.</h1>
                <p>
                    Te enviaremos un enlace de un solo uso al correo registrado
                    para que puedas crear una contraseña nueva.
                </p>
            </div>
        </aside>

        <main class="login-card">
            <div class="login-card-inner">
                <div class="login-card-header">
                    <div class="login-icon"><i class="fa-solid fa-unlock-keyhole"></i></div>
                    <div>
                        <h2>Recuperar contraseña</h2>
                        <p class="subtitle">El enlace vencerá después de 30 minutos.</p>
                    </div>
                </div>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert error login-alert" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($noticeMessage !== ''): ?>
                    <div class="alert success login-alert" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/helpdesk-php/forgot-password.php" class="login-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($forgotCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group login-input-group">
                        <label for="email">Correo registrado</label>
                        <div class="login-input-wrap">
                            <i class="fa-solid fa-envelope"></i>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="usuario@correo.com"
                                autocomplete="email"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn-primary login-submit">
                        <span>Enviar enlace de recuperación</span>
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>

                <p class="login-access-note">
                    <i class="fa-solid fa-shield-halved"></i>
                    Por seguridad, no confirmaremos si el correo pertenece a una cuenta.
                </p>

                <div class="login-footer-link">
                    <a href="/helpdesk-php/login.php" class="link-back">
                        <i class="fa-solid fa-arrow-left"></i>
                        Volver al login
                    </a>
                </div>
            </div>
        </main>
    </section>
</div>

</body>
</html>
