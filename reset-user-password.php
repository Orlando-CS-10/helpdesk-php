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

$sql = "SELECT id, name, email, role
        FROM users
        WHERE id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $userId]);
$userItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userItem) {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

require __DIR__ . '/app/views/admin/reset-user-password.php';