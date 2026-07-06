<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/system_sla.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

function closeTicketRedirect(int $ticketId, string $type, string $message): never
{
    $_SESSION[$type === 'success' ? 'ticket_success' : 'ticket_error'] = $message;

    $destination = $ticketId > 0
        ? '/helpdesk-php/ticket-detail.php?id=' . $ticketId
        : '/helpdesk-php/home.php';

    header('Location: ' . $destination);
    exit;
}

function closeTicketSafeText(
    string $value,
    int $maxLength,
    bool $collapseWhitespace = true
): string {
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));

    if ($collapseWhitespace) {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$closureReasonId = isset($_POST['closure_reason_id']) ? (int)$_POST['closure_reason_id'] : 0;
$closureComment = closeTicketSafeText((string)($_POST['closure_comment'] ?? ''), 2000, false);
$confirmed = (string)($_POST['confirm_close'] ?? '') === '1';

if ($ticketId <= 0) {
    closeTicketRedirect(0, 'error', 'No se pudo identificar el ticket.');
}

if (!systemSlaVerifyCsrf($_POST['csrf_token'] ?? null)) {
    closeTicketRedirect($ticketId, 'error', 'La confirmación venció. Vuelve a intentar el cierre.');
}

if (!$confirmed) {
    closeTicketRedirect($ticketId, 'error', 'Debes confirmar el cierre definitivo del ticket.');
}

if (!ticketTableExists($pdo, 'closure_reasons') || !ticketTableExists($pdo, 'ticket_closures')) {
    closeTicketRedirect(
        $ticketId,
        'error',
        'El módulo de cierre todavía no está instalado. Ejecuta database/ticket_closures.sql.'
    );
}

$currentUser = (array)user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

$ticketStatement = $pdo->prepare(
    'SELECT *
     FROM tickets
     WHERE id = :ticket_id
     LIMIT 1'
);
$ticketStatement->execute(['ticket_id' => $ticketId]);
$ticket = $ticketStatement->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    closeTicketRedirect($ticketId, 'error', 'No se encontró el ticket solicitado.');
}

$hasPermission = match ($currentRole) {
    'ADMIN' => true,
    'TECH' => true,
    'CLIENT' => (int)($ticket['requester_id'] ?? 0) === $currentUserId,
    default => false,
};

if (!$hasPermission) {
    closeTicketRedirect($ticketId, 'error', 'No tienes permiso para cerrar este ticket.');
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    closeTicketRedirect($ticketId, 'error', 'Este ticket ya se encuentra cerrado.');
}

$reasonStatement = $pdo->prepare(
    'SELECT id, code, name, description, requires_comment
     FROM closure_reasons
     WHERE id = :id
       AND is_active = 1
     LIMIT 1'
);
$reasonStatement->execute(['id' => $closureReasonId]);
$reason = $reasonStatement->fetch(PDO::FETCH_ASSOC);

if (!$reason) {
    closeTicketRedirect($ticketId, 'error', 'Selecciona un motivo de cierre válido.');
}

if ((int)($reason['requires_comment'] ?? 0) === 1 && $closureComment === '') {
    closeTicketRedirect(
        $ticketId,
        'error',
        'El motivo seleccionado requiere un comentario de cierre.'
    );
}

$closedAt = date('Y-m-d H:i:s');
$actorName = closeTicketSafeText((string)($currentUser['name'] ?? 'Usuario'), 120);
$reasonName = closeTicketSafeText((string)($reason['name'] ?? 'Sin motivo'), 120);
$reasonCode = closeTicketSafeText((string)($reason['code'] ?? 'SIN_CODIGO'), 50);
$clientClosed = $currentRole === 'CLIENT' ? 1 : 0;

