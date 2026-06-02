<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$userId = (int)($currentUser['id'] ?? 0);
$notificationId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$redirect = (string)($_GET['redirect'] ?? $_POST['redirect'] ?? '/helpdesk-php/index.php');

function safeNotificationRedirect(string $redirect): string
{
    if ($redirect === '') {
        return '/helpdesk-php/index.php';
    }

    $decoded = urldecode($redirect);

    if (str_starts_with($decoded, '/helpdesk-php/')) {
        return $decoded;
    }

    if (str_starts_with($decoded, 'ticket-detail.php')) {
        return '/helpdesk-php/' . $decoded;
    }

    return '/helpdesk-php/index.php';
}

if ($userId <= 0 || $notificationId <= 0) {
    header('Location: ' . safeNotificationRedirect($redirect));
    exit;
}

$stmt = $pdo->prepare("UPDATE notifications
                       SET is_read = 1
                       WHERE id = :id
                         AND user_id = :user_id");
$stmt->execute([
    'id' => $notificationId,
    'user_id' => $userId,
]);

header('Location: ' . safeNotificationRedirect($redirect));
exit;
