<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

function updateMessageRedirect(int $ticketId, int $messageId, string $type, string $message): never
{
    $_SESSION[$type === 'success' ? 'ticket_success' : 'ticket_error'] = $message;

    if ($ticketId > 0) {
        header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab');
        exit;
    }

    if ($messageId > 0) {
        header('Location: /helpdesk-php/edit-message.php?id=' . $messageId);
        exit;
    }

    header('Location: /helpdesk-php/home.php');
    exit;
}

function canUpdatePublicTicketMessage(array $messageData, array $currentUser): bool
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$newMessage = ticketSanitizeRichHtml((string)($_POST['message'] ?? ''));

if ($messageId <= 0) {
    updateMessageRedirect($ticketId, $messageId, 'error', 'No se pudo identificar el mensaje.');
}

$messageStatement = $pdo->prepare(
    'SELECT
        tm.id,
        tm.ticket_id,
        tm.user_id,
        t.status AS ticket_status
     FROM ticket_messages tm
     INNER JOIN tickets t ON t.id = tm.ticket_id
     WHERE tm.id = :message_id
     LIMIT 1'
);
$messageStatement->execute(['message_id' => $messageId]);
$messageData = $messageStatement->fetch(PDO::FETCH_ASSOC);

if (!$messageData) {
    updateMessageRedirect($ticketId, $messageId, 'error', 'El mensaje no existe.');
}

$ticketId = (int)$messageData['ticket_id'];
$currentUser = (array)user();
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));
$currentUserId = (int)($currentUser['id'] ?? 0);

if (!canUpdatePublicTicketMessage($messageData, $currentUser)) {
    updateMessageRedirect($ticketId, $messageId, 'error', 'No tienes permiso para editar este mensaje.');
}

if (($messageData['ticket_status'] ?? '') === 'CERRADO') {
    updateMessageRedirect($ticketId, $messageId, 'error', 'No se puede modificar un mensaje de un ticket cerrado.');
}

if (!ticketMessageHasContent($newMessage)) {
    updateMessageRedirect($ticketId, $messageId, 'error', 'El mensaje no puede estar vacío.');
}

try {
    $pdo->beginTransaction();

    $hasFormatColumn = ticketColumnExists($pdo, 'ticket_messages', 'message_format');
    $hasUpdatedColumn = ticketColumnExists($pdo, 'ticket_messages', 'updated_at');

    $setParts = ['message = :message'];
    $params = [
        'message' => $newMessage,
        'message_id' => $messageId,
    ];

    if ($hasFormatColumn) {
        $setParts[] = 'message_format = :message_format';
        $params['message_format'] = 'html';
    }

    if ($hasUpdatedColumn) {
        $setParts[] = 'updated_at = NOW()';
    }

    $updateStatement = $pdo->prepare(
        'UPDATE ticket_messages
         SET ' . implode(', ', $setParts) . '
         WHERE id = :message_id'
    );
    $updateStatement->execute($params);

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
        'MESSAGE_EDITED',
        'Se editó un mensaje de la conversación pública.'
    );

    $pdo->commit();

    updateMessageRedirect($ticketId, $messageId, 'success', 'El mensaje fue actualizado correctamente.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[update-message] Mensaje #' . $messageId . ': ' . $exception->getMessage());

    updateMessageRedirect($ticketId, $messageId, 'error', 'Ocurrió un error al actualizar el mensaje.');
}
