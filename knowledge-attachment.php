<?php

declare(strict_types=1);

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/knowledge_base.php';

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$forceDownload = filter_input(INPUT_GET, 'download', FILTER_VALIDATE_BOOL);

if (!$attachmentId || $attachmentId <= 0) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

try {
    $statement = $pdo->prepare(
        'SELECT
            a.id,
            a.original_name,
            a.file_path,
            a.mime_type,
            a.file_size,
            a.is_image,
            k.is_active AS article_is_active
         FROM knowledge_base_attachments a
         INNER JOIN knowledge_base_articles k ON k.id = a.article_id
         WHERE a.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => (int)$attachmentId]);
    $attachment = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $exception) {
    $attachment = null;
}

$currentRole = strtoupper((string)(user()['role'] ?? ''));
$canViewInactive = isLoggedIn() && in_array($currentRole, ['ADMIN', 'TECH'], true);

if (!$attachment || ((int)($attachment['article_is_active'] ?? 0) !== 1 && !$canViewInactive)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$absolutePath = knowledgeBaseAbsolutePath((string)($attachment['file_path'] ?? ''));

if (!$absolutePath || !is_file($absolutePath)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

$originalName = trim((string)($attachment['original_name'] ?? 'archivo'));
$originalName = str_replace(["\r", "\n", '"'], ['', '', "'"], $originalName);
$mimeType = trim((string)($attachment['mime_type'] ?? 'application/octet-stream'));
$isImage = (int)($attachment['is_image'] ?? 0) === 1;
$inlineAllowed = $isImage || $mimeType === 'application/pdf';
$disposition = (!$forceDownload && $inlineAllowed) ? 'inline' : 'attachment';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($absolutePath));
header("Content-Disposition: {$disposition}; filename=\"{$originalName}\"; filename*=UTF-8''" . rawurlencode($originalName));

readfile($absolutePath);
exit;
