<?php
require_once __DIR__ . '/app/helpers/session.php';
requireLogin();

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

$currentUser = user() ?? [];
$securitySettings = getSystemSecuritySettings($pdo);
$passwordRules = systemSecurityPasswordRulesText($securitySettings);
$csrfToken = systemSecurityCsrfToken('change_password');
$errorMessage = (string) ($_SESSION['password_change_error'] ?? '');
$successMessage = (string) ($_SESSION['password_change_success'] ?? '');
unset($_SESSION['password_change_error'], $_SESSION['password_change_success']);

$authCssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/auth-app.css';
$authCssVersion = file_exists($authCssPath) ? filemtime($authCssPath) : time();
$passwordCssPath = __DIR__ . '/public/assets/css/security-password.css';
$passwordCssVersion = file_exists($passwordCssPath) ? filemtime($passwordCssPath) : time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña - HelpDesk</title>
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth-app.css?v=<?= (int) $authCssVersion ?>">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/security-password.css?v=<?= (int) $passwordCssVersion ?>">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="security-password-page">
    <section class="security-password-card">
        <div class="security-password-icon"><i class="fa-solid fa-key"></i></div>
        <span class="security-password-eyebrow">Seguridad de la cuenta</span>
        <h1>Define una nueva contraseña</h1>
        <p>La política del sistema requiere una contraseña segura antes de continuar.</p>

        <div class="security-password-user">
            <i class="fa-solid fa-user-shield"></i>
            <span>
                <strong><?= htmlspecialchars((string) ($currentUser['name'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars((string) ($currentUser['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
            </span>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert error security-password-alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="alert success security-password-alert"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="/helpdesk-php/update-my-password.php" method="POST" class="security-password-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="current_password">Contraseña actual</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>

            <div class="form-group">
                <label for="new_password">Nueva contraseña</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="<?= (int) ($securitySettings['min_password_length'] ?? 8) ?>" required>
            </div>

            <div class="form-group">
                <label for="new_password_confirmation">Confirmar nueva contraseña</label>
                <input type="password" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password" minlength="<?= (int) ($securitySettings['min_password_length'] ?? 8) ?>" required>
            </div>

            <div class="security-password-rules">
                <i class="fa-solid fa-shield-halved"></i>
                <span><?= htmlspecialchars($passwordRules, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <button type="submit" class="btn-primary security-password-submit">
                <i class="fa-solid fa-check"></i>
                Guardar y continuar
            </button>
        </form>

        <a href="/helpdesk-php/logout.php" class="security-password-logout">Cerrar sesión</a>
    </section>
</div>
</body>
</html>
