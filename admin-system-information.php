<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';
require_once __DIR__ . '/app/helpers/system_profile.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$systemProfile = getSystemProfile($pdo);
$systemInformation = systemToolsInformation($pdo, $systemProfile);

require __DIR__ . '/app/views/admin/system-information.php';
