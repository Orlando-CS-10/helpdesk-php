<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';
require_once __DIR__ . '/app/helpers/system_sla.php';

requireLogin();

function replyTicketRedirect(int $ticketId, string $type, string $message): never
{
    $_SESSION[$type === 'success' ? 'ticket_success' : 'ticket_error'] = $message;

    $destination = $ticketId > 0
        ? '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '#conversationTab'
        : '/helpdesk-php/home.php';

    header('Location: ' . $destination);
    exit;
}

function replyTicketCurrentUserCanAccess(PDO $pdo, array $ticket, array $currentUser): bool
{
    $currentUserId = (int)($currentUser['id'] ?? 0);
    $currentRole = strtoupper((string)($currentUser['role'] ?? ''));

    if ($currentUserId <= 0) {
        return false;
    }

    if (in_array($currentRole, ['ADMIN', 'TECH'], true)) {
        return true;
    }

    if ($currentRole !== 'CLIENT') {
        return false;
    }

    if ((int)($ticket['requester_id'] ?? 0) === $currentUserId) {
        return true;
    }

    if (!ticketColumnExists($pdo, 'users', 'company_id')
        || !ticketColumnExists($pdo, 'users', 'can_view_company_tickets')
    ) {
        return false;
    }

    $accessStatement = $pdo->prepare(
        'SELECT company_id, can_view_company_tickets
         FROM users
         WHERE id = :user_id
         LIMIT 1'
    );
    $accessStatement->execute(['user_id' => $currentUserId]);
    $access = $accessStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    $canViewCompanyTickets = (int)($access['can_view_company_tickets'] ?? 0) === 1;
    $companyId = !empty($access['company_id']) ? (int)$access['company_id'] : 0;
    $ticketCompanyId = !empty($ticket['company_id']) ? (int)$ticket['company_id'] : 0;

    return $canViewCompanyTickets
        && $companyId > 0
        && $ticketCompanyId > 0
        && $companyId === $ticketCompanyId;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$rawMessage = (string)($_POST['message'] ?? '');

if ($ticketId <= 0) {
    replyTicketRedirect(0, 'error', 'No se pudo identificar el ticket.');
}

$currentUser = (array)user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

$ticketStatement = $pdo->prepare(
    'SELECT
        t.*,
        requester.company_id AS requester_company_id
     FROM tickets t
     INNER JOIN users requester ON requester.id = t.requester_id
     WHERE t.id = :ticket_id
     LIMIT 1'
);
$ticketStatement->execute(['ticket_id' => $ticketId]);
$ticket = $ticketStatement->fetch(PDO::FETCH_ASSOC);

if (!$ticket || !replyTicketCurrentUserCanAccess($pdo, $ticket, $currentUser)) {
    replyTicketRedirect($ticketId, 'error', 'No tienes permiso para responder este ticket.');
}

if (($ticket['status'] ?? '') === 'CERRADO') {
    replyTicketRedirect($ticketId, 'error', 'No se puede responder un ticket cerrado.');
}

$prepared = [];

try {
    $prepared = ticketPrepareRichMessage(
        $ticketId,
        $rawMessage,
        $_FILES['inline_images'] ?? null,
        $_FILES['attachments'] ?? null
    );

    $pdo->beginTransaction();

    $hasFormatColumn = ticketColumnExists($pdo, 'ticket_messages', 'message_format');
    $hasUpdatedColumn = ticketColumnExists($pdo, 'ticket_messages', 'updated_at');

    $columns = ['ticket_id', 'user_id', 'message', 'created_at'];
    $values = [':ticket_id', ':user_id', ':message', 'NOW()'];
    $params = [
        'ticket_id' => $ticketId,
        'user_id' => $currentUserId,
        'message' => $prepared['html'],
    ];

    if ($hasFormatColumn) {
        $columns[] = 'message_format';
        $values[] = ':message_format';
        $params['message_format'] = 'html';
    }

    if ($hasUpdatedColumn) {
        $columns[] = 'updated_at';
        $values[] = 'NOW()';
    }

    $insertStatement = $pdo->prepare(
        'INSERT INTO ticket_messages (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $values) . ')'
    );
    $insertStatement->execute($params);

    $messageId = (int)$pdo->lastInsertId();

    $persisted = ticketPersistPreparedAttachments(
        $pdo,
        $ticketId,
        'PUBLIC',
        $messageId,
        $currentUserId,
        $prepared['files'],
        $prepared['html']
    );

    if ($persisted['html'] !== $prepared['html']) {
        $updateHtmlStatement = $pdo->prepare(
            'UPDATE ticket_messages
             SET message = :message
             WHERE id = :message_id'
        );
        $updateHtmlStatement->execute([
            'message' => $persisted['html'],
            'message_id' => $messageId,
        ]);
    }

    $now = date('Y-m-d H:i:s');

    if (empty($ticket['first_response_at']) && $currentRole !== 'CLIENT') {
        systemSlaMarkFirstResponse($pdo, $ticketId, $ticket, $now);
    }

    $newStatus = $currentRole === 'CLIENT'
        ? 'ABIERTO'
        : 'RESPONDIDO';

    $ticketUpdateStatement = $pdo->prepare(
        'UPDATE tickets
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :ticket_id'
    );
    $ticketUpdateStatement->execute([
        'status' => $newStatus,
        'updated_at' => $now,
        'ticket_id' => $ticketId,
    ]);

    systemSlaSyncPauseState(
        $pdo,
        $ticketId,
        (string)($ticket['status'] ?? ''),
        $newStatus,
        $now
    );

    $activityDescription = match ($currentRole) {
        'CLIENT' => 'El cliente respondió en el ticket.',
        'ADMIN' => 'El administrador respondió en el ticket.',
        default => 'El técnico respondió en el ticket.',
    };

    createTicketActivity(
        $pdo,
        $ticketId,
        $currentUserId,
        (string)($currentUser['name'] ?? 'Usuario'),
        $currentRole,
        'REPLIED',
        $activityDescription
    );

    if ($currentRole !== 'CLIENT') {
        createNotification(
            $pdo,
            (int)$ticket['requester_id'],
            'Nueva respuesta en tu ticket',
            'Tu ticket #' . $ticketId . ' recibió una nueva respuesta.',
            'info',
            $ticketId
        );
    } else {
        if (!empty($ticket['assigned_to']) && (int)$ticket['assigned_to'] !== $currentUserId) {
            createNotification(
                $pdo,
                (int)$ticket['assigned_to'],
                'Nueva respuesta del cliente',
                'El cliente respondió en el ticket #' . $ticketId . '.',
                'info',
                $ticketId
            );
        }

        notifyAdmins(
            $pdo,
            'Nueva respuesta del cliente',
            'El cliente respondió en el ticket #' . $ticketId . '.',
            'info',
            $ticketId
        );
    }

    $pdo->commit();

    replyTicketRedirect($ticketId, 'success', 'La respuesta fue enviada correctamente.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (!empty($prepared)) {
        ticketCleanupPreparedFiles($prepared);
    }

    error_log('[reply-ticket] Ticket #' . $ticketId . ': ' . $exception->getMessage());

    replyTicketRedirect(
        $ticketId,
        'error',
        $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Ocurrió un error al enviar la respuesta.'
    );
}
