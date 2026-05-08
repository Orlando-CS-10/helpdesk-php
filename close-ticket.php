<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';

requireLogin();

$ticketId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($ticketId <= 0) {
    header('Location: home.php');
    exit;
}

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'CLIENT') {
    $_SESSION['ticket_error'] = 'Solo el cliente puede cerrar su ticket.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

// Verificar que el ticket exista y pertenezca al cliente logueado
$sql = "SELECT 
            id,
            requester_id,
            status,
            sla_hours,
            created_at
        FROM tickets
        WHERE id = :ticket_id
          AND requester_id = :requester_id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'ticket_id' => $ticketId,
    'requester_id' => (int)$currentUser['id']
]);

$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['ticket_error'] = 'No tienes permiso para cerrar este ticket.';
    header('Location: home.php');
    exit;
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    $_SESSION['ticket_error'] = 'Este ticket ya está cerrado.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

try {
    $pdo->beginTransaction();

    $sqlUpdate = "UPDATE tickets
                  SET status = 'CERRADO',
                      client_closed = 1,
                      closed_at = CURRENT_TIMESTAMP,
                      sla_met = CASE
                                    WHEN TIMESTAMPDIFF(HOUR, created_at, CURRENT_TIMESTAMP) <= sla_hours THEN 1
                                    ELSE 0
                                END,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :ticket_id";

    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        'ticket_id' => $ticketId
    ]);

    // Registrar actividad
    createTicketActivity(
        $pdo,
        $ticketId,
        (int)$currentUser['id'],
        $currentUser['name'],
        $currentUser['role'],
        'CLOSED',
        'El cliente cerró el ticket.',
        $ticket['status'],
        'CERRADO'
    );

    // Notificación al cliente
    createNotification(
        $pdo,
        (int)$currentUser['id'],
        'Ticket cerrado',
        'Tu ticket #' . $ticketId . ' fue cerrado correctamente.',
        'success',
        $ticketId
    );

    // Notificación a administradores
    notifyAdmins(
        $pdo,
        'Ticket cerrado por el cliente',
        'El cliente cerró el ticket #' . $ticketId . '.',
        'warning',
        $ticketId
    );

    $pdo->commit();

    $_SESSION['ticket_success'] = 'El ticket fue cerrado correctamente. Ahora puedes calificar la atención.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['ticket_error'] = 'Ocurrió un error al cerrar el ticket.';
}

header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
exit;