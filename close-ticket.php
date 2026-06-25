<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/system_sla.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;

if ($ticketId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$currentUser = (array) user();

if (($currentUser['role'] ?? '') !== 'CLIENT') {
    $_SESSION['ticket_error'] = 'Solo el cliente puede cerrar su ticket.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

if (!systemSlaVerifyCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['ticket_error'] = 'La confirmación venció. Vuelve a intentar el cierre.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT *
     FROM tickets
     WHERE id = :ticket_id
       AND requester_id = :requester_id
     LIMIT 1'
);
$stmt->execute([
    'ticket_id' => $ticketId,
    'requester_id' => (int) ($currentUser['id'] ?? 0),
]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['ticket_error'] = 'No tienes permiso para cerrar este ticket.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    $_SESSION['ticket_error'] = 'Este ticket ya está cerrado.';
    header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
    exit;
}

$closedAt = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    systemSlaSyncPauseState(
        $pdo,
        $ticketId,
        (string) ($ticket['status'] ?? ''),
        'CERRADO',
        $closedAt
    );

    $refreshStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
    $refreshStmt->execute(['id' => $ticketId]);
    $ticketForMetrics = $refreshStmt->fetch(PDO::FETCH_ASSOC) ?: $ticket;
    $metrics = systemSlaCloseMetrics($ticketForMetrics, $closedAt);

    $assignments = [
        "status = 'CERRADO'",
        'client_closed = 1',
        'closed_at = :closed_at',
        'sla_met = :sla_met',
        'updated_at = :closed_at',
    ];
    $params = [
        'closed_at' => $closedAt,
        'sla_met' => $metrics['met'],
        'ticket_id' => $ticketId,
    ];

    if (systemSlaColumnExists($pdo, 'tickets', 'sla_ttr_met')) {
        $assignments[] = 'sla_ttr_met = :sla_ttr_met';
        $params['sla_ttr_met'] = $metrics['met'];
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE tickets SET ' . implode(', ', $assignments) . ' WHERE id = :ticket_id'
    );
    $stmtUpdate->execute($params);

    createTicketActivity(
        $pdo,
        $ticketId,
        (int) ($currentUser['id'] ?? 0),
        (string) ($currentUser['name'] ?? 'Cliente'),
        (string) ($currentUser['role'] ?? 'CLIENT'),
        'CLOSED',
        'El cliente cerró el ticket. Resultado SLA TTR: ' . ($metrics['met'] === 1 ? 'cumplido.' : 'incumplido.'),
        $ticket['status'] ?? null,
        'CERRADO'
    );

    createNotification(
        $pdo,
        (int) ($currentUser['id'] ?? 0),
        'Ticket cerrado',
        'Tu ticket #' . $ticketId . ' fue cerrado correctamente.',
        'success',
        $ticketId
    );

    notifyAdmins(
        $pdo,
        'Ticket cerrado por el cliente',
        'El cliente cerró el ticket #' . $ticketId . '. Resultado SLA: ' . ($metrics['met'] === 1 ? 'cumplido' : 'incumplido') . '.',
        'warning',
        $ticketId
    );

    $pdo->commit();
    $_SESSION['ticket_success'] = 'El ticket fue cerrado correctamente. Ahora puedes calificar la atención.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['ticket_error'] = 'Ocurrió un error al cerrar el ticket.';
}

header('Location: /helpdesk-php/ticket-detail.php?id=' . $ticketId);
exit;
