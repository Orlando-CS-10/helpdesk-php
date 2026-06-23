<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

$attachmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$inlineRequested = isset($_GET['inline']) && (int)$_GET['inline'] === 1;

if ($attachmentId <= 0 || !ticketTableExists($pdo, 'ticket_message_attachments')) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$statement = $pdo->prepare(
    'SELECT
        a.*,
        t.requester_id,
        t.assigned_to,
        COALESCE(t.company_id, requester.company_id) AS ticket_company_id
     FROM ticket_message_attachments a
     INNER JOIN tickets t ON t.id = a.ticket_id
     INNER JOIN users requester ON requester.id = t.requester_id
     WHERE a.id = :attachment_id
     LIMIT 1'
);
$statement->execute(['attachment_id' => $attachmentId]);
$attachment = $statement->fetch(PDO::FETCH_ASSOC);

if (!$attachment) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$currentUser = (array)user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));

$allowed = in_array($currentRole, ['ADMIN', 'TECH'], true);

if (!$allowed && $currentRole === 'CLIENT') {
    if ((int)$attachment['requester_id'] === $currentUserId) {
        $allowed = true;
    } elseif (ticketColumnExists($pdo, 'users', 'can_view_company_tickets')) {
        $accessStatement = $pdo->prepare(
            'SELECT company_id, can_view_company_tickets
             FROM users
             WHERE id = :user_id
             LIMIT 1'
        );
        $accessStatement->execute(['user_id' => $currentUserId]);
        $access = $accessStatement->fetch(PDO::FETCH_ASSOC) ?: [];

        $allowed = (int)($access['can_view_company_tickets'] ?? 0) === 1
            && !empty($access['company_id'])
            && (int)$access['company_id'] === (int)$attachment['ticket_company_id'];
    }
}

if (
    strtoupper((string)$attachment['message_scope']) === 'INTERNAL'
    && !in_array($currentRole, ['ADMIN', 'TECH'], true)
) {
    $allowed = false;
}

if (!$allowed) {
    http_response_code(403);
    exit('No tienes permiso para acceder a este archivo.');
}

$relativePath = ltrim((string)$attachment['storage_path'], '/');
$absolutePath = ticketStorageBasePath() . '/' . $relativePath;

if (!is_file($absolutePath)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

$mimeType = (string)($attachment['mime_type'] ?? 'application/octet-stream');
$originalName = ticketSanitizeFileName((string)($attachment['original_name'] ?? 'archivo'));
$isImage = str_starts_with($mimeType, 'image/');
$disposition = $inlineRequested && $isImage ? 'inline' : 'attachment';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($absolutePath));
header(
    'Content-Disposition: '
    . $disposition
    . '; filename="'
    . rawurlencode($originalName)
    . '"; filename*=UTF-8\'\''
    . rawurlencode($originalName)
);

readfile($absolutePath);
exit;
