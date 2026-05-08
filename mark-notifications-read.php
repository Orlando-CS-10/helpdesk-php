<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

$sql = "UPDATE notifications
        SET is_read = 1
        WHERE user_id = :user_id
          AND is_read = 0";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $currentUser['id']
]);

echo json_encode([
    'success' => true
]);