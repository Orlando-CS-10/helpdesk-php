<?php

require_once __DIR__ . '/sla_helper.php';

if (!function_exists('createNotification')) {
    function createNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info', ?int $relatedTicketId = null): void
    {
        $allowedTypes = ['info', 'success', 'warning', 'error'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

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
}

if (!function_exists('notificationExistsRecently')) {
    function notificationExistsRecently(PDO $pdo, int $userId, string $title, ?int $relatedTicketId = null, int $hours = 8): bool
    {
        $sql = "SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :user_id
                  AND title = :title
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";

        $params = [
            'user_id' => $userId,
            'title' => $title,
            'hours' => $hours,
        ];

        if ($relatedTicketId === null) {
            $sql .= " AND related_ticket_id IS NULL";
        } else {
            $sql .= " AND related_ticket_id = :related_ticket_id";
            $params['related_ticket_id'] = $relatedTicketId;
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('createNotificationOnce')) {
    function createNotificationOnce(PDO $pdo, int $userId, string $title, string $message, string $type = 'info', ?int $relatedTicketId = null, int $hours = 8): void
    {
        if (notificationExistsRecently($pdo, $userId, $title, $relatedTicketId, $hours)) {
            return;
        }

        createNotification($pdo, $userId, $title, $message, $type, $relatedTicketId);
    }
}

if (!function_exists('notifyAdmins')) {
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
}

if (!function_exists('getSlaAlertTicketsForUser')) {
    function getSlaAlertTicketsForUser(PDO $pdo, array $currentUser): array
    {
        $role = strtoupper((string)($currentUser['role'] ?? ''));
        $userId = (int)($currentUser['id'] ?? 0);

        if (!in_array($role, ['ADMIN', 'TECH'], true) || $userId <= 0) {
            return [];
        }

        $sql = "SELECT
                    t.id,
                    t.subject,
                    t.status,
                    t.priority,
                    t.category,
                    t.assigned_to,
                    t.sla_hours,
                    t.sla_met,
                    t.created_at,
                    t.first_response_at,
                    t.closed_at,
                    u.name AS requester_name,
                    a.name AS assigned_name
                FROM tickets t
                INNER JOIN users u ON u.id = t.requester_id
                LEFT JOIN users a ON a.id = t.assigned_to
                WHERE t.status <> 'CERRADO'
                  AND t.created_at IS NOT NULL
                  AND t.sla_hours IS NOT NULL
                  AND t.sla_hours > 0";

        $params = [];

        if ($role === 'TECH') {
            $sql .= " AND t.assigned_to = :technician_id";
            $params['technician_id'] = $userId;
        }

        $sql .= " ORDER BY t.created_at ASC LIMIT 80";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alerts = [];

        foreach ($tickets as $ticket) {
            $label = getSlaStatusLabel($ticket);

            if (!in_array($label, ['Por vencer', 'Vencido'], true)) {
                continue;
            }

            $ticket['sla_alert_label'] = $label;
            $ticket['sla_alert_type'] = $label === 'Vencido' ? 'error' : 'warning';
            $alerts[] = $ticket;
        }

        return $alerts;
    }
}

if (!function_exists('syncSlaNotificationsForUser')) {
    function syncSlaNotificationsForUser(PDO $pdo, array $currentUser): void
    {
        $role = strtoupper((string)($currentUser['role'] ?? ''));
        $userId = (int)($currentUser['id'] ?? 0);

        if (!in_array($role, ['ADMIN', 'TECH'], true) || $userId <= 0) {
            return;
        }

        $alerts = getSlaAlertTicketsForUser($pdo, $currentUser);

        foreach ($alerts as $ticket) {
            $ticketId = (int)$ticket['id'];
            $priority = strtoupper((string)($ticket['priority'] ?? ''));
            $assignedName = $ticket['assigned_name'] ?: 'Sin asignar';
            $subject = trim((string)($ticket['subject'] ?? ''));
            $shortSubject = mb_strlen($subject) > 80 ? mb_substr($subject, 0, 77) . '...' : $subject;

            if (($ticket['sla_alert_label'] ?? '') === 'Vencido') {
                $title = 'SLA vencido';
                $type = 'error';
                $message = $role === 'TECH'
                    ? "Tu ticket #{$ticketId} superó el tiempo SLA establecido. Prioridad: {$priority}. Asunto: {$shortSubject}."
                    : "El ticket #{$ticketId} superó el tiempo SLA establecido. Prioridad: {$priority}. Técnico: {$assignedName}. Asunto: {$shortSubject}.";
            } else {
                $title = 'SLA por vencer';
                $type = 'warning';
                $message = $role === 'TECH'
                    ? "Tu ticket #{$ticketId} está próximo a vencer su SLA. Prioridad: {$priority}. Asunto: {$shortSubject}."
                    : "El ticket #{$ticketId} está próximo a vencer su SLA. Prioridad: {$priority}. Técnico: {$assignedName}. Asunto: {$shortSubject}.";
            }

            createNotificationOnce($pdo, $userId, $title, $message, $type, $ticketId, 8);
        }
    }
}

if (!function_exists('getUnreadNotificationsCount')) {
    function getUnreadNotificationsCount(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute(['user_id' => $userId]);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('getUserNotifications')) {
    function getUserNotifications(PDO $pdo, int $userId, int $limit = 8): array
    {
        $stmt = $pdo->prepare("SELECT
                                    id,
                                    title,
                                    message,
                                    type,
                                    is_read,
                                    related_ticket_id,
                                    created_at
                                FROM notifications
                                WHERE user_id = :user_id
                                ORDER BY is_read ASC, created_at DESC
                                LIMIT :limit");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
