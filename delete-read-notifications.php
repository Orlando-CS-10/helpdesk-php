<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$user = user();
$userId = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $userId <= 0) {
    header('Location: /helpdesk-php/index.php');
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM notifications
    WHERE user_id = :user_id
      AND is_read = 1
");

$stmt->execute([
    'user_id' => $userId
]);

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/helpdesk-php/index.php'));
exit;