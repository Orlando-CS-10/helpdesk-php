<?php

declare(strict_types=1);

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/controllers/AuthController.php';
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/helpers/system_security.php';
require_once __DIR__ . '/app/helpers/login_two_factor.php';

if (isLoggedIn()) {
    header('Location: /helpdesk-php/index.php');
    exit;
}

$pending = $_SESSION['pending_2fa'] ?? null;
if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) <= 0 || (int) ($pending['challenge_id'] ?? 0) <= 0) {
    header('Location: /helpdesk-php/login.php');
    exit;
}

if (!loginTwoFactorActive($pdo)) {
    unset($_SESSION['pending_2fa']);
    header('Location: /helpdesk-php/login.php?reason=expired');
    exit;
}

if ((int) ($pending['expires_at'] ?? 0) < time()) {
    unset($_SESSION['pending_2fa']);
    header('Location: /helpdesk-php/login.php?reason=2fa_expired');
    exit;
}

$errorMessage = '';
$noticeMessage = (string) ($_SESSION['two_factor_notice'] ?? '');
unset($_SESSION['two_factor_notice']);
$verifyCsrfToken = systemSecurityCsrfToken('two_factor_verify');
$authController = new AuthController($pdo);
$config = loginTwoFactorConfig();
$resendSeconds = loginTwoFactorResendSeconds($config);

$userId = (int) ($pending['user_id'] ?? 0);
$challengeId = (int) ($pending['challenge_id'] ?? 0);
$maskedEmail = (string) ($pending['masked_email'] ?? loginTwoFactorMaskEmail((string) ($pending['email'] ?? '')));
$secondsRemaining = max(0, (int) ($pending['expires_at'] ?? time()) - time());
$resendRemaining = max(0, (int) ($pending['resend_available_at'] ?? 0) - time());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'verify');

    if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'two_factor_verify')) {
        $errorMessage = 'El formulario venció. Recarga la página e inténtalo nuevamente.';
    } elseif ($action === 'resend') {
        if ($resendRemaining > 0) {
            $errorMessage = "Espera {$resendRemaining} segundo" . ($resendRemaining === 1 ? '' : 's') . ' para reenviar el código.';
        } elseif (loginTwoFactorRecentlyRequested($pdo, $userId, $resendSeconds)) {
            $errorMessage = "Espera un momento antes de solicitar otro código.";
        } else {
            try {
                $statement = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = :id AND status = 1 LIMIT 1');
                $statement->execute(['id' => $userId]);
                $user = $statement->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    throw new RuntimeException('La cuenta ya no está disponible.');
                }

                $issued = loginTwoFactorIssueChallenge(
                    $pdo,
                    $user,
                    loginTwoFactorTtlMinutes($config),
                    loginTwoFactorMaxAttempts($config)
                );

                $delivery = loginTwoFactorSendEmail(
                    $config,
                    $user,
                    (string) $issued['code'],
                    (int) $issued['expires_in_minutes']
                );

                loginTwoFactorMarkDelivery(
                    $pdo,
                    (int) $issued['id'],
                    (bool) $delivery['ok'],
                    (string) $delivery['error']
                );

                if (empty($delivery['ok'])) {
                    loginTwoFactorLogMailError($userId, (string) $delivery['error']);
                    throw new RuntimeException('No se pudo enviar el nuevo código.');
                }

                $_SESSION['pending_2fa']['challenge_id'] = (int) $issued['id'];
                $_SESSION['pending_2fa']['expires_at'] = (int) $issued['expires_at_ts'];
                $_SESSION['pending_2fa']['issued_at'] = time();
                $_SESSION['pending_2fa']['resend_available_at'] = time() + $resendSeconds;
                $_SESSION['pending_2fa']['masked_email'] = loginTwoFactorMaskEmail((string) ($user['email'] ?? ''));

                systemSecurityAudit(
                    $pdo,
                    'TWO_FACTOR_CODE_RESENT',
                    'Se reenvió un código de autenticación de dos pasos.',
                    $userId,
                    null,
                    'info'
                );

                $_SESSION['two_factor_notice'] = 'Te enviamos un nuevo código de verificación.';
                header('Location: /helpdesk-php/verify-2fa.php');
                exit;
            } catch (Throwable $exception) {
                systemSecurityAudit(
                    $pdo,
                    'TWO_FACTOR_RESEND_FAILED',
                    'No se pudo reenviar el código de autenticación de dos pasos.',
                    $userId,
                    null,
                    'warning',
                    ['error' => substr($exception->getMessage(), 0, 180)]
                );

                $errorMessage = 'No se pudo reenviar el código. Inténtalo nuevamente.';
            }
        }
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));
        $validation = loginTwoFactorValidateCode($pdo, $challengeId, $userId, $code);

        if (!empty($validation['ok'])) {
            systemSecurityAudit(
                $pdo,
                'TWO_FACTOR_CODE_VALIDATED',
                'Código de autenticación de dos pasos validado correctamente.',
                $userId,
                null,
                'info'
            );

            $rememberMe = !empty($_SESSION['pending_2fa']['remember_me']);
            $forcePasswordChange = !empty($_SESSION['pending_2fa']['force_password_change']);
            unset($_SESSION['pending_2fa']);

            $loginResult = $authController->completeTwoFactorLogin($userId, $rememberMe, $forcePasswordChange);

            if (!empty($loginResult['success'])) {
                if (!empty($loginResult['force_password_change'])) {
                    header('Location: /helpdesk-php/change-password.php');
                } else {
                    header('Location: /helpdesk-php/index.php');
                }
                exit;
            }

            $errorMessage = (string) ($loginResult['message'] ?? 'No se pudo completar el inicio de sesión.');
        } else {
            systemSecurityAudit(
                $pdo,
                'TWO_FACTOR_CODE_FAILED',
                'Código de autenticación de dos pasos incorrecto o vencido.',
                $userId,
                null,
                !empty($validation['expired']) ? 'warning' : 'info'
            );

            if (!empty($validation['expired'])) {
                unset($_SESSION['pending_2fa']);
                header('Location: /helpdesk-php/login.php?reason=2fa_expired');
                exit;
            }

            $errorMessage = (string) ($validation['message'] ?? 'Código incorrecto.');
        }
    }

    $pending = $_SESSION['pending_2fa'] ?? $pending;
    $challengeId = (int) ($pending['challenge_id'] ?? $challengeId);
    $maskedEmail = (string) ($pending['masked_email'] ?? $maskedEmail);
    $secondsRemaining = max(0, (int) ($pending['expires_at'] ?? time()) - time());
    $resendRemaining = max(0, (int) ($pending['resend_available_at'] ?? 0) - time());
}

