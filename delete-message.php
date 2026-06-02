<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$messageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($messageId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';

$sql = "SELECT 
            tm.id, 
            tm.user_id, 
            tm.ticket_id,
            t.status AS ticket_status
        FROM ticket_messages tm
        INNER JOIN tickets t ON t.id = tm.ticket_id
        WHERE tm.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $messageId]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    $_SESSION['ticket_error'] = 'El mensaje no existe.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = (int) $message['ticket_id'];
$redirectUrl = '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab';

if (
    $currentRole === 'CLIENT' &&
    (int) $message['user_id'] !== (int) ($currentUser['id'] ?? 0)
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
    $sqlDelete = "DELETE FROM ticket_messages WHERE id = :id";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute(['id' => $messageId]);

    $_SESSION['ticket_success'] = 'Mensaje eliminado correctamente.';
} catch (PDOException $e) {
    $_SESSION['ticket_error'] = 'Error al eliminar el mensaje.';
}

header('Location: ' . $redirectUrl);
exit;
