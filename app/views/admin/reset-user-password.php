<?php
$userItem = $userItem ?? [];

$userId = (int)($userItem['id'] ?? 0);
$userName = (string)($userItem['name'] ?? '');
$userEmail = (string)($userItem['email'] ?? '');
$userRole = (string)($userItem['role'] ?? '');

if ($userId <= 0) {
    $_SESSION['user_error'] = 'No se encontró información del usuario seleccionado.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$title = 'Restablecer Contraseña';

$activePage = 'users';
$pageTitle = 'Restablecer Contraseña';
$pageSubtitle = 'Define una nueva contraseña para el usuario seleccionado.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-users.php',
        'class' => 'btn-secondary',
        'text' => 'Volver'
    ]
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
                        <?= htmlspecialchars($userName) ?>
                        · <?= htmlspecialchars($userEmail) ?>
                        · <?= htmlspecialchars($userRole) ?>
                    </p>
                </div>

                <?php if (!empty($_SESSION['user_error'])): ?>
                    <div class="alert error">
                        <?= htmlspecialchars($_SESSION['user_error']) ?>
                    </div>
                    <?php unset($_SESSION['user_error']); ?>
                <?php endif; ?>

                <form action="/helpdesk-php/update-user-password.php" method="POST" class="ticket-form">
                    <input type="hidden" name="id" value="<?= $userId ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Nueva contraseña</label>
                            <input type="password" id="password" name="password" required>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm">Confirmar contraseña</label>
                            <input type="password" id="password_confirm" name="password_confirm" required>
                        </div>
                    </div>

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
