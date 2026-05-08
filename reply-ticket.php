<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
$message = trim($_POST['message'] ?? '');

if ($ticketId <= 0) {
    header('Location: home.php');
    exit;
}

if ($message === '') {
    $_SESSION['ticket_error'] = 'El mensaje no puede estar vacío.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';

// =============================
// VALIDAR ACCESO AL TICKET
// =============================
if ($currentRole === 'CLIENT') {
    $sqlTicket = "SELECT 
                    id,
                    requester_id,
                    status,
                    first_response_at,
                    sla_hours
                  FROM tickets
                  WHERE id = :ticket_id
                    AND requester_id = :requester_id
                  LIMIT 1";

    $stmtTicket = $pdo->prepare($sqlTicket);
    $stmtTicket->execute([
        'ticket_id' => $ticketId,
        'requester_id' => (int)$currentUser['id']
    ]);
} else {
    $sqlTicket = "SELECT 
                    id,
                    requester_id,
                    status,
                    first_response_at,
                    sla_hours
                  FROM tickets
                  WHERE id = :ticket_id
                  LIMIT 1";

    $stmtTicket = $pdo->prepare($sqlTicket);
    $stmtTicket->execute([
        'ticket_id' => $ticketId
    ]);
}

$ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['ticket_error'] = 'No tienes permiso para responder este ticket.';
    header('Location: home.php');
    exit;
}

// =============================
// BLOQUEAR SI EL TICKET ESTÁ CERRADO
// =============================
if (($ticket['status'] ?? '') === 'CERRADO') {
    $_SESSION['ticket_error'] = 'No se puede responder un ticket cerrado.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

try {
    $pdo->beginTransaction();

    // =============================
    // GUARDAR MENSAJE
    // =============================
    $sqlInsert = "INSERT INTO ticket_messages (
                    ticket_id,
                    user_id,
                    message
                  ) VALUES (
                    :ticket_id,
                    :user_id,
                    :message
                  )";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute([
        'ticket_id' => $ticketId,
        'user_id' => (int)$currentUser['id'],
        'message' => $message
    ]);

    // =============================
    // GUARDAR PRIMERA RESPUESTA
    // =============================
    if (empty($ticket['first_response_at'])) {
        $sqlFirstResponse = "UPDATE tickets
                             SET first_response_at = CURRENT_TIMESTAMP
                             WHERE id = :ticket_id
                               AND first_response_at IS NULL";

        $stmtFirstResponse = $pdo->prepare($sqlFirstResponse);
        $stmtFirstResponse->execute([
            'ticket_id' => $ticketId
        ]);
    }

    // =============================
    // ACTUALIZAR ESTADO
    // =============================
    if ($currentRole === 'CLIENT') {
        $newStatus = 'ABIERTO';
    } else {
        $newStatus = 'RESPONDIDO';
    }

    $sqlUpdate = "UPDATE tickets
                  SET status = :status,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = :ticket_id";

    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        'status' => $newStatus,
        'ticket_id' => $ticketId
    ]);

    // =============================
    // REGISTRAR ACTIVIDAD
    // =============================
    $actorRoleText = $currentRole === 'CLIENT'
        ? 'El cliente respondió en el ticket.'
        : ($currentRole === 'ADMIN'
            ? 'El administrador respondió en el ticket.'
            : 'El técnico respondió en el ticket.');

    createTicketActivity(
        $pdo,
        $ticketId,
        (int)$currentUser['id'],
        $currentUser['name'],
        $currentUser['role'],
        'REPLIED',
        $actorRoleText
    );

    // =============================
    // NOTIFICACIONES
    // =============================

    // Si responde TECH o ADMIN, notificar al cliente
    if ($currentRole !== 'CLIENT') {
        createNotification(
            $pdo,
            (int)$ticket['requester_id'],
            'Nueva respuesta en tu ticket',
            'Tu ticket #' . $ticketId . ' recibió una nueva respuesta.',
            'info',
            $ticketId
        );
    }

    // Si responde CLIENTE, notificar a admins
    if ($currentRole === 'CLIENT') {
        notifyAdmins(
            $pdo,
            'Nueva respuesta del cliente',
            'El cliente respondió en el ticket #' . $ticketId . '.',
            'info',
            $ticketId
        );
    }

    $pdo->commit();

    $_SESSION['ticket_success'] = 'Tu respuesta fue enviada correctamente.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['ticket_error'] = 'Ocurrió un error al enviar la respuesta.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}