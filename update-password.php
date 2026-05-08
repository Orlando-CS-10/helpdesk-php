<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if ($currentUser['role'] !== 'CLIENT') {
    header('Location: home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php');
    exit;
}

$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    $_SESSION['settings_error'] = 'Todos los campos de contraseña son obligatorios.';
    header('Location: settings.php');
    exit;
}

if ($newPassword !== $confirmPassword) {
    $_SESSION['settings_error'] = 'La nueva contraseña y su confirmación no coinciden.';
    header('Location: settings.php');
    exit;
}

if (strlen($newPassword) < 4) {
    $_SESSION['settings_error'] = 'La nueva contraseña debe tener al menos 4 caracteres.';
    header('Location: settings.php');
    exit;
}

$sql = "SELECT password
        FROM users
        WHERE id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $currentUser['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    $_SESSION['settings_error'] = 'No se pudo validar tu usuario.';
    header('Location: settings.php');
    exit;
}

if (!password_verify($currentPassword, $userData['password'])) {
    $_SESSION['settings_error'] = 'La contraseña actual no es correcta.';
    header('Location: settings.php');
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$sqlUpdate = "UPDATE users
              SET password = :password
              WHERE id = :id";

$stmtUpdate = $pdo->prepare($sqlUpdate);
$stmtUpdate->execute([
    'password' => $newHash,
    'id' => $currentUser['id']
]);

$_SESSION['settings_success'] = 'Tu contraseña fue actualizada correctamente.';

header('Location: settings.php');
exit;