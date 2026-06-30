<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-system-logs.php');
    exit;
}

if (!systemToolsVerifyCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Actualiza la página e inténtalo nuevamente.';
    header('Location: /helpdesk-php/admin-system-logs.php');
    exit;
}

$currentUser = user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$days = (int) ($_POST['days'] ?? 0);
$confirmation = strtoupper(trim((string) ($_POST['confirmation'] ?? '')));
$adminPassword = (string) ($_POST['admin_password'] ?? '');

try {
    if ($confirmation !== 'ELIMINAR') {
        throw new RuntimeException('Escribe ELIMINAR exactamente para confirmar la limpieza.');
    }

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id AND role = :role LIMIT 1');
    $stmt->execute(['id' => $currentUserId, 'role' => 'ADMIN']);
    $passwordHash = (string) ($stmt->fetchColumn() ?: '');

    if ($passwordHash === '' || !password_verify($adminPassword, $passwordHash)) {
        throw new RuntimeException('La contraseña del administrador no es correcta.');
    }

    $deleted = systemToolsDeleteOldTechnicalLogs($pdo, $days, $currentUserId);
    $_SESSION['settings_success'] = $deleted > 0
        ? "Se eliminaron {$deleted} registros técnicos antiguos."
        : 'No se encontraron registros técnicos con esa antigüedad.';
} catch (Throwable $e) {
    $_SESSION['settings_error'] = substr($e->getMessage(), 0, 240);
}

header('Location: /helpdesk-php/admin-system-logs.php');
exit;
