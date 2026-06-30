<?php
$companyName = companyPortalDisplayName($account);
$logoUrl = companyPortalLogoUrl($account['logo_path'] ?? null);
$forceChange = !empty($account['force_password_change']);
$cssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/company-portal.css';
$cssVersion = is_file($cssPath) ? filemtime($cssPath) : time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña - Portal corporativo</title>
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/company-portal.css?v=<?= $cssVersion ?>">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
</head>
<body class="company-password-body">
<div class="company-password-shell">
    <header class="company-password-brand">
        <div class="company-password-logo <?= $logoUrl ? 'has-image' : '' ?>">
            <?php if ($logoUrl): ?>
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo de <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
                <i class="fa-solid fa-building-shield"></i>
            <?php endif; ?>
        </div>
        <div><small>Portal corporativo</small><strong><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></strong></div>
    </header>

    <main class="company-password-card">
        <div class="company-password-heading">
            <span><i class="fa-solid fa-key"></i></span>
            <div>
                <small><?= $forceChange ? 'Primer ingreso' : 'Seguridad de la cuenta' ?></small>
                <h1><?= $forceChange ? 'Crea una contraseña personal' : 'Cambiar contraseña' ?></h1>
                <p><?= $forceChange
                    ? 'La clave temporal debe reemplazarse antes de ingresar al panel corporativo.'
                    : 'Actualiza la contraseña de la cuenta corporativa.' ?></p>
            </div>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="company-alert is-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <div class="company-password-rules">
            <i class="fa-solid fa-shield-halved"></i>
            <span><?= htmlspecialchars($passwordRules, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <form method="POST" action="/helpdesk-php/company-change-password.php" class="company-password-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label class="company-form-field" for="current_password">
                <span>Contraseña actual</span>
                <div><i class="fa-solid fa-lock"></i><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
            </label>

            <label class="company-form-field" for="new_password">
                <span>Nueva contraseña</span>
                <div><i class="fa-solid fa-key"></i><input type="password" id="new_password" name="new_password" autocomplete="new-password" required></div>
            </label>

            <label class="company-form-field" for="confirm_password">
                <span>Confirmar nueva contraseña</span>
                <div><i class="fa-solid fa-check"></i><input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required></div>
            </label>

            <div class="company-password-actions">
                <?php if (!$forceChange): ?>
                    <a href="/helpdesk-php/company-dashboard.php" class="company-secondary-button">Volver al panel</a>
                <?php endif; ?>
                <button type="submit" class="company-primary-button">
                    <i class="fa-solid fa-floppy-disk"></i><span>Guardar contraseña</span>
                </button>
            </div>
        </form>

        <?php if ($forceChange): ?>
            <a href="/helpdesk-php/company-logout.php" class="company-password-logout">Cerrar sesión y hacerlo después</a>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
