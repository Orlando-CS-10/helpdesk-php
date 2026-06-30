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
if (!systemToolsVerifyCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Vuelve a intentarlo.';
    header('Location: ' . $redirect);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$currentUser = (array) user();

try {
    $stmt = $pdo->prepare('SELECT * FROM system_backup_records WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        throw new RuntimeException('El respaldo seleccionado no existe.');
    }

    $root = realpath(systemToolsBackupsDirectory());
    $candidate = systemToolsProjectRoot() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $record['storage_path']);
    $file = realpath($candidate);
    if ($root && $file && str_starts_with($file, $root) && is_file($file)) {
        @unlink($file);
    }

    $delete = $pdo->prepare('DELETE FROM system_backup_records WHERE id = :id');
    $delete->execute(['id' => $id]);
    systemToolsLog($pdo, 'BACKUP_DELETED', 'Se eliminó un respaldo del sistema.', (int) ($currentUser['id'] ?? 0), 'warning', ['file_name' => $record['file_name']]);
    $_SESSION['settings_success'] = 'Respaldo eliminado correctamente.';
} catch (Throwable $e) {
    $_SESSION['settings_error'] = $e->getMessage();
}

header('Location: ' . $redirect);
exit;
