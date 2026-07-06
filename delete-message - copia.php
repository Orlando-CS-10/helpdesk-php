<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

function deleteMessageRedirect(int $ticketId, string $type, string $message): never
{
    $_SESSION[$type === 'success' ? 'ticket_success' : 'ticket_error'] = $message;

    $destination = $ticketId > 0
        ? '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab'
        : '/helpdesk-php/home.php';

    header('Location: ' . $destination);
    exit;
}

function canDeletePublicTicketMessage(array $messageData, array $currentUser): bool
{
    $currentRole = strtoupper((string)($currentUser['role'] ?? ''));
    $currentUserId = (int)($currentUser['id'] ?? 0);
    $messageOwnerId = (int)($messageData['user_id'] ?? 0);

    if ($currentRole === 'ADMIN') {
        return true;
    }

    return in_array($currentRole, ['TECH', 'CLIENT'], true)
        && $currentUserId > 0
        && $messageOwnerId === $currentUserId;
}

$messageId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['message_id'] ?? 0)
    : (int)($_GET['id'] ?? 0);

if ($messageId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$currentUser = (array)user();
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));
$currentUserId = (int)($currentUser['id'] ?? 0);

$messageStatement = $pdo->prepare(
    'SELECT
        tm.id,
        tm.user_id,
        tm.ticket_id,
        t.status AS ticket_status
     FROM ticket_messages tm
     INNER JOIN tickets t ON t.id = tm.ticket_id
     WHERE tm.id = :message_id
     LIMIT 1'
);
$messageStatement->execute(['message_id' => $messageId]);
$message = $messageStatement->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    deleteMessageRedirect(0, 'error', 'El mensaje no existe.');
}

$ticketId = (int)$message['ticket_id'];

if (!canDeletePublicTicketMessage($message, $currentUser)) {
    deleteMessageRedirect($ticketId, 'error', 'No tienes permiso para eliminar este mensaje.');
}

if (($message['ticket_status'] ?? '') === 'CERRADO') {
    deleteMessageRedirect($ticketId, 'error', 'No se puede eliminar un mensaje de un ticket cerrado.');
}

try {
    $pdo->beginTransaction();

    $attachmentPaths = ticketDeleteMessageAttachments($pdo, 'PUBLIC', $messageId);

    $deleteStatement = $pdo->prepare(
        'DELETE FROM ticket_messages
         WHERE id = :message_id'
    );
    $deleteStatement->execute(['message_id' => $messageId]);

    $ticketUpdateStatement = $pdo->prepare(
        'UPDATE tickets
         SET updated_at = NOW()
         WHERE id = :ticket_id'
    );
    $ticketUpdateStatement->execute(['ticket_id' => $ticketId]);

    createTicketActivity(
        $pdo,
        $ticketId,
        $currentUserId,
        (string)($currentUser['name'] ?? 'Usuario'),
        $currentRole,
        'MESSAGE_DELETED',
        'Se eliminó un mensaje de la conversación pública.'
    );

    $pdo->commit();

    ticketDeletePhysicalFiles($attachmentPaths ?? []);

    deleteMessageRedirect($ticketId, 'success', 'Mensaje eliminado correctamente.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[delete-message] Mensaje #' . $messageId . ': ' . $exception->getMessage());

    deleteMessageRedirect($ticketId, 'error', 'Error al eliminar el mensaje.');
}
