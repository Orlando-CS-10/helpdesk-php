<?php

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/controllers/AuthController.php';
require_once __DIR__ . '/app/helpers/session.php';

$authController = new AuthController($pdo);
$authController->logout();

header('Location: /helpdesk-php/login.php');
exit;
