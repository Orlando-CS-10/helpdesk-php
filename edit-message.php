<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';

$messageId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageId = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
} else {
    $messageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
}

if ($messageId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$sql = "SELECT 
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
        WHERE tm.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $messageId]);
$messageData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$messageData) {
    $_SESSION['ticket_error'] = 'El mensaje no existe.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = (int) $messageData['ticket_id'];
$redirectUrl = '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab';

// Si alguien entra por URL directa, ya no se muestra la pantalla completa de edición.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit;
}

// CLIENT solo puede editar sus propios mensajes. TECH y ADMIN pueden editar cualquiera.
if (
    $currentRole === 'CLIENT' &&
    (int) $messageData['user_id'] !== (int) ($currentUser['id'] ?? 0)
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

$newMessage = trim((string) ($_POST['message'] ?? ''));

if ($newMessage === '') {
    $_SESSION['ticket_error'] = 'El mensaje no puede estar vacío.';
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $sqlUpdate = "UPDATE ticket_messages
                  SET message = :message
                  WHERE id = :id";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        'message' => $newMessage,
        'id' => $messageId
    ]);

    $_SESSION['ticket_success'] = 'Mensaje actualizado correctamente.';
} catch (PDOException $e) {
    $_SESSION['ticket_error'] = 'Ocurrió un error al actualizar el mensaje.';
}

header('Location: ' . $redirectUrl);
exit;
