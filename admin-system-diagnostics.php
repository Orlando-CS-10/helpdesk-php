<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$diagnostics = systemToolsDiagnostics($pdo);
$csrfToken = systemToolsCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!systemToolsVerifyCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'La sesión del formulario venció. Vuelve a intentarlo.';
    } else {
        $currentUser = (array) user();
        systemToolsLog($pdo, 'DIAGNOSTIC_RUN', 'Se ejecutó un diagnóstico técnico del sistema.', (int) ($currentUser['id'] ?? 0), 'info', $diagnostics['summary']);
        $_SESSION['settings_success'] = 'Diagnóstico ejecutado correctamente.';
    }
    header('Location: /helpdesk-php/admin-system-diagnostics.php');
    exit;
}

require __DIR__ . '/app/views/admin/system-diagnostics.php';
