<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

$currentUser = (array)user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

$redirectToTicket = static function (int $ticketId): void {
    $target = $ticketId > 0
        ? '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '&tab=internal#internalConversationTab'
        : '/helpdesk-php/home.php';

    header('Location: ' . $target);
    exit;
};

if (!in_array($currentRole, ['ADMIN', 'TECH'], true)) {
    $_SESSION['internal_message_error'] = 'No tienes permiso para enviar mensajes internos.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$rawMessage = trim((string)($_POST['message'] ?? ''));

if ($ticketId <= 0) {
    $_SESSION['internal_message_error'] = 'No se recibió un ticket válido.';
    $redirectToTicket($ticketId);
}

$ticketStatement = $pdo->prepare(
    'SELECT id, status
     FROM tickets
     WHERE id = :ticket_id
     LIMIT 1'
);
$ticketStatement->execute(['ticket_id' => $ticketId]);
$ticket = $ticketStatement->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['internal_message_error'] = 'No se encontró el ticket indicado.';
    $redirectToTicket($ticketId);
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    $_SESSION['internal_message_error'] = 'No se pueden agregar mensajes internos a un ticket cerrado.';
    $redirectToTicket($ticketId);
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
        'ticket_internal_messages',
        'message_format'
    );

    if ($hasFormatColumn) {
        $insertStatement = $pdo->prepare(
            'INSERT INTO ticket_internal_messages (
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
            'INSERT INTO ticket_internal_messages (
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
        'INTERNAL',
        $messageId,
        $currentUserId,
        $prepared['files'],
        $prepared['html']
    );

    if ($persisted['html'] !== $prepared['html']) {
        $updateStatement = $pdo->prepare(
            'UPDATE ticket_internal_messages
             SET message = :message,
                 updated_at = NOW()
             WHERE id = :message_id'
        );
        $updateStatement->execute([
            'message' => $persisted['html'],
            'message_id' => $messageId,
        ]);
    }

    $pdo->commit();

    $_SESSION['internal_message_success'] = 'Mensaje interno registrado correctamente.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (!empty($prepared)) {
        ticketCleanupPreparedFiles($prepared);
    }

    $_SESSION['internal_message_error'] = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'No se pudo guardar el mensaje interno.';
}

$redirectToTicket($ticketId);
