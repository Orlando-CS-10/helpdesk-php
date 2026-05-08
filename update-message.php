<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: home.php');
    exit;
}

$messageId = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
$ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
$message = trim($_POST['message'] ?? '');

if ($messageId <= 0 || $ticketId <= 0) {
    header('Location: home.php');
    exit;
}

if ($message === '') {
    $_SESSION['ticket_error'] = 'El mensaje no puede estar vacío.';
    header('Location: /helpdesk-php/edit-message.php?id=' . $messageId);
    exit;
}

$currentUser = user();
$currentRole = $currentUser['role'];

$sql = "SELECT id, ticket_id, user_id
        FROM ticket_messages
        WHERE id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $messageId]);
$messageData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$messageData) {
    $_SESSION['ticket_error'] = 'El mensaje no existe.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

if ($currentRole === 'CLIENT' && (int)$messageData['user_id'] !== (int)$currentUser['id']) {
    $_SESSION['ticket_error'] = 'No tienes permiso para editar este mensaje.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

if ($ticket['status'] === 'CERRADO') {
    $_SESSION['ticket_error'] = 'No se puede modificar un ticket cerrado.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

try {
    $sqlUpdate = "UPDATE ticket_messages
                  SET message = :message
                  WHERE id = :id";

    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        'message' => $message,
        'id' => $messageId
    ]);

    $sqlTicketUpdate = "UPDATE tickets
                        SET updated_at = CURRENT_TIMESTAMP
                        WHERE id = :ticket_id";

    $stmtTicketUpdate = $pdo->prepare($sqlTicketUpdate);
    $stmtTicketUpdate->execute([
        'ticket_id' => $ticketId
    ]);

    $_SESSION['ticket_success'] = 'El mensaje fue actualizado correctamente.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;

} catch (PDOException $e) {
    $_SESSION['ticket_error'] = 'Ocurrió un error al actualizar el mensaje.';
    header('Location: /helpdesk-php/edit-message.php?id=' . $messageId);
    exit;
}