<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

$systemSecurityReady = systemSecurityReady($pdo);
$systemSecuritySettings = getSystemSecuritySettings($pdo);

if (!empty($_SESSION['system_security_old']) && is_array($_SESSION['system_security_old'])) {
    $systemSecuritySettings = array_merge($systemSecuritySettings, $_SESSION['system_security_old']);
    unset($_SESSION['system_security_old']);
}

$systemSecurityCsrfToken = systemSecurityCsrfToken('settings');
$systemSecurityActionCsrfToken = systemSecurityCsrfToken('actions');
$systemSecurityLevel = systemSecurityProtectionLevel($systemSecuritySettings);
$systemSecuritySessions = $systemSecurityReady ? systemSecurityActiveSessions($pdo, 25) : [];
$systemSecurityCompanies = $systemSecurityReady ? systemSecurityCompanyTraceSummaries($pdo, 5) : [];
$systemSecurityCompanyTotal = $systemSecurityReady ? systemSecurityCompanyTraceCount($pdo) : 0;
$systemSecurityGeneralLogs = $systemSecurityReady ? systemSecurityGeneralTraceLogs($pdo, 5) : [];
$systemSecurityGeneralTotal = $systemSecurityReady ? systemSecurityGeneralTraceCount($pdo) : 0;
$currentSecuritySessionToken = (string) ($_SESSION['security_session_token'] ?? '');

require __DIR__ . '/app/views/admin/system-security.php';
