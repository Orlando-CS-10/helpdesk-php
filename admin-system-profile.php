<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_profile.php';

$systemProfileReady = systemProfileTableExists($pdo);
$systemProfile = getSystemProfile($pdo);

if (!empty($_SESSION['system_profile_old']) && is_array($_SESSION['system_profile_old'])) {
    $systemProfile = array_merge($systemProfile, $_SESSION['system_profile_old']);
    unset($_SESSION['system_profile_old']);
}

$systemProfileLogoUrl = systemProfileLogoUrl($systemProfile['logo_path'] ?? null);
$systemProfileCsrfToken = systemProfileCsrfToken();

require __DIR__ . '/app/views/admin/system-profile.php';
