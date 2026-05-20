<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/controllers/AuthController.php';
require_once __DIR__ . '/app/helpers/session.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$authController = new AuthController($pdo);
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $result = $authController->login($email, $password);

    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $errorMessage = $result['message'];
    }
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mesa de Ayuda</title>

    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/base.css?v=14">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth.css?v=14">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>

<div class="login-wrapper">
    <div class="login-background-orb orb-one"></div>
    <div class="login-background-orb orb-two"></div>

    <section class="login-shell">
        <aside class="login-brand-panel">
            <div class="login-brand-badge">
                <div>
                    <strong></strong>
                    <small></small>
                </div>
            </div>

            <div class="login-brand-content">
                <span class="login-mini-label">Soporte TI</span>
                <h1>HelpDesk Pronet System</h1>
                <p>
                    Plataforma interna para registrar, asignar y dar seguimiento a solicitudes de soporte técnico.
                </p>
            </div>
        </aside>

        <main class="login-card">
            <div class="login-card-header">
                <div class="login-icon">
                    <i class="fa-solid fa-headset"></i>
                </div>

                <div>
                    <h2>Bienvenido</h2>
                    <p class="subtitle">Inicia sesión para continuar</p>
                </div>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert error login-alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">
                <div class="form-group login-input-group">
                    <label for="email">Correo</label>

                    <div class="login-input-wrap">
                        <i class="fa-solid fa-envelope"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="cliente@demo.com"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <div class="form-group login-input-group">
                    <div class="login-label-row">
                        <label for="password">Contraseña</label>
                    </div>

                    <div class="login-input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="1234"
                            autocomplete="current-password"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="btn-primary login-submit">
                    <span>Ingresar</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
                <a href="/helpdesk-php/forgot-password.php" class="forgot-link">Olvidé mi contraseña</a>
            </form>

            <div class="demo-box login-demo-box">
                <div class="demo-box-title">
                    <i class="fa-solid fa-key"></i>
                    <strong>Accesos demo</strong>
                </div>

                <div class="demo-credentials">
                    <div class="demo-credential-item">
                        <span>Cliente</span>
                        <strong>cliente@demo.com / 1234</strong>
                    </div>

                    <div class="demo-credential-item">
                        <span>Técnico</span>
                        <strong>tech@demo.com / 1234</strong>
                    </div>

                    <div class="demo-credential-item">
                        <span>Admin</span>
                        <strong>admin@demo.com / 1234</strong>
                    </div>
                </div>
            </div>
        </main>
    </section>
</div>

</body>
</html>
