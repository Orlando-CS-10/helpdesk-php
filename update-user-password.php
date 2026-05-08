<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$password = trim($_POST['password'] ?? '');
$passwordConfirm = trim($_POST['password_confirm'] ?? '');

if ($id <= 0 || $password === '' || $passwordConfirm === '') {
    $_SESSION['user_error'] = 'Completa todos los campos.';
    header('Location: /helpdesk-php/reset-user-password.php?id=' . $id);
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['user_error'] = 'La contraseña debe tener al menos 6 caracteres.';
    header('Location: /helpdesk-php/reset-user-password.php?id=' . $id);
    exit;
}

if ($password !== $passwordConfirm) {
    $_SESSION['user_error'] = 'Las contraseñas no coinciden.';
    header('Location: /helpdesk-php/reset-user-password.php?id=' . $id);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users
        SET password = :password
        WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'password' => $passwordHash,
    'id' => $id
]);

header('Location: /helpdesk-php/admin-users.php');
exit;