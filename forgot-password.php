<?php
require_once __DIR__ . '/app/config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Ingresa tu correo electrónico.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Mensaje genérico por seguridad
        $message = 'Si el correo está registrado, el administrador podrá ayudarte a restablecer tu contraseña.';
    }
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña</title>

    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/base.css?v=15">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth.css?v=15">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>

<div class="login-wrapper">
    <section class="login-shell forgot-shell">

        <aside class="login-brand-panel">
            <div class="login-brand-badge">
                <span>M</span>
                <div>
                    <strong>Mesa de Ayuda</strong>
                    <small>Recuperación de acceso</small>
                </div>
            </div>

            <div class="login-brand-content">
                <span class="login-mini-label">Seguridad</span>
                <h1>Recupera el acceso a tu cuenta.</h1>
                <p>
                    Ingresa tu correo registrado para solicitar apoyo con el restablecimiento de tu contraseña.
                </p>
            </div>
        </aside>

        <main class="login-card">
            <div class="login-card-header">
                <div class="login-icon">
                    <i class="fa-solid fa-unlock-keyhole"></i>
                </div>

                <div>
                    <h2>Recuperar contraseña</h2>
                    <p class="subtitle">Te ayudaremos a validar tu cuenta</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert error login-alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert success login-alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot-password.php" class="login-form">
                <div class="form-group login-input-group">
                    <label for="email">Correo registrado</label>

                    <div class="login-input-wrap">
                        <i class="fa-solid fa-envelope"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="usuario@correo.com"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="btn-primary login-submit">
                    <span>Solicitar recuperación</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="login-footer-link">
                <a href="/helpdesk-php/login.php" class="link-back">← Volver al login</a>
            </div>
        </main>

    </section>
</div>

</body>
</html>