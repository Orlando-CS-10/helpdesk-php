<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_customization.php';

$systemCustomizationReady = systemCustomizationTableExists($pdo);
$systemCustomization = getSystemCustomization($pdo);

if (!empty($_SESSION['system_customization_old']) && is_array($_SESSION['system_customization_old'])) {
    $systemCustomization = array_merge($systemCustomization, $_SESSION['system_customization_old']);
    unset($_SESSION['system_customization_old']);
}

$systemCustomizationCsrfToken = systemCustomizationCsrfToken();

require __DIR__ . '/app/views/admin/system-customization.php';
