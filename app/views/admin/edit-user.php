<?php
$title = 'Editar Usuario';

$activePage = 'users';
$pageTitle = 'Editar Usuario';
$pageSubtitle = 'Actualiza los datos principales del usuario seleccionado.';

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

                <?php if (!empty($_SESSION['user_error'])): ?>
                    <div class="alert error">
                        <?= htmlspecialchars($_SESSION['user_error']) ?>
                    </div>
                    <?php unset($_SESSION['user_error']); ?>
                <?php endif; ?>

                <form action="/helpdesk-php/update-user.php" method="POST" class="ticket-form">
                    <input type="hidden" name="id" value="<?= (int)$userItem['id'] ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nombre</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($userItem['name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Correo</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($userItem['email']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Rol</label>
                            <select id="role" name="role" required>
                                <option value="CLIENT" <?= $userItem['role'] === 'CLIENT' ? 'selected' : '' ?>>Cliente</option>
                                <option value="TECH" <?= $userItem['role'] === 'TECH' ? 'selected' : '' ?>>Técnico</option>
                                <option value="ADMIN" <?= $userItem['role'] === 'ADMIN' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="phone">Teléfono</label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($userItem['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="position">Cargo</label>
                            <input type="text" id="position" name="position" value="<?= htmlspecialchars($userItem['position'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="company">Empresa</label>
                            <input type="text" id="company" name="company" value="<?= htmlspecialchars($userItem['company'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="ticket-form-actions">
                        <a href="/helpdesk-php/admin-users.php" class="btn-secondary">Cancelar</a>
                        <button type="submit" class="btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>