<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';
$managerRoles = ['ADMIN', 'TECH'];

if (!in_array($currentRole, $managerRoles, true)) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$role = trim($_GET['role'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedRoles = ['CLIENT', 'TECH', 'ADMIN'];
$createAllowedRoles = $currentRole === 'ADMIN' ? ['CLIENT', 'TECH', 'ADMIN'] : ['CLIENT'];
$canCreateUsers = true;
$isTechManager = $currentRole === 'TECH';

$sql = "SELECT
            id,
            name,
            email,
            role,
            status,
            phone,
            position,
            company,
            created_at
        FROM users
        WHERE 1=1";

$params = [];

if ($role !== '' && in_array($role, $allowedRoles, true)) {
    $sql .= " AND role = :role";
    $params['role'] = $role;
}

if ($search !== '') {
    $sql .= " AND (name LIKE :search OR email LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
require __DIR__ . '/app/views/admin/users.php';
$pageContent = ob_get_clean();

if ($canCreateUsers) {
    $roleOptionsHtml = '';
    foreach ($createAllowedRoles as $optionRole) {
        $label = [
            'CLIENT' => 'Cliente',
            'TECH' => 'Técnico',
            'ADMIN' => 'Administrador',
        ][$optionRole] ?? $optionRole;

        $roleOptionsHtml .= '<option value="' . htmlspecialchars($optionRole, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    $fixedRoleNotice = $isTechManager
        ? '<p class="create-user-note">Como técnico, puedes crear únicamente usuarios clientes.</p>'
        : '<p class="create-user-note">Como administrador, puedes crear clientes, técnicos y administradores.</p>';

    $createUserWidget = '
<link rel="stylesheet" href="/helpdesk-php/public/assets/css/create-user-modal.css">

<div class="create-user-floating-action">
    <button type="button" class="create-user-open-btn" id="openCreateUserModal">
        <i class="fa-solid fa-user-plus"></i>
        Crear usuario
    </button>
</div>

<div class="create-user-modal-backdrop" id="createUserModal" aria-hidden="true">
    <div class="create-user-modal" role="dialog" aria-modal="true" aria-labelledby="createUserModalTitle">
        <div class="create-user-modal-header">
            <div>
                <span>Gestión de usuarios</span>
                <h3 id="createUserModalTitle">Crear nuevo usuario</h3>
            </div>
            <button type="button" class="create-user-close-btn" id="closeCreateUserModal" aria-label="Cerrar">&times;</button>
        </div>

        <form action="/helpdesk-php/create-user.php" method="POST" class="create-user-form" autocomplete="off">
            ' . $fixedRoleNotice . '

            <div class="create-user-grid">
                <div class="create-user-field">
                    <label for="create_user_name">Nombre completo</label>
                    <input type="text" id="create_user_name" name="name" required placeholder="Ej. Juan Pérez">
                </div>

                <div class="create-user-field">
                    <label for="create_user_email">Correo</label>
                    <input type="email" id="create_user_email" name="email" required placeholder="usuario@empresa.com">
                </div>

                <div class="create-user-field">
                    <label for="create_user_role">Rol</label>
                    <select id="create_user_role" name="role" required>
                        ' . $roleOptionsHtml . '
                    </select>
                </div>

                <div class="create-user-field create-user-tech-level" id="createUserTechLevelWrap">
                    <label for="create_user_tech_level">Nivel técnico</label>
                    <select id="create_user_tech_level" name="tech_level">
                        <option value="1">Nivel 1</option>
                        <option value="2">Nivel 2</option>
                        <option value="3">Nivel 3</option>
                    </select>
                </div>

                <div class="create-user-field">
                    <label for="create_user_phone">Teléfono</label>
                    <input type="text" id="create_user_phone" name="phone" placeholder="Opcional">
                </div>

                <div class="create-user-field">
                    <label for="create_user_position">Cargo</label>
                    <input type="text" id="create_user_position" name="position" placeholder="Opcional">
                </div>

                <div class="create-user-field create-user-full">
                    <label for="create_user_company">Empresa</label>
                    <input type="text" id="create_user_company" name="company" placeholder="Opcional">
                </div>

                <div class="create-user-field">
                    <label for="create_user_password">Contraseña</label>
                    <input type="password" id="create_user_password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>

                <div class="create-user-field">
                    <label for="create_user_password_confirmation">Confirmar contraseña</label>
                    <input type="password" id="create_user_password_confirmation" name="password_confirmation" required minlength="6" placeholder="Repite la contraseña">
                </div>
            </div>

            <div class="create-user-actions">
                <button type="button" class="create-user-cancel-btn" id="cancelCreateUserModal">Cancelar</button>
                <button type="submit" class="create-user-submit-btn">
                    <i class="fa-solid fa-check"></i>
                    Guardar usuario
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("createUserModal");
    const openBtn = document.getElementById("openCreateUserModal");
    const closeBtn = document.getElementById("closeCreateUserModal");
    const cancelBtn = document.getElementById("cancelCreateUserModal");
    const roleSelect = document.getElementById("create_user_role");
    const techLevelWrap = document.getElementById("createUserTechLevelWrap");

    function openModal() {
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("modal-open");
    }

    function closeModal() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
    }

    function syncTechLevel() {
        if (!roleSelect || !techLevelWrap) return;
        techLevelWrap.style.display = roleSelect.value === "TECH" ? "block" : "none";
    }

    if (openBtn) openBtn.addEventListener("click", openModal);
    if (closeBtn) closeBtn.addEventListener("click", closeModal);
    if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
    if (roleSelect) roleSelect.addEventListener("change", syncTechLevel);

    if (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) closeModal();
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("show")) closeModal();
    });

    syncTechLevel();
});
</script>
';

    if (stripos($pageContent, '</body>') !== false) {
        $pageContent = str_ireplace('</body>', $createUserWidget . '</body>', $pageContent);
    } else {
        $pageContent .= $createUserWidget;
    }
}

echo $pageContent;
