<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if ($currentUser['role'] !== 'CLIENT') {
    header('Location: home.php');
    exit;
}

$userId = (int) $currentUser['id'];

$sql = "SELECT id, name, email, role
        FROM users
        WHERE id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    header('Location: home.php');
    exit;
}

require __DIR__ . '/app/views/client/settings.php';