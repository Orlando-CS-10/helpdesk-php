<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !systemToolsTableExists($pdo, 'system_backup_records')) {
    http_response_code(404);
    exit('Respaldo no encontrado.');
}

$stmt = $pdo->prepare('SELECT * FROM system_backup_records WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$record) {
    http_response_code(404);
    exit('Respaldo no encontrado.');
}

$root = realpath(systemToolsBackupsDirectory());
$file = realpath(systemToolsProjectRoot() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $record['storage_path']));
if (!$root || !$file || !str_starts_with($file, $root) || !is_file($file)) {
    http_response_code(404);
    exit('El archivo del respaldo ya no existe.');
}

$downloadName = basename((string) $record['file_name']);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
