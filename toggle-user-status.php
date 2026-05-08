<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($userId <= 0) {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

// Evitar que el admin se desactive a sí mismo
if ($userId === (int)$currentUser['id']) {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$sqlGet = "SELECT status FROM users WHERE id = :id LIMIT 1";
$stmtGet = $pdo->prepare($sqlGet);
$stmtGet->execute(['id' => $userId]);
$userItem = $stmtGet->fetch(PDO::FETCH_ASSOC);

if (!$userItem) {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$newStatus = ((int)$userItem['status'] === 1) ? 0 : 1;

$sqlUpdate = "UPDATE users SET status = :status WHERE id = :id";
$stmtUpdate = $pdo->prepare($sqlUpdate);
$stmtUpdate->execute([
    'status' => $newStatus,
    'id' => $userId
]);

header('Location: /helpdesk-php/admin-users.php');
exit;