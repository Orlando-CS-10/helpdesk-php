<?php
require_once __DIR__ . '/../../helpers/system_security.php';

$userItem = $userItem ?? [];
$userId = (int) ($userItem['id'] ?? 0);
$userName = (string) ($userItem['name'] ?? '');
$userEmail = (string) ($userItem['email'] ?? '');
$userRole = (string) ($userItem['role'] ?? '');

if ($userId <= 0) {
    $_SESSION['user_error'] = 'No se encontró información del usuario seleccionado.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$securitySettings = isset($pdo) && $pdo instanceof PDO
    ? getSystemSecuritySettings($pdo)
    : systemSecurityDefaults();
$passwordMinimum = max(6, (int) ($securitySettings['min_password_length'] ?? 8));
$passwordRules = systemSecurityPasswordRulesText($securitySettings);
$passwordResetCsrf = systemSecurityCsrfToken('reset_user_password');

$title = 'Restablecer Contraseña';
$activePage = 'users';
$pageTitle = 'Restablecer Contraseña';
$pageSubtitle = 'Define una nueva contraseña para el usuario seleccionado.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-users.php',
        'class' => 'btn-secondary',
        'text' => 'Volver',
    ],
];

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content">
            <section class="card admin-filters-card">
                <div class="ticket-section-title">
                    <h3>Usuario seleccionado</h3>
                    <p>
                        <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                        · <?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?>
                        · <?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                <?php if (!empty($_SESSION['user_error'])): ?>
                    <div class="alert error">
                        <?= htmlspecialchars((string) $_SESSION['user_error'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php unset($_SESSION['user_error']); ?>
                <?php endif; ?>

                <form action="/helpdesk-php/update-user-password.php" method="POST" class="ticket-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($passwordResetCsrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= $userId ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Nueva contraseña</label>
                            <input type="password" id="password" name="password" minlength="<?= $passwordMinimum ?>" autocomplete="new-password" required>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm">Confirmar contraseña</label>
                            <input type="password" id="password_confirm" name="password_confirm" minlength="<?= $passwordMinimum ?>" autocomplete="new-password" required>
                        </div>
                    </div>

                    <div class="settings-setup-alert" style="margin: 14px 0;">
                        <span><i class="fa-solid fa-shield-halved"></i></span>
                        <div>
                            <strong>Política vigente</strong>
                            <p><?= htmlspecialchars($passwordRules, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>

                    <label class="tools-check" style="margin: 0 0 16px;">
                        <input type="checkbox" name="force_change_next_login" value="1" checked>
                        Solicitar al usuario que cambie esta contraseña en el próximo inicio de sesión.
                    </label>

                    <div class="ticket-form-actions">
                        <a href="/helpdesk-php/admin-users.php" class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-primary">Actualizar contraseña</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
