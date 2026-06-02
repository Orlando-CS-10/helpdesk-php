<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

$redirectToTicket = function (int $ticketId): void {
    $target = $ticketId > 0
        ? '/helpdesk-php/ticket-detail.php?id=' . $ticketId . '&tab=internal#internalConversationTab'
        : '/helpdesk-php/home.php';

    header('Location: ' . $target);
    exit;
};

if (!in_array($currentRole, ['ADMIN', 'TECH'], true)) {
    $_SESSION['internal_message_error'] = 'No tienes permiso para enviar mensajes internos.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$message = trim((string)($_POST['message'] ?? ''));

if ($ticketId <= 0 || $message === '') {
    $_SESSION['internal_message_error'] = 'El mensaje interno no puede estar vacío.';
    $redirectToTicket($ticketId);
}

try {
    // Verifica que exista la tabla. Si no existe, el sistema avisará claramente.
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'ticket_internal_messages'");
    if (!$tableCheck || !$tableCheck->fetchColumn()) {
        $_SESSION['internal_message_error'] = 'Falta crear la tabla ticket_internal_messages en MySQL.';
        $redirectToTicket($ticketId);
    }

    $stmtTicket = $pdo->prepare('SELECT id, status FROM tickets WHERE id = :ticket_id LIMIT 1');
    $stmtTicket->execute(['ticket_id' => $ticketId]);
    $ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $_SESSION['internal_message_error'] = 'No se encontró el ticket indicado.';
        header('Location: /helpdesk-php/home.php');
        exit;
    }

    if (($ticket['status'] ?? '') === 'CERRADO') {
        $_SESSION['internal_message_error'] = 'No se pueden agregar mensajes internos a un ticket cerrado.';
        $redirectToTicket($ticketId);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ticket_internal_messages (ticket_id, user_id, message, created_at)
         VALUES (:ticket_id, :user_id, :message, NOW())'
    );

    $stmt->execute([
        'ticket_id' => $ticketId,
        'user_id' => (int)$currentUser['id'],
        'message' => $message,
    ]);

    $_SESSION['internal_message_success'] = 'Mensaje interno registrado correctamente.';
} catch (Throwable $e) {
    // En local conviene ver el motivo real; cuando publiques el sistema, puedes dejar solo el mensaje genérico.
    $_SESSION['internal_message_error'] = 'No se pudo guardar el mensaje interno. Detalle: ' . $e->getMessage();
}

$redirectToTicket($ticketId);
