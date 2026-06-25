<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

$userId = max(0, (int) ($_GET['user_id'] ?? 0));
$contact = systemSecurityContactTraceDetail($pdo, $userId);
if (!$contact) {
    http_response_code(404);
    $_SESSION['system_security_error'] = 'El contacto solicitado no existe o no pertenece a una empresa.';
    header('Location: /helpdesk-php/admin-security-companies.php');
    exit;
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'severity' => trim((string) ($_GET['severity'] ?? '')),
    'event_type' => trim((string) ($_GET['event_type'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$totalLogs = systemSecurityContactTraceCount($pdo, $userId, $filters);
$totalPages = max(1, (int) ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$logs = systemSecurityContactTraceLogs($pdo, $userId, $perPage, ($page - 1) * $perPage, $filters);
$eventTypes = systemSecurityTraceEventTypes($pdo, 'contact', $userId);

require __DIR__ . '/app/views/admin/security-contact.php';
