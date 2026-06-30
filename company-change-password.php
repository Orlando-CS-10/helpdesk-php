<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/company_portal.php';
require_once __DIR__ . '/app/controllers/CompanyPortalAuthController.php';

$sessionResult = companyPortalRequireLogin($pdo);
$account = (array) ($sessionResult['account'] ?? companyPortalAccount() ?? []);
$settings = getSystemSecuritySettings($pdo);
$passwordRules = systemSecurityPasswordRulesText($settings);
$csrfToken = companyPortalCsrfToken('change_password');
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!companyPortalVerifyCsrf($_POST['csrf_token'] ?? null, 'change_password')) {
        $errorMessage = 'El formulario venció. Recarga la página e inténtalo nuevamente.';
    } else {
        $controller = new CompanyPortalAuthController($pdo);
        $result = $controller->changePassword(
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );

        if (!empty($result['success'])) {
            $_SESSION['company_portal_password_notice'] = (string) ($result['message'] ?? 'Contraseña actualizada.');
            header('Location: /helpdesk-php/company-dashboard.php');
            exit;
        }

        $errorMessage = (string) ($result['message'] ?? 'No se pudo actualizar la contraseña.');
    }
}

require __DIR__ . '/app/views/company-portal/change-password.php';
