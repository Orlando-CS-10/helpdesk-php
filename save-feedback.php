<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: home.php');
    exit;
}

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'CLIENT') {
    header('Location: home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$resolved = trim($_POST['resolved'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if ($ticketId <= 0) {
    header('Location: home.php');
    exit;
}

if ($rating < 1 || $rating > 5) {
    $_SESSION['ticket_error'] = 'La calificación debe estar entre 1 y 5.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

if (!in_array($resolved, ['SI', 'NO'], true)) {
    $_SESSION['ticket_error'] = 'Debes indicar si el problema fue resuelto.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

// Verificar que el ticket exista, sea del cliente y esté cerrado
$sqlTicket = "SELECT id, requester_id, status
              FROM tickets
              WHERE id = :ticket_id
                AND requester_id = :requester_id
              LIMIT 1";

$stmtTicket = $pdo->prepare($sqlTicket);
$stmtTicket->execute([
    'ticket_id' => $ticketId,
    'requester_id' => $currentUser['id']
]);

$ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['ticket_error'] = 'No tienes permiso para evaluar este ticket.';
    header('Location: home.php');
    exit;
}

if (($ticket['status'] ?? '') !== 'CERRADO') {
    $_SESSION['ticket_error'] = 'Solo puedes calificar tickets cerrados.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

// Verificar que no exista feedback previo
$sqlCheck = "SELECT id
             FROM ticket_feedback
             WHERE ticket_id = :ticket_id
             LIMIT 1";

$stmtCheck = $pdo->prepare($sqlCheck);
$stmtCheck->execute([
    'ticket_id' => $ticketId
]);

$feedbackExists = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($feedbackExists) {
    $_SESSION['ticket_error'] = 'Ya registraste una evaluación para este ticket.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

try {
    $sqlInsert = "INSERT INTO ticket_feedback (
                    ticket_id,
                    user_id,
                    rating,
                    resolved,
                    comment
                  ) VALUES (
                    :ticket_id,
                    :user_id,
                    :rating,
                    :resolved,
                    :comment
                  )";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute([
        'ticket_id' => $ticketId,
        'user_id' => $currentUser['id'],
        'rating' => $rating,
        'resolved' => $resolved,
        'comment' => $comment !== '' ? $comment : null
    ]);

    // Notificar a administradores
    notifyAdmins(
        $pdo,
        'Nueva evaluación registrada',
        'El cliente registró una evaluación para el ticket #' . $ticketId . '.',
        'success',
        $ticketId
    );

    $_SESSION['ticket_success'] = 'Gracias por calificar la atención.';
} catch (PDOException $e) {
    $_SESSION['ticket_error'] = 'Ocurrió un error al guardar tu evaluación.';
}

header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
exit;