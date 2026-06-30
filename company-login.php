<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/company_portal.php';
require_once __DIR__ . '/app/controllers/CompanyPortalAuthController.php';

if (companyPortalIsLoggedIn()) {
    $sessionCheck = companyPortalEnforceSession($pdo);
    if (!empty($sessionCheck['valid'])) {
        header('Location: ' . (!empty($sessionCheck['force_password_change'])
            ? '/helpdesk-php/company-change-password.php'
            : '/helpdesk-php/company-dashboard.php'));
        exit;
    }
}

$authController = new CompanyPortalAuthController($pdo);
$errorMessage = '';
$noticeMessage = '';
$loginCsrfToken = companyPortalCsrfToken('login');
$moduleReady = companyPortalModuleReady($pdo);

$reasonMessages = [
    'idle_timeout' => 'Tu sesión corporativa se cerró por inactividad. Ingresa nuevamente.',
    'absolute_timeout' => 'Tu sesión corporativa alcanzó su duración máxima.',
    'revoked' => 'La sesión corporativa fue cerrada por seguridad.',
    'inactive_account' => 'La cuenta corporativa o la empresa ya no tiene acceso.',
    'expired' => 'La sesión corporativa venció. Ingresa nuevamente.',
    'session_mismatch' => 'La sesión no pudo validarse. Ingresa nuevamente.',
];

$reason = trim((string) ($_GET['reason'] ?? ''));
if (isset($reasonMessages[$reason])) {
    $noticeMessage = $reasonMessages[$reason];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!companyPortalVerifyCsrf($_POST['csrf_token'] ?? null, 'login')) {
        $errorMessage = 'El formulario venció. Recarga la página e inténtalo nuevamente.';
    } else {
        $result = $authController->login(
            trim((string) ($_POST['email'] ?? '')),
            (string) ($_POST['password'] ?? '')
        );

        if (!empty($result['success'])) {
            header('Location: ' . (!empty($result['force_password_change'])
                ? '/helpdesk-php/company-change-password.php'
                : '/helpdesk-php/company-dashboard.php'));
            exit;
        }

        $errorMessage = (string) ($result['message'] ?? 'No se pudo iniciar sesión.');
    }
}

require __DIR__ . '/app/views/company-portal/login.php';
