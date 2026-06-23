<?php
$search = $search ?? ($_GET['search'] ?? '');
$role = $role ?? ($_GET['role'] ?? '');
$status = $status ?? ($_GET['status'] ?? '');
$companyId = $companyId ?? ($_GET['company_id'] ?? '');
$users = $users ?? [];
$summary = $summary ?? ['total' => 0, 'clients' => 0, 'techs' => 0, 'admins' => 0, 'active' => 0];
$companyOptions = $companyOptions ?? [];
$companyModuleReady = $companyModuleReady ?? false;

$title = 'Gestión de Usuarios';

$activePage = 'users';
$pageTitle = 'Cuentas del sistema';
$pageSubtitle = 'Administra administradores, técnicos y contactos autorizados de empresas cliente.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-clients.php',
        'class' => 'btn-secondary',
        'text' => 'Clientes / Empresas'
    ]
];

function userPageSafe(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function userPageRoleLabel(string $role): string
{
    return match ($role) {
        'ADMIN' => 'Administrador',
        'TECH' => 'Técnico',
        'CLIENT' => 'Contacto cliente',
        default => $role,
    };
}

function userPageRoleClass(string $role): string
{
    return match ($role) {
        'ADMIN' => 'user-role-admin',
        'TECH' => 'user-role-tech',
        'CLIENT' => 'user-role-client',
        default => 'user-role-neutral',
    };
}

function userPageCompanyDisplay(array $userItem): string
{
    $tradeName = trim((string)($userItem['company_trade_name'] ?? ''));
    $businessName = trim((string)($userItem['company_business_name'] ?? ''));
    $legacyCompany = trim((string)($userItem['company'] ?? ''));

    if ($tradeName !== '') {
        return $tradeName;
    }

    if ($businessName !== '') {
        return $businessName;
    }

    return $legacyCompany !== '' ? $legacyCompany : '-';
}

function userPageInitials(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        return 'US';
    }

    $parts = explode(' ', $name);
    $first = mb_substr($parts[0] ?? 'U', 0, 1, 'UTF-8');
    $second = count($parts) > 1 ? mb_substr($parts[1], 0, 1, 'UTF-8') : mb_substr($parts[0], 1, 1, 'UTF-8');

    return mb_strtoupper($first . $second, 'UTF-8');
}

function userPageProfilePhotoUrl(array $userItem): string
{
    $photo = trim((string)($userItem['profile_photo'] ?? ''));

    if ($photo === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $photo)) {
        return $photo;
    }

    if (str_starts_with($photo, '/helpdesk-php/')) {
        return $photo;
    }

    if (str_starts_with($photo, '/')) {
        return '/helpdesk-php' . $photo;
    }

    return '/helpdesk-php/' . ltrim($photo, '/');
}

function userPageScopeLabel(array $userItem): array
{
    $role = (string)($userItem['role'] ?? '');

    if ($role === 'ADMIN') {
        return ['label' => 'Gestión global', 'class' => 'scope-global'];
    }

    if ($role === 'TECH') {
        return ['label' => 'Tickets asignados', 'class' => 'scope-tech'];
    }

    if ((int)($userItem['can_view_company_tickets'] ?? 0) === 1) {
        return ['label' => 'Toda la empresa', 'class' => 'scope-company'];
    }

    return ['label' => 'Solo propios', 'class' => 'scope-own'];
}

