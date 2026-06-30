<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$redirect = '/helpdesk-php/admin-system-backups.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}
if (!systemToolsModuleReady($pdo)) {
    $_SESSION['settings_error'] = 'Primero ejecuta database/system_tools.sql en phpMyAdmin.';
    header('Location: ' . $redirect);
    exit;
}
if (!systemToolsVerifyCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Vuelve a intentarlo.';
    header('Location: ' . $redirect);
    exit;
}

$type = strtoupper(trim((string) ($_POST['backup_type'] ?? 'DATABASE')));
$currentUser = (array) user();

try {
    $backup = systemToolsCreateBackup($pdo, $type, (int) ($currentUser['id'] ?? 0));
    $_SESSION['settings_success'] = 'Respaldo creado correctamente: ' . $backup['file_name'] . ' (' . systemToolsFormatBytes($backup['size_bytes']) . ').';
} catch (Throwable $e) {
    systemToolsLog($pdo, 'BACKUP_FAILED', 'Falló la creación de un respaldo.', (int) ($currentUser['id'] ?? 0), 'critical', ['type' => $type, 'error' => $e->getMessage()]);
    $_SESSION['settings_error'] = $e->getMessage();
}

header('Location: ' . $redirect);
exit;
