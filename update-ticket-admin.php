<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';

requireLogin();

$currentUser = user();

if ($currentUser['role'] !== 'ADMIN') {
    header('Location: home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin-tickets.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
$status = trim($_POST['status'] ?? '');
$assignedToRaw = trim($_POST['assigned_to'] ?? '');

$allowedStatus = ['ABIERTO', 'EN_PROCESO', 'RESPONDIDO', 'CERRADO'];

if ($ticketId <= 0) {
    $_SESSION['ticket_error'] = 'Ticket inválido.';
    header('Location: /helpdesk-php/admin-tickets.php');
    exit;
}

if (!in_array($status, $allowedStatus, true)) {
    $_SESSION['ticket_error'] = 'Estado no válido.';
    header('Location: /helpdesk-php/admin-tickets.php');
    exit;
}

$assignedTo = null;
$newTechLevel = null;

if ($assignedToRaw !== '') {
    $assignedTo = (int)$assignedToRaw;

    $sqlTech = "SELECT id, tech_level
                FROM users
                WHERE id = :id
                  AND role = 'TECH'
                  AND status = 1
                LIMIT 1";

    $stmtTech = $pdo->prepare($sqlTech);
    $stmtTech->execute(['id' => $assignedTo]);
    $techExists = $stmtTech->fetch(PDO::FETCH_ASSOC);

    if (!$techExists) {
        $_SESSION['ticket_error'] = 'El técnico seleccionado no es válido.';
        header('Location: /helpdesk-php/admin-tickets.php');
        exit;
    }

    $newTechLevel = !empty($techExists['tech_level']) ? (int)$techExists['tech_level'] : 1;
}

$sqlTicket = "SELECT 
                  id, 
                  status, 
                  assigned_to,
                  support_level,
                  level_started_at,
                  level_first_response_at,
                  created_at
              FROM tickets
              WHERE id = :ticket_id
              LIMIT 1";

$stmtTicket = $pdo->prepare($sqlTicket);
$stmtTicket->execute(['ticket_id' => $ticketId]);
$ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['ticket_error'] = 'El ticket no existe.';
    header('Location: /helpdesk-php/admin-tickets.php');
    exit;
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    $_SESSION['ticket_error'] = 'No se puede modificar un ticket cerrado.';
    header('Location: /helpdesk-php/admin-tickets.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $oldAssigned = $ticket['assigned_to'] !== null ? (string)$ticket['assigned_to'] : null;
    $newAssigned = $assignedTo !== null ? (string)$assignedTo : null;

    $oldSupportLevel = !empty($ticket['support_level']) ? (int)$ticket['support_level'] : 1;
    $finalSupportLevel = $newTechLevel ?? $oldSupportLevel;

    $assignmentChanged = $oldAssigned !== $newAssigned;
    $levelChanged = $assignmentChanged && $newTechLevel !== null && $newTechLevel !== $oldSupportLevel;

    /*
    |--------------------------------------------------------------------------
    | Si el ticket cambia de nivel, guardamos el nivel anterior en historial
    |--------------------------------------------------------------------------
    */
    if ($levelChanged) {
        $historyStmt = $pdo->prepare("
            INSERT INTO ticket_level_history (
                ticket_id,
                technician_id,
                support_level,
                level_started_at,
                level_first_response_at,
                level_finished_at,
                result
            ) VALUES (
                :ticket_id,
                :technician_id,
                :support_level,
                :level_started_at,
                :level_first_response_at,
                NOW(),
                'ESCALADO'
            )
        ");

        $historyStmt->execute([
            'ticket_id' => $ticketId,
            'technician_id' => $ticket['assigned_to'] ?? null,
            'support_level' => $oldSupportLevel,
            'level_started_at' => $ticket['level_started_at'] ?? $ticket['created_at'],
            'level_first_response_at' => $ticket['level_first_response_at'] ?? null
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar ticket
    |--------------------------------------------------------------------------
    | Si cambia de nivel:
    | - support_level pasa al nivel del nuevo técnico
    | - level_started_at se reinicia con NOW()
    | - level_first_response_at vuelve a NULL
    |
    | Si solo cambia técnico del mismo nivel:
    | - no reinicia TTA/TTR
    |--------------------------------------------------------------------------
    */
    $sqlUpdate = "
        UPDATE tickets
        SET 
            status = :status,
            assigned_to = :assigned_to,
            support_level = :support_level,
            level_started_at = CASE
                WHEN :level_changed = 1 THEN NOW()
                WHEN level_started_at IS NULL THEN NOW()
                ELSE level_started_at
            END,
            level_first_response_at = CASE
                WHEN :level_changed = 1 THEN NULL
                ELSE level_first_response_at
            END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :ticket_id
    ";

    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->bindValue(':status', $status);

    if ($assignedTo === null) {
        $stmtUpdate->bindValue(':assigned_to', null, PDO::PARAM_NULL);
    } else {
        $stmtUpdate->bindValue(':assigned_to', $assignedTo, PDO::PARAM_INT);
    }

    $stmtUpdate->bindValue(':support_level', $finalSupportLevel, PDO::PARAM_INT);
    $stmtUpdate->bindValue(':level_changed', $levelChanged ? 1 : 0, PDO::PARAM_INT);
    $stmtUpdate->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
    $stmtUpdate->execute();

    /*
    |--------------------------------------------------------------------------
    | Registrar actividad
    |--------------------------------------------------------------------------
    */
    if ($ticket['status'] !== $status) {
        createTicketActivity(
            $pdo,
            $ticketId,
            (int)$currentUser['id'],
            $currentUser['name'],
            $currentUser['role'],
            'STATUS_CHANGED',
            'El administrador cambió el estado del ticket.',
            $ticket['status'],
            $status
        );
    }

    if ($assignmentChanged) {
        $assignedLabel = $assignedTo === null ? 'Sin asignar' : 'Técnico ID ' . $assignedTo;

        createTicketActivity(
            $pdo,
            $ticketId,
            (int)$currentUser['id'],
            $currentUser['name'],
            $currentUser['role'],
            'ASSIGNED',
            'El administrador actualizó la asignación del ticket a ' . $assignedLabel . '.',
            $oldAssigned,
            $newAssigned
        );
    }

    if ($levelChanged) {
        createTicketActivity(
            $pdo,
            $ticketId,
            (int)$currentUser['id'],
            $currentUser['name'],
            $currentUser['role'],
            'LEVEL_ESCALATED',
            'El ticket fue escalado del nivel ' . $oldSupportLevel . ' al nivel ' . $finalSupportLevel . '. El conteo TTA/TTR del nuevo nivel inició nuevamente.',
            (string)$oldSupportLevel,
            (string)$finalSupportLevel
        );
    }

    $pdo->commit();

    $_SESSION['ticket_success'] = 'El ticket fue actualizado correctamente.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['ticket_error'] = 'Ocurrió un error al actualizar el ticket.';
}

header('Location: /helpdesk-php/admin-tickets.php');
exit;