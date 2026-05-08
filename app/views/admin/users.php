<?php
$title = 'Gestión de Usuarios';

$activePage = 'users';
$pageTitle = 'Gestión de Usuarios';
$pageSubtitle = 'Administra clientes, técnicos y administradores del sistema.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/index.php',
        'class' => 'btn-secondary',
        'text' => 'Panel admin'
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
                <div class="my-tickets-header">
                    <h2>Filtros</h2>
                    <p>Busca usuarios por nombre, correo o rol.</p>
                </div>

                <form action="/helpdesk-php/admin-users.php" method="GET" class="ticket-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">Buscar</label>
                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Nombre o correo">
                        </div>

                        <div class="form-group">
                            <label for="role">Rol</label>
                            <select id="role" name="role">
                                <option value="">Todos</option>
                                <option value="CLIENT" <?= $role === 'CLIENT' ? 'selected' : '' ?>>Cliente</option>
                                <option value="TECH" <?= $role === 'TECH' ? 'selected' : '' ?>>Técnico</option>
                                <option value="ADMIN" <?= $role === 'ADMIN' ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>
                    </div>

                    <div class="ticket-form-actions">
                        <a href="/helpdesk-php/admin-users.php" class="btn-secondary">Limpiar</a>
                        <button type="submit" class="btn-primary">Filtrar</button>
                    </div>
                </form>
            </section>

            <section class="card my-tickets-card">
                <div class="my-tickets-header">
                    <h2>Listado de usuarios</h2>
                    <p>Consulta y administra los datos principales de cada usuario.</p>
                </div>

                <?php if (!empty($users)): ?>
                    <div class="tickets-table-wrapper">
                        <table class="tickets-table admin-users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Teléfono</th>
                                    <th>Cargo</th>
                                    <th>Empresa</th>
                                    <th>Estado</th>
                                    <th>Registro</th>
                                    <th>Editar</th>
                                    <th>Contraseña</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $userItem): ?>
                                    <tr>
                                        <td>#<?= (int)$userItem['id'] ?></td>
                                        <td><?= htmlspecialchars($userItem['name']) ?></td>
                                        <td><?= htmlspecialchars($userItem['email']) ?></td>
                                        <td>
                                            <span class="table-badge category-badge">
                                                <?= htmlspecialchars($userItem['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($userItem['phone']) ? htmlspecialchars($userItem['phone']) : '-' ?></td>
                                        <td><?= !empty($userItem['position']) ? htmlspecialchars($userItem['position']) : '-' ?></td>
                                        <td><?= !empty($userItem['company']) ? htmlspecialchars($userItem['company']) : '-' ?></td>
                                        <td>
                                            <?php if ((int)$userItem['status'] === 1): ?>
                                                <span class="metric-pill success-pill">Activo</span>
                                            <?php else: ?>
                                                <span class="metric-pill danger-pill">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= !empty($userItem['created_at']) ? date('d/m/Y', strtotime($userItem['created_at'])) : '-' ?></td>
                                        <td>
                                            <a href="/helpdesk-php/edit-user.php?id=<?= (int)$userItem['id'] ?>" class="ticket-link-btn">
                                                Editar
                                            </a>
                                        </td>

                                        <td>
                                            <a href="/helpdesk-php/reset-user-password.php?id=<?= (int)$userItem['id'] ?>" class="btn-secondary">
                                                Restablecer
                                            </a>
                                        </td>

                                        <td>
                                            <a
                                                href="/helpdesk-php/toggle-user-status.php?id=<?= (int)$userItem['id'] ?>"
                                                class="<?= (int)$userItem['status'] === 1 ? 'btn-secondary' : 'btn-primary' ?>">
                                                <?= (int)$userItem['status'] === 1 ? 'Desactivar' : 'Activar' ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-ticket-box">
                        <h4>No se encontraron usuarios</h4>
                        <p>No hay resultados con los filtros aplicados.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>