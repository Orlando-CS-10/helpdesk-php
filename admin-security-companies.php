<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$totalCompanies = systemSecurityCompanyTraceCount($pdo, $search);
$totalPages = max(1, (int) ceil($totalCompanies / $perPage));
$page = min($page, $totalPages);
$companies = systemSecurityCompanyTraceSummaries($pdo, $perPage, ($page - 1) * $perPage, $search);

require __DIR__ . '/app/views/admin/security-companies.php';
