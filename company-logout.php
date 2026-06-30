<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/company_portal.php';
require_once __DIR__ . '/app/controllers/CompanyPortalAuthController.php';

$controller = new CompanyPortalAuthController($pdo);
$controller->logout();

header('Location: /helpdesk-php/company-login.php');
exit;