function userPageBuildUrl(array $changes = []): string
{
    $query = array_merge($_GET, $changes);

    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    $queryString = http_build_query($query);
    return '/helpdesk-php/admin-users.php' . ($queryString !== '' ? '?' . $queryString : '');
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-users-content">
            <?php if (!empty($_SESSION['user_success'])): ?>
                <section class="card admin-alert-card admin-alert-success">
                    <strong><?= userPageSafe($_SESSION['user_success']) ?></strong>
                </section>
                <?php unset($_SESSION['user_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['user_error'])): ?>
                <section class="card admin-alert-card admin-alert-error">
                    <strong><?= userPageSafe($_SESSION['user_error']) ?></strong>
                </section>
                <?php unset($_SESSION['user_error']); ?>
            <?php endif; ?>

            <?php if (empty($companyModuleReady)): ?>
                <section class="card admin-alert-card admin-alert-warning">
                    <h3>Módulo de empresas pendiente</h3>
                    <p>Cuando ejecutes la migración de empresas cliente, los contactos podrán vincularse a una organización y a su contrato SLA.</p>
                </section>
            <?php endif; ?>

            <section class="admin-user-summary-grid">
                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">Total cuentas</span>
                    <strong class="admin-kpi-value"><?= (int)$summary['total'] ?></strong>
                    <p>Usuarios con acceso al sistema.</p>
                </article>

                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">Contactos cliente</span>
                    <strong class="admin-kpi-value"><?= (int)$summary['clients'] ?></strong>
                    <p>Trabajadores vinculados a empresas cliente.</p>
                </article>

                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">Técnicos</span>
                    <strong class="admin-kpi-value"><?= (int)$summary['techs'] ?></strong>
                    <p>Equipo operativo de soporte Pronet.</p>
                </article>

                <article class="admin-kpi-card">
                    <span class="admin-kpi-label">Cuentas activas</span>
                    <strong class="admin-kpi-value"><?= (int)$summary['active'] ?></strong>
                    <p>Usuarios habilitados actualmente.</p>
                </article>
            </section>

            <section class="card admin-filters-card admin-user-filter-card">
                <div class="my-tickets-header">
                    <h2>Filtros</h2>
                    <p>Busca cuentas por nombre, correo, empresa, RUC, cargo o rol.</p>
                </div>

                <form action="/helpdesk-php/admin-users.php" method="GET" class="ticket-form">
                    <div class="admin-user-filter-grid">
                        <div class="form-group form-group-wide">
                            <label for="search">Buscar</label>
                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="<?= userPageSafe($search) ?>"
                                placeholder="Nombre, correo, empresa o RUC">
                        </div>

                        <div class="form-group">
                            <label for="role">Rol</label>
                            <select id="role" name="role">
                                <option value="">Todos</option>
                                <option value="CLIENT" <?= $role === 'CLIENT' ? 'selected' : '' ?>>Contactos cliente</option>
                                <option value="TECH" <?= $role === 'TECH' ? 'selected' : '' ?>>Técnicos</option>
                                <option value="ADMIN" <?= $role === 'ADMIN' ? 'selected' : '' ?>>Administradores</option>
                            </select>
                        </div>

                        <?php if (!empty($companyModuleReady)): ?>
                            <div class="form-group">
                                <label for="company_id">Empresa</label>
                                <select id="company_id" name="company_id">
                                    <option value="">Todas</option>
                                    <?php foreach ($companyOptions as $company): ?>
                                        <?php
                                        $companyName = trim((string)($company['trade_name'] ?? ''));
                                        if ($companyName === '') {
                                            $companyName = trim((string)($company['business_name'] ?? ''));
                                        }
                                        ?>
                                        <option value="<?= (int)$company['id'] ?>" <?= (string)$companyId === (string)$company['id'] ? 'selected' : '' ?>>
                                            <?= userPageSafe($companyName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Activos</option>
                                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactivos</option>
                            </select>
                        </div>
                    </div>

                    <div class="ticket-form-actions">
                        <a href="/helpdesk-php/admin-users.php" class="btn-secondary">Limpiar</a>
                        <button type="submit" class="btn-primary">Filtrar</button>
                    </div>
                </form>
            </section>

            <section class="card my-tickets-card admin-user-list-card">
                <div class="admin-user-list-header">
                    <div class="my-tickets-header">
                        <h2>Listado de cuentas</h2>
                        <p>Usuarios internos de Pronet y contactos autorizados de empresas cliente.</p>
                    </div>

                    <div class="admin-user-quick-filters">
                        <a href="<?= userPageBuildUrl(['role' => null]) ?>" class="<?= $role === '' ? 'active' : '' ?>">Todos</a>
                        <a href="<?= userPageBuildUrl(['role' => 'ADMIN']) ?>" class="<?= $role === 'ADMIN' ? 'active' : '' ?>">Admins</a>
                        <a href="<?= userPageBuildUrl(['role' => 'TECH']) ?>" class="<?= $role === 'TECH' ? 'active' : '' ?>">Técnicos</a>
                        <a href="<?= userPageBuildUrl(['role' => 'CLIENT']) ?>" class="<?= $role === 'CLIENT' ? 'active' : '' ?>">Contactos cliente</a>
                    </div>
                </div>

                <?php if (!empty($users)): ?>
                    <div class="tickets-table-wrapper admin-user-table-wrapper">
                        <table class="tickets-table admin-user-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Tipo de cuenta</th>
                                    <th>Contacto</th>
                                    <th>Empresa vinculada</th>
                                    <th>Alcance</th>
                                    <th>Estado</th>
                                    <th>Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $userItem): ?>
                                    <?php
                                    $userRole = (string)($userItem['role'] ?? '');
                                    $profilePhotoUrl = userPageProfilePhotoUrl($userItem);
                                    $scope = userPageScopeLabel($userItem);
                                    $companyDisplay = userPageCompanyDisplay($userItem);
                                    $isClient = $userRole === 'CLIENT';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="admin-user-cell">
                                                <div class="admin-user-avatar <?= userPageRoleClass($userRole) ?> <?= $profilePhotoUrl !== '' ? 'has-photo' : '' ?>">
                                                    <?php if ($profilePhotoUrl !== ''): ?>
                                                        <img
                                                            src="<?= userPageSafe($profilePhotoUrl) ?>"
                                                            alt="Foto de <?= userPageSafe($userItem['name'] ?? 'usuario') ?>"
                                                            loading="lazy">
                                                    <?php else: ?>
                                                        <?= userPageSafe(userPageInitials((string)($userItem['name'] ?? ''))) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong><?= userPageSafe($userItem['name'] ?? '-') ?></strong>
                                                    <span><?= userPageSafe($userItem['email'] ?? '-') ?></span>
                                                    <small>#<?= (int)$userItem['id'] ?></small>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="admin-user-role-badge <?= userPageRoleClass($userRole) ?>">
                                                <?= userPageSafe(userPageRoleLabel($userRole)) ?>
                                            </span>

                                            <?php if ($userRole === 'TECH' && !empty($userItem['tech_level'])): ?>
                                                <small class="admin-user-tech-level">Nivel <?= (int)$userItem['tech_level'] ?></small>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="admin-user-contact-cell">
                                                <strong><?= !empty($userItem['phone']) ? userPageSafe($userItem['phone']) : '-' ?></strong>
                                                <span><?= !empty($userItem['position']) ? userPageSafe($userItem['position']) : 'Sin cargo registrado' ?></span>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if ($isClient): ?>
                                                <div class="admin-user-company-cell">
                                                    <strong><?= userPageSafe($companyDisplay) ?></strong>
                                                    <?php if (!empty($userItem['company_ruc'])): ?>
                                                        <span>RUC: <?= userPageSafe($userItem['company_ruc']) ?></span>
                                                    <?php elseif ($companyDisplay === '-'): ?>
                                                        <span>Sin empresa vinculada</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="admin-user-muted">Cuenta interna Pronet</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="admin-user-scope <?= userPageSafe($scope['class']) ?>">
                                                <?= userPageSafe($scope['label']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ((int)($userItem['status'] ?? 0) === 1): ?>
                                                <span class="metric-pill success-pill">Activo</span>
                                            <?php else: ?>
                                                <span class="metric-pill danger-pill">Inactivo</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="admin-user-date">
                                                <?= !empty($userItem['created_at']) ? date('d/m/Y', strtotime($userItem['created_at'])) : '-' ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="admin-user-actions">
                                                <a href="/helpdesk-php/edit-user.php?id=<?= (int)$userItem['id'] ?>" class="ticket-link-btn">
                                                    Editar
                                                </a>

                                                <a href="/helpdesk-php/reset-user-password.php?id=<?= (int)$userItem['id'] ?>" class="btn-secondary">
                                                    Clave
                                                </a>

                                                <a
                                                    href="/helpdesk-php/toggle-user-status.php?id=<?= (int)$userItem['id'] ?>"
                                                    class="<?= (int)($userItem['status'] ?? 0) === 1 ? 'btn-secondary' : 'btn-primary' ?>">
                                                    <?= (int)($userItem['status'] ?? 0) === 1 ? 'Desactivar' : 'Activar' ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-ticket-box">
                        <h4>No se encontraron cuentas</h4>
                        <p>No hay usuarios con los filtros aplicados. Prueba limpiando la búsqueda.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
