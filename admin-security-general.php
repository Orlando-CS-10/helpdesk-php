<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'severity' => trim((string) ($_GET['severity'] ?? '')),
    'event_type' => trim((string) ($_GET['event_type'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$totalLogs = systemSecurityGeneralTraceCount($pdo, $filters);
$totalPages = max(1, (int) ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$logs = systemSecurityGeneralTraceLogs($pdo, $perPage, ($page - 1) * $perPage, $filters);
$eventTypes = systemSecurityTraceEventTypes($pdo, 'general');

require __DIR__ . '/app/views/admin/security-general.php';
