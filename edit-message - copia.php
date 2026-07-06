<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

function editMessageRedirect(int $ticketId, string $type, string $message): never
{
    $_SESSION[$type === 'success' ? 'ticket_success' : 'ticket_error'] = $message;

    $destination = $ticketId > 0
        ? '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab'
        : '/helpdesk-php/home.php';

    header('Location: ' . $destination);
    exit;
}

function canManagePublicTicketMessage(array $messageData, array $currentUser): bool
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

$currentUser = (array)user();
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));
$currentUserId = (int)($currentUser['id'] ?? 0);

$messageId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int)($_POST['message_id'] ?? 0)
    : (int)($_GET['id'] ?? 0);

if ($messageId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$messageStatement = $pdo->prepare(
    'SELECT
        tm.id,
        tm.ticket_id,
        tm.user_id,
        tm.message,
        tm.created_at,
        t.status AS ticket_status,
        u.name,
        u.role
     FROM ticket_messages tm
     INNER JOIN users u ON u.id = tm.user_id
     INNER JOIN tickets t ON t.id = tm.ticket_id
     WHERE tm.id = :message_id
     LIMIT 1'
);
$messageStatement->execute(['message_id' => $messageId]);
$messageData = $messageStatement->fetch(PDO::FETCH_ASSOC);

if (!$messageData) {
    editMessageRedirect(0, 'error', 'El mensaje no existe.');
}

$ticketId = (int)$messageData['ticket_id'];

if (!canManagePublicTicketMessage($messageData, $currentUser)) {
    editMessageRedirect($ticketId, 'error', 'No tienes permiso para editar este mensaje.');
}

if (($messageData['ticket_status'] ?? '') === 'CERRADO') {
    editMessageRedirect($ticketId, 'error', 'No se puede modificar un mensaje de un ticket cerrado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab');
    exit;
}

$newMessage = ticketSanitizeRichHtml((string)($_POST['message'] ?? ''));

if (!ticketMessageHasContent($newMessage)) {
    editMessageRedirect($ticketId, 'error', 'El mensaje no puede estar vacío.');
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

    editMessageRedirect($ticketId, 'success', 'Mensaje actualizado correctamente.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[edit-message] Mensaje #' . $messageId . ': ' . $exception->getMessage());

    editMessageRedirect($ticketId, 'error', 'Ocurrió un error al actualizar el mensaje.');
}
