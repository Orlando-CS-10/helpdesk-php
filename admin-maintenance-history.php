<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$totalRows = $systemToolsReady ? (int) $pdo->query('SELECT COUNT(*) FROM system_maintenance_logs')->fetchColumn() : 0;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$historyRows = $systemToolsReady ? systemToolsHistory($pdo, $perPage, ($page - 1) * $perPage) : [];

require __DIR__ . '/app/views/admin/maintenance-history.php';
