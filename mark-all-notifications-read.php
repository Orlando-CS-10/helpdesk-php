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

function wantsNotificationJsonResponse(): bool
{
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest'
        || str_contains($accept, 'application/json')
        || (string)($_GET['ajax'] ?? $_POST['ajax'] ?? '') === '1';
}

function countUnreadNotifications(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $stmt->execute(['user_id' => $userId]);

    return (int)$stmt->fetchColumn();
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

if (wantsNotificationJsonResponse()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode([
        'success' => true,
        'unread_count' => $userId > 0 ? countUnreadNotifications($pdo, $userId) : 0,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Location: ' . safeAllNotificationsRedirect($redirect));
exit;
