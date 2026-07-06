<?php

declare(strict_types=1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/helpers/system_security.php';
require_once __DIR__ . '/app/helpers/password_reset.php';

if (isLoggedIn()) {
    header('Location: /helpdesk-php/index.php');
    exit;
}

$errorMessage = '';
$successMessage = '';
$rawToken = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$resetCsrfToken = systemSecurityCsrfToken('reset_password');
$settings = getSystemSecuritySettings($pdo);
$passwordRules = systemSecurityPasswordRulesText($settings);
$tokenRecord = passwordResetFindValidToken($pdo, $rawToken);

if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
    $tokenRecord = null;
    $errorMessage = 'El enlace de recuperación no es válido.';
} elseif (!$tokenRecord) {
    $errorMessage = 'El enlace venció, ya fue utilizado o dejó de ser válido.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'reset_password')) {
        $errorMessage = 'El formulario venció. Abre nuevamente el enlace recibido.';
    } elseif (!$tokenRecord) {
        $errorMessage = 'El enlace venció, ya fue utilizado o dejó de ser válido.';
    } elseif ($newPassword === '' || $confirmPassword === '') {
        $errorMessage = 'Completa ambos campos de contraseña.';
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $errorMessage = 'Las contraseñas no coinciden.';
    } else {
        $passwordErrors = systemSecurityPasswordErrors($newPassword, $settings, [
            'email' => (string) ($tokenRecord['email'] ?? ''),
            'name' => (string) ($tokenRecord['name'] ?? ''),
        ]);

        if ($passwordErrors) {
            $errorMessage = implode(' ', $passwordErrors);
        } else {
            try {
                $pdo->beginTransaction();

                $lockedToken = passwordResetFindValidToken($pdo, $rawToken, true);
                if (!$lockedToken) {
                    throw new RuntimeException('El enlace dejó de ser válido.');
                }

                $userId = (int) $lockedToken['user_id'];
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if (!is_string($passwordHash) || $passwordHash === '') {
                    throw new RuntimeException('No se pudo proteger la contraseña.');
                }

                $updateUser = $pdo->prepare(
                    "UPDATE users
                     SET password = :password,
                         password_changed_at = NOW(),
                         force_password_change = 0,
                         failed_login_attempts = 0,
                         failed_login_at = NULL,
                         locked_until = NULL
                     WHERE id = :id"
                );
                $updateUser->execute([
                    'password' => $passwordHash,
                    'id' => $userId,
                ]);

                passwordResetConsumeToken(
                    $pdo,
                    (int) $lockedToken['token_id'],
                    $userId
                );

                $pdo->commit();

                if (!empty($settings['invalidate_sessions_on_password_change'])) {
                    systemSecurityRevokeUserSessions(
                        $pdo,
                        $userId,
                        'Contraseña restablecida mediante enlace de recuperación'
                    );
                }

                systemSecurityAudit(
                    $pdo,
                    'PASSWORD_RESET_SELF_SERVICE',
                    'El usuario restableció su contraseña mediante un enlace seguro.',
                    $userId,
                    $userId,
                    'warning'
                );

                unset($_SESSION['system_security_csrf_reset_password']);
                $successMessage = 'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.';
                $errorMessage = '';
                $tokenRecord = null;
                $rawToken = '';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Password reset consume error: ' . $exception->getMessage());
                $errorMessage = 'No fue posible actualizar la contraseña. Solicita un enlace nuevo e inténtalo otra vez.';
            }
        }
    }
}

$authCssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/auth-app.css';
$authCssVersion = is_file($authCssPath) ? (string) filemtime($authCssPath) : (string) time();

require __DIR__ . '/app/views/auth/reset-password.php';