$authCssPath = $_SERVER['DOCUMENT_ROOT'] . '/helpdesk-php/public/assets/css/auth-app.css';
$authCssVersion = file_exists($authCssPath) ? filemtime($authCssPath) : time();
$twoFactorCssPath = __DIR__ . '/public/assets/css/two-factor.css';
$twoFactorCssVersion = file_exists($twoFactorCssPath) ? filemtime($twoFactorCssPath) : time();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de acceso - HelpDesk Pronet System</title>
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/auth-app.css?v=<?= (int) $authCssVersion ?>">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/two-factor.css?v=<?= (int) $twoFactorCssVersion ?>">
    <link rel="icon" type="image/png" href="/helpdesk-php/public/favicon/favicon-96x96.png" sizes="96x96">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="login-wrapper login-published two-factor-page">
    <section class="login-shell two-factor-shell">
        <aside class="login-brand-panel">
            <div class="login-brand-top">
                <div class="login-brand-mark">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <strong>Mesa de Ayuda</strong>
                    <small>Pronet System</small>
                </div>
            </div>

            <div class="login-brand-content">
                <span class="login-mini-label">Segundo candado</span>
                <h1>Verificación segura</h1>
                <p>Protegemos tu cuenta con un código temporal antes de permitir el acceso al sistema.</p>
            </div>
        </aside>

        <main class="login-card two-factor-card">
            <div class="login-card-inner">
                <div class="login-card-header two-factor-header">
                    <div class="login-icon two-factor-icon">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                    </div>
                    <div>
                        <h2>Revisa tu correo</h2>
                        <p class="subtitle">Ingresa el código de 6 dígitos enviado a <strong><?= htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                    </div>
                </div>

                <?php if ($noticeMessage !== ''): ?>
                    <div class="alert success login-alert">
                        <i class="fa-solid fa-circle-info"></i>
                        <span><?= htmlspecialchars($noticeMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert error login-alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/helpdesk-php/verify-2fa.php" class="login-form two-factor-form" autocomplete="off" data-two-factor-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($verifyCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="verify">

                    <div class="form-group login-input-group">
                        <label for="code">Código de verificación</label>
                        <div class="login-input-wrap two-factor-code-wrap">
                            <i class="fa-solid fa-key login-field-icon"></i>
                            <input
                                type="text"
                                id="code"
                                name="code"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                placeholder="000000"
                                autocomplete="one-time-code"
                                required
                                autofocus>
                        </div>
                        <small class="two-factor-hint">
                            <i class="fa-solid fa-clock"></i>
                            El código vence en <strong data-two-factor-timer><?= (int) ceil($secondsRemaining / 60) ?> min</strong>.
                        </small>
                    </div>

                    <button type="submit" class="btn-primary login-submit two-factor-submit">
                        <span>Verificar e ingresar</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <form method="POST" action="/helpdesk-php/verify-2fa.php" class="two-factor-resend-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($verifyCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="two-factor-resend-btn" <?= $resendRemaining > 0 ? 'disabled' : '' ?> data-resend-button data-resend-remaining="<?= (int) $resendRemaining ?>">
                        <i class="fa-solid fa-rotate-right"></i>
                        <span><?= $resendRemaining > 0 ? 'Reenviar en ' . (int) $resendRemaining . 's' : 'Reenviar código' ?></span>
                    </button>
                </form>

                <div class="two-factor-footer-actions">
                    <a href="/helpdesk-php/login.php" class="two-factor-back-link">
                        <i class="fa-solid fa-arrow-left"></i>
                        Volver al inicio de sesión
                    </a>
                </div>

                <p class="login-legal-note">
                    <i class="fa-solid fa-lock"></i>
                    Nunca compartas este código con otra persona.
                </p>
            </div>
        </main>
    </section>
</div>

<script>
(function () {
    const input = document.getElementById('code');
    if (input) {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    const timer = document.querySelector('[data-two-factor-timer]');
    let seconds = <?= (int) $secondsRemaining ?>;
    if (timer) {
        setInterval(function () {
            seconds = Math.max(0, seconds - 1);
            const minutes = Math.ceil(seconds / 60);
            timer.textContent = seconds > 0 ? `${minutes} min` : 'vencido';
        }, 1000);
    }

    const resendButton = document.querySelector('[data-resend-button]');
    if (resendButton) {
        let remaining = parseInt(resendButton.dataset.resendRemaining || '0', 10);
        const label = resendButton.querySelector('span');
        if (remaining > 0 && label) {
            const interval = setInterval(function () {
                remaining -= 1;
                if (remaining <= 0) {
                    resendButton.disabled = false;
                    label.textContent = 'Reenviar código';
                    clearInterval(interval);
                    return;
                }
                label.textContent = `Reenviar en ${remaining}s`;
            }, 1000);
        }
    }
})();
</script>
</body>
</html>