try {
    $pdo->beginTransaction();

    systemSlaSyncPauseState(
        $pdo,
        $ticketId,
        (string)($ticket['status'] ?? ''),
        'CERRADO',
        $closedAt
    );

    $refreshStatement = $pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
    $refreshStatement->execute(['id' => $ticketId]);
    $ticketForMetrics = $refreshStatement->fetch(PDO::FETCH_ASSOC) ?: $ticket;
    $metrics = systemSlaCloseMetrics($ticketForMetrics, $closedAt);

    $assignments = [
        "status = 'CERRADO'",
        'client_closed = :client_closed',
        'closed_at = :closed_at',
        'sla_met = :sla_met',
        'updated_at = :closed_at',
    ];
    $params = [
        'client_closed' => $clientClosed,
        'closed_at' => $closedAt,
        'sla_met' => $metrics['met'],
        'ticket_id' => $ticketId,
    ];

    if (systemSlaColumnExists($pdo, 'tickets', 'sla_ttr_met')) {
        $assignments[] = 'sla_ttr_met = :sla_ttr_met';
        $params['sla_ttr_met'] = $metrics['met'];
    }

    $updateStatement = $pdo->prepare(
        'UPDATE tickets
         SET ' . implode(', ', $assignments) . '
         WHERE id = :ticket_id'
    );
    $updateStatement->execute($params);

    $closureStatement = $pdo->prepare(
        'INSERT INTO ticket_closures (
            ticket_id,
            closure_reason_id,
            reason_code,
            reason_name,
            comment,
            closed_by,
            closed_by_name,
            closed_by_role,
            closed_at,
            sla_met
         ) VALUES (
            :ticket_id,
            :closure_reason_id,
            :reason_code,
            :reason_name,
            :comment,
            :closed_by,
            :closed_by_name,
            :closed_by_role,
            :closed_at,
            :sla_met
         )'
    );
    $closureStatement->execute([
        'ticket_id' => $ticketId,
        'closure_reason_id' => (int)$reason['id'],
        'reason_code' => $reasonCode,
        'reason_name' => $reasonName,
        'comment' => $closureComment !== '' ? $closureComment : null,
        'closed_by' => $currentUserId > 0 ? $currentUserId : null,
        'closed_by_name' => $actorName,
        'closed_by_role' => $currentRole,
        'closed_at' => $closedAt,
        'sla_met' => $metrics['met'],
    ]);

    $roleLabel = match ($currentRole) {
        'ADMIN' => 'El administrador',
        'TECH' => 'El técnico',
        'CLIENT' => 'El cliente',
        default => 'El usuario',
    };

    $activityDescription = $roleLabel . ' cerró el ticket. Motivo: ' . $reasonName . '.';

    if ($closureComment !== '') {
        $activityDescription .= ' Comentario: ' . $closureComment;
    }

    $activityDescription .= ' Resultado SLA TTR: '
        . ($metrics['met'] === 1 ? 'cumplido.' : 'incumplido.');

    createTicketActivity(
        $pdo,
        $ticketId,
        $currentUserId,
        $actorName,
        $currentRole,
        'CLOSED',
        closeTicketSafeText($activityDescription, 255),
        (string)($ticket['status'] ?? ''),
        'CERRADO|' . $reasonCode
    );

    $requesterId = (int)($ticket['requester_id'] ?? 0);

    if ($requesterId > 0) {
        createNotification(
            $pdo,
            $requesterId,
            'Ticket cerrado',
            'El ticket #' . $ticketId . ' fue cerrado. Motivo: ' . $reasonName . '.',
            'success',
            $ticketId
        );
    }

    $assignedTo = (int)($ticket['assigned_to'] ?? 0);

    if ($assignedTo > 0 && $assignedTo !== $currentUserId && $assignedTo !== $requesterId) {
        createNotification(
            $pdo,
            $assignedTo,
            'Ticket cerrado',
            'El ticket #' . $ticketId . ' fue cerrado por ' . $actorName
                . '. Motivo: ' . $reasonName . '.',
            'warning',
            $ticketId
        );
    }

    if ($currentRole !== 'ADMIN') {
        notifyAdmins(
            $pdo,
            'Ticket cerrado',
            $actorName . ' cerró el ticket #' . $ticketId
                . '. Motivo: ' . $reasonName
                . '. SLA: ' . ($metrics['met'] === 1 ? 'cumplido' : 'incumplido') . '.',
            'warning',
            $ticketId
        );
    }

    $pdo->commit();

    closeTicketRedirect(
        $ticketId,
        'success',
        'El ticket fue cerrado correctamente con el motivo "' . $reasonName . '".'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[close-ticket] Ticket #' . $ticketId . ': '
        . $exception->getMessage()
    );

    closeTicketRedirect(
        $ticketId,
        'error',
        'Ocurrió un error al cerrar el ticket. Revisa el registro del servidor.'
    );
}
