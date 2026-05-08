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

$name = trim($_POST['name'] ?? '');

if ($name === '') {
    $_SESSION['settings_error'] = 'El nombre no puede estar vacío.';
    header('Location: settings.php');
    exit;
}

if (mb_strlen($name) < 3) {
    $_SESSION['settings_error'] = 'El nombre debe tener al menos 3 caracteres.';
    header('Location: settings.php');
    exit;
}

$sql = "UPDATE users
        SET name = :name
        WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'name' => $name,
    'id' => $currentUser['id']
]);

$_SESSION['user']['name'] = $name;
$_SESSION['settings_success'] = 'Tu nombre fue actualizado correctamente.';

header('Location: settings.php');
exit;