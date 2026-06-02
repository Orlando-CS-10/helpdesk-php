<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$userId = (int)($currentUser['id'] ?? 0);
$redirect = (string)($_GET['redirect'] ?? $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/helpdesk-php/index.php'));

function safeAllNotificationsRedirect(string $redirect): string
{
    if ($redirect === '') {
        return '/helpdesk-php/index.php';
    }

    $decoded = urldecode($redirect);

    if (str_starts_with($decoded, '/helpdesk-php/')) {
        return $decoded;
    }

    return '/helpdesk-php/index.php';
}

if ($userId > 0) {
    $stmt = $pdo->prepare("UPDATE notifications
                           SET is_read = 1
                           WHERE user_id = :user_id
                             AND is_read = 0");
    $stmt->execute([
        'user_id' => $userId,
    ]);
}

header('Location: ' . safeAllNotificationsRedirect($redirect));
exit;
