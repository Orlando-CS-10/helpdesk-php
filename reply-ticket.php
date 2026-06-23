<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$rawMessage = trim((string)($_POST['message'] ?? ''));

if ($ticketId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$currentUser = (array)user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

if ($currentRole === 'CLIENT') {
    $ticketStatement = $pdo->prepare(
        'SELECT id, requester_id, assigned_to, status, first_response_at, sla_hours
         FROM tickets
         WHERE id = :ticket_id
           AND requester_id = :requester_id
         LIMIT 1'
    );
    $ticketStatement->execute([
        'ticket_id' => $ticketId,
        'requester_id' => $currentUserId,
    ]);
} else {
    $ticketStatement = $pdo->prepare(
        'SELECT id, requester_id, assigned_to, status, first_response_at, sla_hours
         FROM tickets
         WHERE id = :ticket_id
         LIMIT 1'
    );
    $ticketStatement->execute(['ticket_id' => $ticketId]);
}

$ticket = $ticketStatement->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['ticket_error'] = 'No tienes permiso para responder este ticket.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    $_SESSION['ticket_error'] = 'No se puede responder un ticket cerrado.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

$prepared = [];

try {
    $prepared = ticketPrepareRichMessage(
        $ticketId,
        $rawMessage,
        $_FILES['inline_images'] ?? null,
        $_FILES['attachments'] ?? null
    );

    $pdo->beginTransaction();

    $hasFormatColumn = ticketColumnExists(
        $pdo,
        'ticket_messages',
        'message_format'
    );

    if ($hasFormatColumn) {
        $insertStatement = $pdo->prepare(
            'INSERT INTO ticket_messages (
                ticket_id,
                user_id,
                message,
                message_format,
                created_at
             ) VALUES (
                :ticket_id,
                :user_id,
                :message,
                :message_format,
                NOW()
             )'
        );

        $insertStatement->execute([
            'ticket_id' => $ticketId,
            'user_id' => $currentUserId,
            'message' => $prepared['html'],
            'message_format' => 'html',
        ]);
    } else {
        $insertStatement = $pdo->prepare(
            'INSERT INTO ticket_messages (
                ticket_id,
                user_id,
                message,
                created_at
             ) VALUES (
                :ticket_id,
                :user_id,
                :message,
                NOW()
             )'
        );

        $insertStatement->execute([
            'ticket_id' => $ticketId,
            'user_id' => $currentUserId,
            'message' => $prepared['html'],
        ]);
    }

    $messageId = (int)$pdo->lastInsertId();

    $persisted = ticketPersistPreparedAttachments(
        $pdo,
        $ticketId,
        'PUBLIC',
        $messageId,
        $currentUserId,
        $prepared['files'],
        $prepared['html']
    );

    if ($persisted['html'] !== $prepared['html']) {
        $updateHtmlStatement = $pdo->prepare(
            'UPDATE ticket_messages
             SET message = :message
             WHERE id = :message_id'
        );
        $updateHtmlStatement->execute([
            'message' => $persisted['html'],
            'message_id' => $messageId,
        ]);
    }

    if (empty($ticket['first_response_at']) && $currentRole !== 'CLIENT') {
        $firstResponseStatement = $pdo->prepare(
            'UPDATE tickets
             SET first_response_at = NOW()
             WHERE id = :ticket_id
               AND first_response_at IS NULL'
        );
        $firstResponseStatement->execute(['ticket_id' => $ticketId]);
    }

    $newStatus = $currentRole === 'CLIENT'
        ? 'ABIERTO'
        : 'RESPONDIDO';

    $ticketUpdateStatement = $pdo->prepare(
        'UPDATE tickets
         SET status = :status,
             updated_at = NOW()
         WHERE id = :ticket_id'
    );
    $ticketUpdateStatement->execute([
        'status' => $newStatus,
        'ticket_id' => $ticketId,
    ]);

    $activityDescription = match ($currentRole) {
        'CLIENT' => 'El cliente respondió en el ticket.',
        'ADMIN' => 'El administrador respondió en el ticket.',
        default => 'El técnico respondió en el ticket.',
    };

    createTicketActivity(
        $pdo,
        $ticketId,
        $currentUserId,
        (string)($currentUser['name'] ?? 'Usuario'),
        $currentRole,
        'REPLIED',
        $activityDescription
    );

    if ($currentRole !== 'CLIENT') {
        createNotification(
            $pdo,
            (int)$ticket['requester_id'],
            'Nueva respuesta en tu ticket',
            'Tu ticket #' . $ticketId . ' recibió una nueva respuesta.',
            'info',
            $ticketId
        );
    } else {
        if (!empty($ticket['assigned_to'])) {
            createNotification(
                $pdo,
                (int)$ticket['assigned_to'],
                'Nueva respuesta del cliente',
                'El cliente respondió en el ticket #' . $ticketId . '.',
                'info',
                $ticketId
            );
        }

        notifyAdmins(
            $pdo,
            'Nueva respuesta del cliente',
            'El cliente respondió en el ticket #' . $ticketId . '.',
            'info',
            $ticketId
        );
    }

    $pdo->commit();

    $_SESSION['ticket_success'] = 'La respuesta fue enviada correctamente.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (!empty($prepared)) {
        ticketCleanupPreparedFiles($prepared);
    }

    $_SESSION['ticket_error'] = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Ocurrió un error al enviar la respuesta.';
}

header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab');
exit;
