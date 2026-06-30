<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$redirect = '/helpdesk-php/admin-system-cleanup.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}
if (!systemToolsVerifyCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Vuelve a intentarlo.';
    header('Location: ' . $redirect);
    exit;
}
if (strtoupper(trim((string) ($_POST['confirmation'] ?? ''))) !== 'LIMPIAR') {
    $_SESSION['settings_error'] = 'Escribe LIMPIAR para confirmar la operación.';
    header('Location: ' . $redirect);
    exit;
}

$currentUser = (array) user();
$categories = is_array($_POST['categories'] ?? null) ? $_POST['categories'] : [];

try {
    $result = systemToolsRunCleanup($pdo, $categories, (int) ($currentUser['id'] ?? 0));
    $_SESSION['settings_success'] = 'Limpieza terminada: ' . (int) $result['deleted'] . ' elementos procesados y ' . systemToolsFormatBytes((int) $result['bytes']) . ' recuperados.';
} catch (Throwable $e) {
    $_SESSION['settings_error'] = $e->getMessage();
}

header('Location: ' . $redirect);
exit;
