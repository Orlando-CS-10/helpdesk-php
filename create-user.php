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

function redirectUsers(): void
{
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

function tableColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$position = trim($_POST['position'] ?? '');
$company = trim($_POST['company'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
$techLevel = isset($_POST['tech_level']) ? (int) $_POST['tech_level'] : 1;

$allowedRoles = $currentRole === 'ADMIN'
    ? ['CLIENT', 'TECH', 'ADMIN']
    : ['CLIENT'];

if ($name === '' || $email === '' || !in_array($role, $allowedRoles, true)) {
    $_SESSION['user_error'] = $currentRole === 'TECH'
        ? 'Completa correctamente los campos. Un técnico solo puede crear usuarios clientes.'
        : 'Completa correctamente los campos obligatorios.';
    redirectUsers();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['user_error'] = 'El correo no es válido.';
    redirectUsers();
}

if (strlen($password) < 6) {
    $_SESSION['user_error'] = 'La contraseña debe tener al menos 6 caracteres.';
    redirectUsers();
}

if ($password !== $passwordConfirmation) {
    $_SESSION['user_error'] = 'Las contraseñas no coinciden.';
    redirectUsers();
}

if ($role === 'TECH' && !in_array($techLevel, [1, 2, 3], true)) {
    $_SESSION['user_error'] = 'Selecciona un nivel técnico válido.';
    redirectUsers();
}

$stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$stmtCheck->execute(['email' => $email]);

if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
    $_SESSION['user_error'] = 'Ya existe un usuario con ese correo.';
    redirectUsers();
}

$passwordColumn = null;
foreach (['password', 'password_hash'] as $candidate) {
    if (tableColumnExists($pdo, 'users', $candidate)) {
        $passwordColumn = $candidate;
        break;
    }
}

if ($passwordColumn === null) {
    $_SESSION['user_error'] = 'No se encontró una columna de contraseña en la tabla users.';
    redirectUsers();
}

$hasStatus = tableColumnExists($pdo, 'users', 'status');
$hasTechLevel = tableColumnExists($pdo, 'users', 'tech_level');
$hasCreatedAt = tableColumnExists($pdo, 'users', 'created_at');

$columns = ['name', 'email', 'role', $passwordColumn, 'phone', 'position', 'company'];
$placeholders = [':name', ':email', ':role', ':password', ':phone', ':position', ':company'];
$params = [
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'phone' => $phone !== '' ? $phone : null,
    'position' => $position !== '' ? $position : null,
    'company' => $company !== '' ? $company : null,
];

if ($hasStatus) {
    $columns[] = 'status';
    $placeholders[] = ':status';
    $params['status'] = 'ACTIVE';
}

if ($hasTechLevel) {
    $columns[] = 'tech_level';
    $placeholders[] = ':tech_level';
    $params['tech_level'] = $role === 'TECH' ? $techLevel : null;
}

if ($hasCreatedAt) {
    $columns[] = 'created_at';
    $placeholders[] = 'NOW()';
}

$sql = 'INSERT INTO users (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$_SESSION['user_success'] = 'Usuario creado correctamente.';
redirectUsers();
