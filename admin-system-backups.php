<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$csrfToken = systemToolsCsrfToken();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$totalBackups = $systemToolsReady ? (int) $pdo->query('SELECT COUNT(*) FROM system_backup_records')->fetchColumn() : 0;
$totalPages = max(1, (int) ceil($totalBackups / $perPage));
$page = min($page, $totalPages);
$backups = $systemToolsReady ? systemToolsBackupRecords($pdo, $perPage, ($page - 1) * $perPage) : [];
$zipAvailable = class_exists('ZipArchive');
$backupDirectoryWritable = systemToolsEnsureDirectory(systemToolsBackupsDirectory()) && is_writable(systemToolsBackupsDirectory());

require __DIR__ . '/app/views/admin/system-backups.php';
