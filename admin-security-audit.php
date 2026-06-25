<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

function securityAuditValidDate(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

$systemSecurityReady = systemSecurityReady($pdo);
$truncateSecurityFilter = static function (string $value, int $length): string {
    $value = trim($value);
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $length, 'UTF-8')
        : substr($value, 0, $length);
};

$securityAuditFilters = [
    'q' => $truncateSecurityFilter((string) ($_GET['q'] ?? ''), 120),
    'event_type' => $truncateSecurityFilter((string) ($_GET['event_type'] ?? ''), 80),
    'severity' => trim((string) ($_GET['severity'] ?? '')),
    'user_id' => max(0, (int) ($_GET['user_id'] ?? 0)),
    'date_from' => securityAuditValidDate((string) ($_GET['date_from'] ?? '')),
    'date_to' => securityAuditValidDate((string) ($_GET['date_to'] ?? '')),
];

if (!in_array($securityAuditFilters['severity'], ['', 'info', 'warning', 'critical'], true)) {
    $securityAuditFilters['severity'] = '';
}

$securityAuditPageNumber = max(1, (int) ($_GET['page'] ?? 1));
$securityAuditPerPage = 10;
$securityAuditResult = $systemSecurityReady
    ? systemSecurityAuditPaginated($pdo, $securityAuditFilters, $securityAuditPageNumber, $securityAuditPerPage)
    : [
        'items' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => $securityAuditPerPage,
        'total_pages' => 1,
        'from' => 0,
        'to' => 0,
    ];

$securityAuditEventTypes = $systemSecurityReady ? systemSecurityAuditEventTypes($pdo) : [];
$securityAuditUsers = $systemSecurityReady ? systemSecurityAuditUsers($pdo) : [];

require __DIR__ . '/app/views/admin/security-audit.php';
