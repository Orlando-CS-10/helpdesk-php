<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$currentUser = user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$testDefinitions = systemToolsTestDefinitions();
$csrfToken = systemToolsCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!systemToolsVerifyCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['settings_error'] = 'La sesión del formulario venció. Actualiza la página e inténtalo nuevamente.';
        header('Location: /helpdesk-php/admin-system-tests.php');
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'run_all') {
            $keys = array_keys($testDefinitions);
        } elseif ($action === 'run_single') {
            $key = trim((string) ($_POST['test_key'] ?? ''));
            $keys = [$key];
        } else {
            throw new RuntimeException('Acción de prueba no válida.');
        }

        $newRun = systemToolsRunTests($pdo, $keys, $currentUserId);
        $previousRun = is_array($_SESSION['system_tools_test_run'] ?? null)
            ? $_SESSION['system_tools_test_run']
            : ['results' => []];

        if ($action === 'run_single') {
            $newRun['results'] = array_replace(
                is_array($previousRun['results'] ?? null) ? $previousRun['results'] : [],
                $newRun['results']
            );

            $summary = ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => 0];
            foreach ($newRun['results'] as $result) {
                $status = (string) ($result['status'] ?? 'error');
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
                $summary['total']++;
            }
            $newRun['summary'] = $summary;
        }

        $_SESSION['system_tools_test_run'] = $newRun;
        $_SESSION['settings_success'] = $action === 'run_all'
            ? 'Se ejecutaron todas las pruebas del sistema.'
            : 'La prueba seleccionada se ejecutó correctamente.';
    } catch (Throwable $e) {
        $_SESSION['settings_error'] = substr($e->getMessage(), 0, 240);
    }

    header('Location: /helpdesk-php/admin-system-tests.php');
    exit;
}

$testRun = is_array($_SESSION['system_tools_test_run'] ?? null)
    ? $_SESSION['system_tools_test_run']
    : null;

require __DIR__ . '/app/views/admin/system-tests.php';
