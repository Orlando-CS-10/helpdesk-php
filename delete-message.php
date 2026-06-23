<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
    $_SESSION['ticket_error'] = 'El mensaje no existe.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = (int)$message['ticket_id'];
$redirectUrl = '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab';

if (
    $currentRole === 'CLIENT'
    && (int)$message['user_id'] !== $currentUserId
) {
    $_SESSION['ticket_error'] = 'No tienes permiso para eliminar este mensaje.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (($message['ticket_status'] ?? '') === 'CERRADO') {
    $_SESSION['ticket_error'] = 'No se puede eliminar un mensaje de un ticket cerrado.';
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $pdo->beginTransaction();

    $attachmentPaths = ticketDeleteMessageAttachments($pdo, 'PUBLIC', $messageId);

    $deleteStatement = $pdo->prepare(
        'DELETE FROM ticket_messages
         WHERE id = :message_id'
    );
    $deleteStatement->execute(['message_id' => $messageId]);

    $pdo->commit();

    ticketDeletePhysicalFiles($attachmentPaths ?? []);

    $_SESSION['ticket_success'] = 'Mensaje eliminado correctamente.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['ticket_error'] = 'Error al eliminar el mensaje.';
}

header('Location: ' . $redirectUrl);
exit;
