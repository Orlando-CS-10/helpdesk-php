<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../helpers/session.php';

// Si ya está logueado, lo mandamos al sistema
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$authController = new AuthController($pdo);
$errorMessage = '';

// Procesar login
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
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/base.css?v=10">
</head>
<body>

<div class="login-wrapper">
    <div class="login-background-orb orb-one"></div>
    <div class="login-background-orb orb-two"></div>

    <section class="login-shell">
        <aside class="login-brand-panel">
            <div class="login-brand-badge">
                <span>M</span>
                <div>
                    <strong>Mesa de Ayuda</strong>
                    <small>Gestión inteligente de incidencias</small>
                </div>
            </div>

            <div class="login-brand-content">
                <span class="login-mini-label">Soporte TI</span>
                <h1>Controla tickets, SLA y tiempos de atención en un solo lugar.</h1>
                <p>
                    Accede al sistema para registrar incidencias, asignar técnicos, monitorear estados y revisar indicadores operativos.
                </p>
            </div>

            <div class="login-feature-grid">
                <div class="login-feature-card">
                    <i class="fa-solid fa-ticket"></i>
                    <span>Tickets</span>
                </div>
                <div class="login-feature-card">
                    <i class="fa-solid fa-clock"></i>
                    <span>TTA / TTR</span>
                </div>
                <div class="login-feature-card">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>SLA</span>
                </div>
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
                        <a href="/helpdesk-php/forgot-password.php" class="forgot-link">Recuperar contraseña</a>
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

            <div class="login-footer-link">
                <a href="index.php" class="link-back">← Volver al inicio</a>
            </div>
        </main>
    </section>
</div>

</body>
</html>
