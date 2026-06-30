<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$technicalLogsReady = systemToolsTableExists($pdo, 'system_technical_logs');
$filters = systemToolsTechnicalLogNormalizeFilters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$totalLogs = $technicalLogsReady ? systemToolsTechnicalLogCount($pdo, $filters) : 0;
$totalPages = max(1, (int) ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$technicalLogs = $technicalLogsReady
    ? systemToolsTechnicalLogs($pdo, $filters, $perPage, $offset)
    : [];
$technicalSummary = $technicalLogsReady
    ? systemToolsTechnicalLogSummary($pdo, $filters)
    : ['total' => 0, 'info' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0];
$technicalModules = $technicalLogsReady ? systemToolsTechnicalLogModules($pdo) : [];
$technicalUsers = $technicalLogsReady ? systemToolsTechnicalLogUsers($pdo) : [];
$csrfToken = systemToolsCsrfToken();

require __DIR__ . '/app/views/admin/system-logs.php';
