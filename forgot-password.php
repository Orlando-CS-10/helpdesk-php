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
$noticeMessage = '';
$email = trim((string) ($_POST['email'] ?? ''));
$forgotCsrfToken = systemSecurityCsrfToken('forgot_password');
$mailConfig = passwordResetConfig();
$moduleReady = passwordResetTableReady($pdo);
$mailReady = passwordResetMailConfigured($mailConfig);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'forgot_password')) {
        $errorMessage = 'El formulario venció. Recarga la página e inténtalo nuevamente.';
    } elseif (!$moduleReady) {
        $errorMessage = 'El módulo de recuperación todavía no está instalado en la base de datos.';
    } elseif (!$mailReady) {
        $errorMessage = 'El servicio de correo todavía no está configurado por el administrador.';
    } elseif ($email === '') {
        $errorMessage = 'Ingresa tu correo electrónico.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Ingresa un correo electrónico válido.';
    } else {
        $genericNotice = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña. Revisa también la carpeta de spam.';

        try {
            $statement = $pdo->prepare(
                'SELECT id, name, email, status
                 FROM users
                 WHERE LOWER(email) = LOWER(:email)
                   AND status = 1
                 LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $account = $statement->fetch(PDO::FETCH_ASSOC);

            if ($account) {
                $userId = (int) $account['id'];

                if (!passwordResetRecentlyRequested($pdo, $userId, 60)) {
                    $ttlMinutes = passwordResetTokenTtl($mailConfig);
                    $issued = passwordResetIssueToken($pdo, $account, $ttlMinutes);
                    $resetUrl = passwordResetBaseUrl($mailConfig)
                        . '/reset-password.php?token=' . rawurlencode((string) $issued['token']);

                    $delivery = passwordResetSendEmail(
                        $mailConfig,
                        $account,
                        $resetUrl,
                        $ttlMinutes
                    );

                    passwordResetMarkDelivery(
                        $pdo,
                        (int) $issued['id'],
                        (bool) $delivery['ok'],
                        (string) $delivery['error']
                    );

                    if (!empty($delivery['ok'])) {
                        systemSecurityAudit(
                            $pdo,
                            'PASSWORD_RESET_EMAIL_SENT',
                            'Se envió un enlace de recuperación de contraseña.',
                            $userId,
                            null,
                            'info',
                            ['expires_in_minutes' => $ttlMinutes]
                        );
                    } else {
                        passwordResetLogMailError($userId, (string) $delivery['error']);
                        systemSecurityAudit(
                            $pdo,
                            'PASSWORD_RESET_EMAIL_FAILED',
                            'No se pudo enviar el enlace de recuperación.',
                            $userId,
                            null,
                            'warning'
                        );
                    }
                } else {
                    systemSecurityAudit(
                        $pdo,
                        'PASSWORD_RESET_RATE_LIMITED',
                        'Se limitó una solicitud repetida de recuperación.',
                        $userId,
                        null,
                        'warning'
                    );
                }
            } else {
                // Reduce diferencias de tiempo sin revelar si el correo existe.
                usleep(random_int(180000, 360000));
            }

            $noticeMessage = $genericNotice;
            $email = '';
            unset($_SESSION['system_security_csrf_forgot_password']);
            $forgotCsrfToken = systemSecurityCsrfToken('forgot_password');
        } catch (Throwable $exception) {
            error_log('Password reset request error: ' . $exception->getMessage());
            $errorMessage = 'No fue posible procesar la solicitud en este momento. Inténtalo nuevamente más tarde.';
        }
    }
}

$authCssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/auth-app.css';
$authCssVersion = is_file($authCssPath) ? (string) filemtime($authCssPath) : (string) time();

require __DIR__ . '/app/views/auth/forgot-password.php';
