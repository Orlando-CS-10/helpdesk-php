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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$position = trim($_POST['position'] ?? '');
$company = trim($_POST['company'] ?? '');

$allowedRoles = $currentRole === 'ADMIN'
    ? ['CLIENT', 'TECH', 'ADMIN']
    : ['CLIENT'];

function redirectUserEdit(int $id): void
{
    header('Location: /helpdesk-php/edit-user.php?id=' . $id);
    exit;
}

if ($id <= 0 || $name === '' || $email === '' || !in_array($role, $allowedRoles, true)) {
    $_SESSION['user_error'] = $currentRole === 'TECH'
        ? 'Completa correctamente los campos. Un técnico solo puede gestionar usuarios clientes.'
        : 'Completa correctamente los campos obligatorios.';
    redirectUserEdit($id);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['user_error'] = 'El correo no es válido.';
    redirectUserEdit($id);
}

// Verificar que el usuario exista y aplicar reglas de permisos por rol.
$stmtTarget = $pdo->prepare("SELECT id, role FROM users WHERE id = :id LIMIT 1");
$stmtTarget->execute(['id' => $id]);
$targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    $_SESSION['user_error'] = 'El usuario seleccionado no existe.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

if ($currentRole === 'TECH' && ($targetUser['role'] ?? '') !== 'CLIENT') {
    $_SESSION['user_error'] = 'No puedes editar usuarios administradores o técnicos.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

// Verificar correo duplicado en otro usuario.
$sqlCheck = "SELECT id
             FROM users
             WHERE email = :email
               AND id <> :id
             LIMIT 1";

$stmtCheck = $pdo->prepare($sqlCheck);
$stmtCheck->execute([
    'email' => $email,
    'id' => $id
]);

$exists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($exists) {
    $_SESSION['user_error'] = 'Ya existe otro usuario con ese correo.';
    redirectUserEdit($id);
}

$sql = "UPDATE users
        SET name = :name,
            email = :email,
            role = :role,
            phone = :phone,
            position = :position,
            company = :company
        WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'id' => $id,
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'phone' => $phone !== '' ? $phone : null,
    'position' => $position !== '' ? $position : null,
    'company' => $company !== '' ? $company : null
]);

$_SESSION['user_success'] = 'Usuario actualizado correctamente.';
header('Location: /helpdesk-php/admin-users.php');
exit;
