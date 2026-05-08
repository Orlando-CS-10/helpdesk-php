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

$sql = "SELECT 
            tm.id,
            tm.ticket_id,
            tm.user_id,
            tm.message,
            tm.created_at,
            u.name,
            u.role
        FROM ticket_messages tm
        INNER JOIN users u ON u.id = tm.user_id
        WHERE tm.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $messageId]);
$messageData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$messageData) {
    $_SESSION['ticket_error'] = 'El mensaje no existe.';
    header('Location: home.php');
    exit;
}

// Permisos:
// CLIENT solo sus propios mensajes
// TECH y ADMIN pueden editar cualquiera
if ($currentRole === 'CLIENT' && (int)$messageData['user_id'] !== (int)$currentUser['id']) {
    $_SESSION['ticket_error'] = 'No tienes permiso para editar este mensaje.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . (int)$messageData['ticket_id']);
    exit;
}

require __DIR__ . '/app/views/tickets/edit-message.php';