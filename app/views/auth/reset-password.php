<?php
/**
 * @var string $errorMessage
 * @var string $successMessage
 * @var string $rawToken
 * @var string $resetCsrfToken
 * @var string $passwordRules
 * @var array|null $tokenRecord
 * @var string $authCssVersion
 */
$errorMessage = isset($errorMessage) ? (string) $errorMessage : '';
$successMessage = isset($successMessage) ? (string) $successMessage : '';
$rawToken = isset($rawToken) ? (string) $rawToken : '';
$resetCsrfToken = isset($resetCsrfToken) ? (string) $resetCsrfToken : '';
$passwordRules = isset($passwordRules) ? (string) $passwordRules : '';
$tokenRecord = isset($tokenRecord) && is_array($tokenRecord) ? $tokenRecord : null;
$authCssVersion = isset($authCssVersion) ? (string) $authCssVersion : (string) time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Nueva contraseña - HelpDesk Pronet System</title>

    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth-app.css?v=<?= htmlspecialchars($authCssVersion, ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
</head>
<body>

<div class="login-wrapper login-published">
    <section class="login-shell">
        <aside class="login-brand-panel">
            <div class="login-brand-top">
                <div class="login-brand-mark"><i class="fa-solid fa-shield-halved"></i></div>
                <div>
                    <strong>Mesa de Ayuda</strong>
                    <small>Protección de la cuenta</small>
                </div>
            </div>

            <div class="login-brand-content">
                <span class="login-mini-label">Enlace seguro</span>
                <h1>Crea una contraseña nueva.</h1>
                <p>
                    El enlace solo puede utilizarse una vez. Al completar el cambio,
                    las sesiones anteriores podrán cerrarse automáticamente.
                </p>
            </div>
        </aside>

        <main class="login-card">
            <div class="login-card-inner">
                <div class="login-card-header">
                    <div class="login-icon"><i class="fa-solid fa-key"></i></div>
                    <div>
                        <h2>Nueva contraseña</h2>
                        <p class="subtitle">Usa una clave distinta y difícil de adivinar.</p>
                    </div>
                </div>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert error login-alert" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert success login-alert" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="login-footer-link">
                        <a href="/helpdesk-php/login.php" class="btn-primary login-submit">
                            <span>Ir al inicio de sesión</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                <?php elseif ($tokenRecord): ?>
                    <div class="alert info login-alert" role="note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span><?= htmlspecialchars($passwordRules, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <form method="POST" action="/helpdesk-php/reset-password.php" class="login-form" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resetCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group login-input-group">
                            <label for="new_password">Nueva contraseña</label>
                            <div class="login-input-wrap">
                                <i class="fa-solid fa-lock"></i>
                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    placeholder="Escribe una contraseña segura"
                                    autocomplete="new-password"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="form-group login-input-group">
                            <label for="confirm_password">Confirmar contraseña</label>
                            <div class="login-input-wrap">
                                <i class="fa-solid fa-lock"></i>
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Repite la contraseña"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn-primary login-submit">
                            <span>Guardar nueva contraseña</span>
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="login-footer-link">
                        <a href="/helpdesk-php/forgot-password.php" class="btn-primary login-submit">
                            <span>Solicitar un enlace nuevo</span>
                            <i class="fa-solid fa-paper-plane"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage === ''): ?>
                    <div class="login-footer-link">
                        <a href="/helpdesk-php/login.php" class="link-back">
                            <i class="fa-solid fa-arrow-left"></i>
                            Volver al login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </section>
</div>

</body>
</html>
