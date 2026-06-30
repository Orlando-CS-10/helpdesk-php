<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$maintenanceSettings = getSystemMaintenanceSettings($pdo);
$databaseStats = systemToolsDatabaseStats($pdo);
$backupCount = $systemToolsReady ? (int) $pdo->query('SELECT COUNT(*) FROM system_backup_records')->fetchColumn() : 0;
$recentActionCount = $systemToolsReady ? (int) $pdo->query("SELECT COUNT(*) FROM system_maintenance_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() : 0;
$technicalLogCount = systemToolsTableExists($pdo, 'system_technical_logs')
    ? (int) $pdo->query("SELECT COUNT(*) FROM system_technical_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn()
    : 0;

require __DIR__ . '/app/views/admin/system-tools.php';
