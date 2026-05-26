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

function redirectWithTicketError(string $message): void
{
    $_SESSION['ticket_error'] = $message;
    header('Location: /helpdesk-php/admin-tickets.php');
    exit;
}

function redirectWithTicketSuccess(string $message): void
{
    $_SESSION['ticket_success'] = $message;
    header('Location: /helpdesk-php/admin-tickets.php');
    exit;
}

function safeCreateTicketActivity(PDO $pdo, int $ticketId, int $userId, string $userName, string $userRole, string $actionType, string $description, $oldValue = null, $newValue = null): void
{
    try {
        createTicketActivity(
            $pdo,
            $ticketId,
            $userId,
            $userName,
            $userRole,
            $actionType,
            $description,
            $oldValue,
            $newValue
        );
    } catch (Throwable $e) {
        error_log('[ticket_activity] No se pudo registrar actividad: ' . $e->getMessage());
    }
}

$ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
$status = trim($_POST['status'] ?? '');
$assignedToRaw = trim($_POST['assigned_to'] ?? '');

$allowedStatus = ['ABIERTO', 'EN_PROCESO', 'RESPONDIDO', 'CERRADO'];

if ($ticketId <= 0) {
    redirectWithTicketError('Ticket inválido.');
}

if (!in_array($status, $allowedStatus, true)) {
    redirectWithTicketError('Estado no válido.');
}

$assignedTo = null;
$newTechLevel = null;
$selectedTechName = null;

if ($assignedToRaw !== '') {
    $assignedTo = (int)$assignedToRaw;

    $sqlTech = "SELECT id, name, tech_level
                FROM users
                WHERE id = :id
                  AND role = 'TECH'
                  AND status = 1
                LIMIT 1";

    $stmtTech = $pdo->prepare($sqlTech);
    $stmtTech->execute(['id' => $assignedTo]);
    $techExists = $stmtTech->fetch(PDO::FETCH_ASSOC);

    if (!$techExists) {
        redirectWithTicketError('El técnico seleccionado no es válido.');
    }

    $newTechLevel = !empty($techExists['tech_level']) ? (int)$techExists['tech_level'] : 1;
    $selectedTechName = $techExists['name'] ?? null;

    if ($newTechLevel < 1 || $newTechLevel > 3) {
        redirectWithTicketError('El nivel del técnico seleccionado no es válido.');
    }
}

$sqlTicket = "SELECT 
                  t.id, 
                  t.status, 
                  t.assigned_to,
                  t.support_level,
                  t.level_started_at,
                  t.level_first_response_at,
                  t.created_at,
                  u.tech_level AS assigned_tech_level
              FROM tickets t
              LEFT JOIN users u ON u.id = t.assigned_to
              WHERE t.id = :ticket_id
              LIMIT 1";

$stmtTicket = $pdo->prepare($sqlTicket);
$stmtTicket->execute(['ticket_id' => $ticketId]);
$ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    redirectWithTicketError('El ticket no existe.');
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    redirectWithTicketError('No se puede modificar un ticket cerrado.');
}

$oldAssigned = $ticket['assigned_to'] !== null ? (string)$ticket['assigned_to'] : null;
$newAssigned = $assignedTo !== null ? (string)$assignedTo : null;

$assignedTechLevel = !empty($ticket['assigned_tech_level']) ? (int)$ticket['assigned_tech_level'] : 0;
$storedSupportLevel = !empty($ticket['support_level']) ? (int)$ticket['support_level'] : 0;

/*
|--------------------------------------------------------------------------
| Nivel actual real
|--------------------------------------------------------------------------
| Si el ticket ya tiene técnico asignado, manda el nivel del técnico.
| Si no tiene técnico asignado, se considera 0 para permitir la primera asignación.
*/
$oldSupportLevel = $oldAssigned !== null
    ? ($assignedTechLevel > 0 ? $assignedTechLevel : ($storedSupportLevel > 0 ? $storedSupportLevel : 1))
    : 0;

$finalSupportLevel = $newTechLevel ?? ($oldSupportLevel > 0 ? $oldSupportLevel : 1);

$assignmentChanged = $oldAssigned !== $newAssigned;
$levelChanged = $assignmentChanged && $newTechLevel !== null && $newTechLevel !== $oldSupportLevel;

/*
|--------------------------------------------------------------------------
| Validación de escalamiento / desescalamiento también en backend
|--------------------------------------------------------------------------
| Evita saltar de nivel 1 a nivel 3 y evita bajar de nivel 2/3 a técnicos inferiores.
*/
if ($newTechLevel !== null && $oldAssigned !== null && $oldSupportLevel > 0) {
    if ($newTechLevel < $oldSupportLevel) {
        redirectWithTicketError('No puedes desescalar a un técnico inferior.');
    }

    if ($newTechLevel > $oldSupportLevel + 1) {
        redirectWithTicketError('No puedes escalar a este nivel. Primero asigna a un técnico del nivel intermedio.');
    }
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Si el ticket cambia de nivel, guardamos el nivel anterior en historial
    |--------------------------------------------------------------------------
    | El historial es complementario. Si la tabla no existe o tiene una restricción
    | diferente, no debe bloquear la asignación del técnico.
    */
    if ($levelChanged && $oldAssigned !== null && $oldSupportLevel > 0) {
        try {
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
                'technician_id' => (int)$oldAssigned,
                'support_level' => $oldSupportLevel,
                'level_started_at' => $ticket['level_started_at'] ?? $ticket['created_at'],
                'level_first_response_at' => $ticket['level_first_response_at'] ?? null
            ]);
        } catch (PDOException $historyError) {
            error_log('[ticket_level_history] No se pudo guardar historial: ' . $historyError->getMessage());
        }
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
    | La actividad no debe romper la actualización principal del ticket.
    */
    if ($ticket['status'] !== $status) {
        safeCreateTicketActivity(
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
        $assignedLabel = $assignedTo === null
            ? 'Sin asignar'
            : 'Técnico ' . ($selectedTechName ?: ('ID ' . $assignedTo));

        safeCreateTicketActivity(
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
        safeCreateTicketActivity(
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

    redirectWithTicketSuccess('El ticket fue actualizado correctamente.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[update-ticket-admin] Error al actualizar ticket: ' . $e->getMessage());
    redirectWithTicketError('Ocurrió un error al actualizar el ticket.');
}
