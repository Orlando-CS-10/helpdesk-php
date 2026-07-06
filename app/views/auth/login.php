<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../helpers/session.php';

if (isLoggedIn()) {
    header('Location: /helpdesk-php/index.php');
    exit;
}

$authController = new AuthController($pdo);
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $result = $authController->login($email, $password);

    if (!empty($result['success'])) {
        header('Location: /helpdesk-php/index.php');
        exit;
    }

    $errorMessage = $result['message'] ?? 'No se pudo iniciar sesión.';
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
    <link rel="shortcut icon" href="/helpdesk-php/public/favicon/favicon.ico?v=2">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
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

                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert error login-alert">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="/helpdesk-php/login.php" class="login-form" autocomplete="off">
                        <div class="form-group login-input-group">
                            <label for="email">Correo</label>
                            <div class="login-input-wrap">
                                <i class="fa-solid fa-envelope"></i>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
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

                            <div class="login-input-wrap">
                                <i class="fa-solid fa-key"></i>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Ingresa tu contraseña"
                                    autocomplete="current-password"
                                    required>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary login-submit">
                            <span>Ingresar</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </main>
        </section>
    </div>

</body>

</html>