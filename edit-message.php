<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

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
    $_SESSION['ticket_error'] = 'El mensaje no existe.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = (int)$messageData['ticket_id'];
$redirectUrl = '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab';

if (
    $currentRole === 'CLIENT'
    && (int)$messageData['user_id'] !== $currentUserId
) {
    $_SESSION['ticket_error'] = 'No tienes permiso para editar este mensaje.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (($messageData['ticket_status'] ?? '') === 'CERRADO') {
    $_SESSION['ticket_error'] = 'No se puede modificar un mensaje de un ticket cerrado.';
    header('Location: ' . $redirectUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit;
}

$newMessage = ticketSanitizeRichHtml((string)($_POST['message'] ?? ''));

if (!ticketMessageHasContent($newMessage)) {
    $_SESSION['ticket_error'] = 'El mensaje no puede estar vacío.';
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $hasFormatColumn = ticketColumnExists(
        $pdo,
        'ticket_messages',
        'message_format'
    );
    $hasUpdatedColumn = ticketColumnExists(
        $pdo,
        'ticket_messages',
        'updated_at'
    );

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

    $_SESSION['ticket_success'] = 'Mensaje actualizado correctamente.';
} catch (Throwable $exception) {
    $_SESSION['ticket_error'] = 'Ocurrió un error al actualizar el mensaje.';
}

header('Location: ' . $redirectUrl);
exit;
