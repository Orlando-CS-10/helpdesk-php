<?php
require_once __DIR__ . '/../../helpers/session.php';

requireLogin();

$userData = $userData ?? user();
$userData['name'] = $userData['name'] ?? '';
$userData['email'] = $userData['email'] ?? '';
$userData['role'] = $userData['role'] ?? '';

$title = 'Ajustes';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="panel">
    <div class="topbar">
        <h1>Ajustes de la cuenta</h1>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="/helpdesk-php/home.php" class="btn-secondary">Ir al inicio</a>
            <a href="/helpdesk-php/logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="settings-layout">
        <div class="card settings-card">
            <div class="settings-card-header">
                <h2>Información del usuario</h2>
                <p>Consulta los datos principales de tu cuenta.</p>
            </div>

            <div class="settings-info-grid">
                <div class="settings-info-item">
                    <span class="label">Nombre</span>
                    <strong><?= htmlspecialchars($userData['name']) ?></strong>
                </div>

                <div class="settings-info-item">
                    <span class="label">Correo</span>
                    <strong><?= htmlspecialchars($userData['email']) ?></strong>
                </div>

                <div class="settings-info-item">
                    <span class="label">Rol</span>
                    <strong><?= htmlspecialchars($userData['role']) ?></strong>
                </div>
            </div>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header">
                <h2>Actualizar nombre</h2>
                <p>Puedes cambiar el nombre que se mostrará en tu cuenta.</p>
            </div>

            <?php if (!empty($_SESSION['settings_success'])): ?>
                <div class="alert success">
                    <?= htmlspecialchars($_SESSION['settings_success']) ?>
                </div>
                <?php unset($_SESSION['settings_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['settings_error'])): ?>
                <div class="alert error">
                    <?= htmlspecialchars($_SESSION['settings_error']) ?>
                </div>
                <?php unset($_SESSION['settings_error']); ?>
            <?php endif; ?>

            <form action="/helpdesk-php/update-settings.php" method="POST" class="ticket-form">
                <div class="form-group">
                    <label for="name">Nuevo nombre</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($userData['name']) ?>"
                        required
                    >
                </div>

                <div class="ticket-form-actions">
                    <button type="submit" class="btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>

        <div class="card settings-card">
            <div class="settings-card-header">
                <h2>Cambiar contraseña</h2>
                <p>Actualiza tu contraseña para mantener segura tu cuenta.</p>
            </div>

            <form action="/helpdesk-php/update-password.php" method="POST" class="ticket-form">
                <div class="form-group">
                    <label for="current_password">Contraseña actual</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="new_password">Nueva contraseña</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar nueva contraseña</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                    >
                </div>

                <div class="ticket-form-actions">
                    <button type="submit" class="btn-primary">Actualizar contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>