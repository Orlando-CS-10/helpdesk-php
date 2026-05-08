<?php

function createNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info', ?int $relatedTicketId = null): void
{
    $sql = "INSERT INTO notifications (
                user_id,
                title,
                message,
                type,
                is_read,
                related_ticket_id
            ) VALUES (
                :user_id,
                :title,
                :message,
                :type,
                0,
                :related_ticket_id
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'related_ticket_id' => $relatedTicketId
    ]);
}

function notifyAdmins(PDO $pdo, string $title, string $message, string $type = 'info', ?int $relatedTicketId = null): void
{
    $sql = "SELECT id
            FROM users
            WHERE role = 'ADMIN' AND status = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($admins as $admin) {
        createNotification(
            $pdo,
            (int)$admin['id'],
            $title,
            $message,
            $type,
            $relatedTicketId
        );
    }
}