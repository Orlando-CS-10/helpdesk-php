<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$redirect = '/helpdesk-php/admin-maintenance-mode.php';
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

$currentUser = (array) user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$password = (string) ($_POST['admin_password'] ?? '');
$enable = !empty($_POST['is_enabled']);
$message = trim((string) ($_POST['message'] ?? ''));
$estimatedReturn = trim((string) ($_POST['estimated_return_at'] ?? ''));
$allowAdmin = !empty($_POST['allow_admin']) ? 1 : 0;
$blockTech = !empty($_POST['block_tech']) ? 1 : 0;
$blockClient = !empty($_POST['block_client']) ? 1 : 0;

try {
    if ($currentUserId <= 0 || $password === '') {
        throw new RuntimeException('Ingresa tu contraseña de administrador para confirmar.');
    }
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id AND role = \'ADMIN\' LIMIT 1');
    $stmt->execute(['id' => $currentUserId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify($password, $hash)) {
        throw new RuntimeException('La contraseña del administrador no es correcta.');
    }
    if ($enable && strtoupper(trim((string) ($_POST['confirmation'] ?? ''))) !== 'MANTENIMIENTO') {
        throw new RuntimeException('Escribe MANTENIMIENTO para activar el modo mantenimiento.');
    }
    if ($message === '') {
        throw new RuntimeException('Ingresa un mensaje para los usuarios.');
    }
    if (!$allowAdmin) {
        throw new RuntimeException('Debe mantenerse permitido el acceso de administradores para poder desactivar el mantenimiento.');
    }

    $estimatedSql = null;
    if ($estimatedReturn !== '') {
        $timestamp = strtotime($estimatedReturn);
        if ($timestamp === false) {
            throw new RuntimeException('La fecha estimada de regreso no es válida.');
        }
        $estimatedSql = date('Y-m-d H:i:s', $timestamp);
    }

    $stmt = $pdo->prepare(
        'UPDATE system_maintenance_settings
         SET is_enabled = :is_enabled,
             message = :message,
             estimated_return_at = :estimated_return_at,
             allow_admin = :allow_admin,
             block_tech = :block_tech,
             block_client = :block_client,
             updated_by = :updated_by,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = 1'
    );
    $stmt->execute([
        'is_enabled' => $enable ? 1 : 0,
        'message' => $message,
        'estimated_return_at' => $estimatedSql,
        'allow_admin' => $allowAdmin,
        'block_tech' => $blockTech,
        'block_client' => $blockClient,
        'updated_by' => $currentUserId,
    ]);

    systemToolsLog($pdo, $enable ? 'MAINTENANCE_ENABLED' : 'MAINTENANCE_DISABLED', $enable ? 'Se activó el modo mantenimiento.' : 'Se desactivó el modo mantenimiento.', $currentUserId, $enable ? 'critical' : 'info', [
        'block_tech' => $blockTech,
        'block_client' => $blockClient,
        'estimated_return_at' => $estimatedSql,
    ]);

    $_SESSION['settings_success'] = $enable ? 'Modo mantenimiento activado correctamente.' : 'Modo mantenimiento desactivado correctamente.';
} catch (Throwable $e) {
    $_SESSION['settings_error'] = $e->getMessage();
}

header('Location: ' . $redirect);
exit;
