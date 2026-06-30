<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

$systemToolsReady = systemToolsModuleReady($pdo);
$cleanupAnalysis = systemToolsCleanupAnalysis($pdo);
$csrfToken = systemToolsCsrfToken();

require __DIR__ . '/app/views/admin/system-cleanup.php';
