<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$maintenanceSettings = getSystemMaintenanceSettings($pdo);
$csrfToken = systemToolsCsrfToken();

require __DIR__ . '/app/views/admin/maintenance-mode.php';
