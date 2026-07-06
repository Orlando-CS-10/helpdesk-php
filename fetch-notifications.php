<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$baseUrl = '/helpdesk-php';
$currentUser = function_exists('user') ? (array) user() : [];
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

function helpdeskJsonResponse(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function safeNotificationCurrentUrl(string $currentUrl, string $baseUrl): string
{
    $currentUrl = trim($currentUrl);

    if ($currentUrl === '') {
        return $baseUrl . '/index.php';
    }

    if (str_starts_with($currentUrl, $baseUrl . '/')) {
        return $currentUrl;
    }

    if (str_starts_with($currentUrl, 'ticket-detail.php')) {
        return $baseUrl . '/' . $currentUrl;
    }

    return $baseUrl . '/index.php';
}

function formatNotificationDateLabel(?string $createdAt): string
{
    $timestamp = strtotime((string)$createdAt);

    if ($timestamp === false) {
        return '';
    }

    return date('d/m/Y H:i', $timestamp);
}

try {
    if (!in_array($currentRole, ['ADMIN', 'TECH'], true) || $currentUserId <= 0) {
        helpdeskJsonResponse([
            'success' => true,
            'unread_count' => 0,
            'badge_text' => '',
            'notifications' => [],
        ]);
    }

    $notificationsHelper = __DIR__ . '/app/helpers/notifications.php';

    if (is_file($notificationsHelper)) {
        require_once $notificationsHelper;
    }

    if (function_exists('syncSlaNotificationsForUser')) {
        syncSlaNotificationsForUser($pdo, $currentUser);
    }

    $unreadCount = function_exists('getUnreadNotificationsCount')
        ? getUnreadNotificationsCount($pdo, $currentUserId)
        : 0;

    $items = function_exists('getUserNotifications')
        ? getUserNotifications($pdo, $currentUserId, 10)
        : [];

    $currentUrl = safeNotificationCurrentUrl((string)($_GET['current_url'] ?? ''), $baseUrl);
    $notifications = [];

    foreach ($items as $item) {
        $notificationId = (int)($item['id'] ?? 0);
        $relatedTicketId = isset($item['related_ticket_id']) ? (int)$item['related_ticket_id'] : 0;
        $redirectTo = $relatedTicketId > 0
            ? $baseUrl . '/ticket-detail.php?id=' . $relatedTicketId
            : $currentUrl;

        $notifications[] = [
            'id' => $notificationId,
            'title' => (string)($item['title'] ?? 'Notificación'),
            'message' => (string)($item['message'] ?? ''),
            'type' => (string)($item['type'] ?? 'info'),
            'is_read' => (int)($item['is_read'] ?? 0),
            'related_ticket_id' => $relatedTicketId,
            'created_at' => (string)($item['created_at'] ?? ''),
            'created_at_label' => formatNotificationDateLabel($item['created_at'] ?? null),
            'url' => $baseUrl
                . '/mark-notifications-read.php?id='
                . $notificationId
                . '&redirect='
                . rawurlencode($redirectTo),
        ];
    }

    helpdeskJsonResponse([
        'success' => true,
        'unread_count' => $unreadCount,
        'badge_text' => $unreadCount > 99 ? '99+' : (string)$unreadCount,
        'notifications' => $notifications,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);

    helpdeskJsonResponse([
        'success' => false,
        'message' => 'No se pudieron cargar las notificaciones en este momento.',
    ]);
}
