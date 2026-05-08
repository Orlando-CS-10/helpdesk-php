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
    <link rel="stylesheet" href="public/assets/css/base.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <h1>Mesa de Ayuda</h1>
        <p class="subtitle">Inicia sesión para continuar</p>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">
                <label for="email">Correo</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="cliente@demo.com"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="1234"
                    required
                >
            </div>

            <button type="submit" class="btn-primary">
                Ingresar
            </button>

        </form>

        <div class="demo-box">
            <p><strong>Cliente:</strong> cliente@demo.com / 1234</p>
            <p><strong>Técnico:</strong> tech@demo.com / 1234</p>
            <p><strong>Admin:</strong> admin@demo.com / 1234</p>
        </div>

        <div style="margin-top: 16px; text-align: center;">
            <a href="index.php" class="link-back">← Volver al inicio</a>
        </div>

    </div>
</div>

</body>
</html>