<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

$companyId = max(0, (int) ($_GET['company_id'] ?? 0));
$company = systemSecurityCompanyTraceDetail($pdo, $companyId);
if (!$company) {
    http_response_code(404);
    $_SESSION['system_security_error'] = 'La empresa solicitada no existe.';
    header('Location: /helpdesk-php/admin-security-companies.php');
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$totalContacts = systemSecurityCompanyContactTraceCount($pdo, $companyId, $search);
$totalPages = max(1, (int) ceil($totalContacts / $perPage));
$page = min($page, $totalPages);
$contacts = systemSecurityCompanyContactTraceSummaries($pdo, $companyId, $perPage, ($page - 1) * $perPage, $search);

require __DIR__ . '/app/views/admin/security-company.php';
