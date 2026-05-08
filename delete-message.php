<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$messageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($messageId <= 0) {
    header('Location: home.php');
    exit;
}

$currentUser = user();
$currentRole = $currentUser['role'];

// =============================
// OBTENER MENSAJE
// =============================
$sql = "SELECT tm.id, tm.user_id, tm.ticket_id
        FROM ticket_messages tm
        WHERE tm.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $messageId]);

$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    $_SESSION['ticket_error'] = 'El mensaje no existe.';
    header('Location: home.php');
    exit;
}

// =============================
// VALIDAR PERMISOS
// =============================
if (
    $currentRole === 'CLIENT' &&
    $message['user_id'] != $currentUser['id']
) {
    $_SESSION['ticket_error'] = 'No tienes permiso para eliminar este mensaje.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $message['ticket_id']);
    exit;
}

// =============================
// ELIMINAR MENSAJE
// =============================
try {
    $sqlDelete = "DELETE FROM ticket_messages WHERE id = :id";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute(['id' => $messageId]);

    $_SESSION['ticket_success'] = 'Mensaje eliminado correctamente.';
} catch (PDOException $e) {
    $_SESSION['ticket_error'] = 'Error al eliminar el mensaje.';
}

if ($ticket['status'] === 'CERRADO') {
    $_SESSION['ticket_error'] = 'No se puede modificar un ticket cerrado.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

header('Location: /helpdesk-php/ticket-detail.php?id=' . $message['ticket_id']);
exit;