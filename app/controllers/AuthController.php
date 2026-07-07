<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/session.php';
require_once __DIR__ . '/../helpers/system_security.php';
require_once __DIR__ . '/../helpers/remember_me.php';
require_once __DIR__ . '/../helpers/login_two_factor.php';

class AuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(string $email, string $password, bool $rememberMe = false): array
    {
        $email = strtolower(trim($email));
        $settings = getSystemSecuritySettings($this->pdo);

        $statement = $this->pdo->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            systemSecurityAudit(
                $this->pdo,
                'LOGIN_FAILED_UNKNOWN_EMAIL',
                'Intento de acceso con credenciales no válidas.',
                null,
                null,
                'warning',
                ['email' => $email]
            );

            return [
                'success' => false,
                'message' => 'Correo o contraseña incorrectos.',
            ];
        }

        $userId = (int) $user['id'];

        if (!empty($settings['block_inactive_users']) && (int) ($user['status'] ?? 0) !== 1) {
            systemSecurityAudit(
                $this->pdo,
                'LOGIN_BLOCKED_INACTIVE',
                'Se rechazó el acceso de una cuenta inactiva.',
                $userId,
                null,
                'warning'
            );

            return [
                'success' => false,
                'message' => 'Correo o contraseña incorrectos.',
            ];
        }

        $lockedUntil = trim((string) ($user['locked_until'] ?? ''));
        if ($lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time()) {
            $minutes = max(1, (int) ceil((strtotime($lockedUntil) - time()) / 60));

            systemSecurityAudit(
                $this->pdo,
                'LOGIN_BLOCKED_TEMPORARILY',
                'Se rechazó un acceso porque la cuenta continúa bloqueada.',
                $userId,
                null,
                'warning',
                ['locked_until' => $lockedUntil]
            );

            return [
                'success' => false,
                'message' => "Cuenta bloqueada temporalmente. Intenta nuevamente en {$minutes} minuto" . ($minutes === 1 ? '' : 's') . '.',
            ];
        }

        $failedAttempts = (int) ($user['failed_login_attempts'] ?? 0);
        $failedAt = trim((string) ($user['failed_login_at'] ?? ''));
        $resetMinutes = max(5, (int) ($settings['failed_attempt_reset_minutes'] ?? 30));

        if ($failedAt !== '' && strtotime($failedAt) !== false && strtotime($failedAt) < (time() - ($resetMinutes * 60))) {
            $failedAttempts = 0;
        }

        if (!password_verify($password, (string) $user['password'])) {
            $failedAttempts++;
            $maxAttempts = max(3, (int) ($settings['max_failed_attempts'] ?? 5));
            $lockoutMinutes = max(1, (int) ($settings['lockout_minutes'] ?? 15));
            $newLockedUntil = $failedAttempts >= $maxAttempts
                ? date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60))
                : null;

            $this->updateFailedLogin($userId, $failedAttempts, $newLockedUntil);

            systemSecurityAudit(
                $this->pdo,
                $newLockedUntil ? 'ACCOUNT_TEMPORARILY_LOCKED' : 'LOGIN_FAILED_PASSWORD',
                $newLockedUntil
                    ? 'La cuenta fue bloqueada por exceder los intentos permitidos.'
                    : 'Intento de acceso con contraseña incorrecta.',
                $userId,
                null,
                $newLockedUntil ? 'critical' : 'warning',
                [
                    'attempts' => $failedAttempts,
                    'max_attempts' => $maxAttempts,
                    'locked_until' => $newLockedUntil,
                ]
            );

            return [
                'success' => false,
                'message' => $newLockedUntil
                    ? "Cuenta bloqueada temporalmente durante {$lockoutMinutes} minuto" . ($lockoutMinutes === 1 ? '' : 's') . '.'
                    : 'Correo o contraseña incorrectos.',
            ];
        }

        $this->resetFailedLogin($userId);

        $credentialHash = (string) $user['password'];

        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
            try {
                $rehash = password_hash($password, PASSWORD_DEFAULT);
                $rehashStatement = $this->pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $rehashStatement->execute(['password' => $rehash, 'id' => $userId]);
                $credentialHash = $rehash;
            } catch (Throwable $exception) {
                // El inicio de sesión continúa con el hash válido actual.
            }
        }

        $forcePasswordChange = !empty($user['force_password_change'])
            || systemSecurityPasswordExpired($user, $settings);

        if ($forcePasswordChange && systemSecurityColumnExists($this->pdo, 'users', 'force_password_change')) {
            try {
                $forceStatement = $this->pdo->prepare('UPDATE users SET force_password_change = 1 WHERE id = :id');
                $forceStatement->execute(['id' => $userId]);
            } catch (Throwable $exception) {
                // El valor también se conserva en la sesión.
            }
        }

        if (function_exists('loginTwoFactorActive') && loginTwoFactorActive($this->pdo)) {
            return $this->startTwoFactorChallenge($user, $rememberMe, $forcePasswordChange);
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $userId,
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'status' => (int) ($user['status'] ?? 1),
            'profile_photo' => $user['profile_photo'] ?? null,
            'force_password_change' => $forcePasswordChange ? 1 : 0,
        ];

        // Compatibilidad con páginas antiguas del proyecto.
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = (string) ($user['name'] ?? '');
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');
        $_SESSION['user_role'] = (string) ($user['role'] ?? '');

        $token = systemSecurityCreateSession($this->pdo, $userId, $settings);
        if ($token !== null) {
            $_SESSION['security_session_token'] = $token;
        }

        $_SESSION['security_session_started_at'] = time();
        $_SESSION['security_last_activity_at'] = time();
        $_SESSION['security_db_touch_at'] = time();

        if ($rememberMe) {
            authRememberIssueForUser($this->pdo, $userId, $credentialHash);
        } else {
            authRememberForgetCurrent($this->pdo, 'El usuario inició sesión sin recordar el dispositivo');
        }

        systemSecurityAudit(
            $this->pdo,
            'LOGIN_SUCCESS',
            'Inicio de sesión correcto.',
            $userId,
            $userId,
            'info',
            [
                'role' => (string) ($user['role'] ?? ''),
                'remember_me' => $rememberMe,
            ]
        );

        return [
            'success' => true,
            'role' => (string) ($user['role'] ?? ''),
            'force_password_change' => $forcePasswordChange,
        ];
    }

    public function completeTwoFactorLogin(int $userId, bool $rememberMe = false, bool $forcePasswordChange = false): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int) ($user['status'] ?? 0) !== 1) {
            systemSecurityAudit(
                $this->pdo,
                'TWO_FACTOR_LOGIN_BLOCKED',
                'No se pudo completar el inicio de sesión tras la verificación de dos pasos.',
                $userId > 0 ? $userId : null,
                null,
                'warning'
            );

            return [
                'success' => false,
                'message' => 'La cuenta ya no está disponible para iniciar sesión.',
            ];
        }

        $settings = getSystemSecuritySettings($this->pdo);
        $credentialHash = (string) ($user['password'] ?? '');
        $forcePasswordChange = $forcePasswordChange
            || !empty($user['force_password_change'])
            || systemSecurityPasswordExpired($user, $settings);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $userId,
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'status' => (int) ($user['status'] ?? 1),
            'profile_photo' => $user['profile_photo'] ?? null,
            'force_password_change' => $forcePasswordChange ? 1 : 0,
        ];

        // Compatibilidad con páginas antiguas del proyecto.
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = (string) ($user['name'] ?? '');
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');
        $_SESSION['user_role'] = (string) ($user['role'] ?? '');

        $token = systemSecurityCreateSession($this->pdo, $userId, $settings);
        if ($token !== null) {
            $_SESSION['security_session_token'] = $token;
        }

        $_SESSION['security_session_started_at'] = time();
        $_SESSION['security_last_activity_at'] = time();
        $_SESSION['security_db_touch_at'] = time();

        if ($rememberMe) {
            authRememberIssueForUser($this->pdo, $userId, $credentialHash);
        } else {
            authRememberForgetCurrent($this->pdo, 'El usuario inició sesión sin recordar el dispositivo');
        }

        try {
            $sets = [];
            foreach (['last_login_at' => 'NOW()'] as $column => $value) {
                if (systemSecurityColumnExists($this->pdo, 'users', $column)) {
                    $sets[] = "`{$column}` = {$value}";
                }
            }
            if ($sets) {
                $update = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $update->execute(['id' => $userId]);
            }
        } catch (Throwable $exception) {
            // La autenticación no falla si no se registra la fecha.
        }

        systemSecurityAudit(
            $this->pdo,
            'LOGIN_SUCCESS_2FA',
            'Inicio de sesión correcto con autenticación de dos pasos.',
            $userId,
            $userId,
            'info',
            [
                'role' => (string) ($user['role'] ?? ''),
                'remember_me' => $rememberMe,
                'two_factor' => true,
            ]
        );

        return [
            'success' => true,
            'role' => (string) ($user['role'] ?? ''),
            'force_password_change' => $forcePasswordChange,
        ];
    }

    private function startTwoFactorChallenge(array $user, bool $rememberMe, bool $forcePasswordChange): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $config = loginTwoFactorConfig();
        $ttlMinutes = loginTwoFactorTtlMinutes($config);
        $resendSeconds = loginTwoFactorResendSeconds($config);

        if (!loginTwoFactorMailConfigured($config)) {
            return [
                'success' => false,
                'message' => 'El correo de verificación todavía no está configurado. Comunícate con el administrador.',
            ];
        }

        try {
            $issued = loginTwoFactorIssueChallenge(
                $this->pdo,
                $user,
                $ttlMinutes,
                loginTwoFactorMaxAttempts($config)
            );

            $delivery = loginTwoFactorSendEmail(
                $config,
                $user,
                (string) $issued['code'],
                $ttlMinutes
            );

            loginTwoFactorMarkDelivery(
                $this->pdo,
                (int) $issued['id'],
                (bool) $delivery['ok'],
                (string) $delivery['error']
            );

            if (empty($delivery['ok'])) {
                loginTwoFactorLogMailError($userId, (string) $delivery['error']);

                systemSecurityAudit(
                    $this->pdo,
                    'TWO_FACTOR_EMAIL_FAILED',
                    'No se pudo enviar el código de autenticación de dos pasos.',
                    $userId,
                    null,
                    'warning',
                    ['error' => substr((string) $delivery['error'], 0, 180)]
                );

                return [
                    'success' => false,
                    'message' => 'No se pudo enviar el código de verificación. Revisa la configuración SMTP o intenta nuevamente.',
                ];
            }

            $_SESSION['pending_2fa'] = [
                'challenge_id' => (int) $issued['id'],
                'user_id' => $userId,
                'name' => (string) ($user['name'] ?? 'Usuario'),
                'email' => (string) ($user['email'] ?? ''),
                'masked_email' => loginTwoFactorMaskEmail((string) ($user['email'] ?? '')),
                'role' => (string) ($user['role'] ?? ''),
                'remember_me' => $rememberMe ? 1 : 0,
                'force_password_change' => $forcePasswordChange ? 1 : 0,
                'expires_at' => (int) $issued['expires_at_ts'],
                'issued_at' => time(),
                'resend_available_at' => time() + $resendSeconds,
            ];

            systemSecurityAudit(
                $this->pdo,
                'TWO_FACTOR_CODE_SENT',
                'Se envió un código de autenticación de dos pasos.',
                $userId,
                null,
                'info',
                ['expires_in_minutes' => $ttlMinutes]
            );

            return [
                'success' => false,
                'requires_2fa' => true,
                'masked_email' => $_SESSION['pending_2fa']['masked_email'],
                'message' => 'Te enviamos un código de verificación al correo registrado.',
            ];
        } catch (Throwable $exception) {
            systemSecurityAudit(
                $this->pdo,
                'TWO_FACTOR_START_FAILED',
                'No se pudo iniciar la autenticación de dos pasos.',
                $userId > 0 ? $userId : null,
                null,
                'warning',
                ['error' => substr($exception->getMessage(), 0, 180)]
            );

            return [
                'success' => false,
                'message' => 'No se pudo preparar la verificación de dos pasos. Inténtalo nuevamente.',
            ];
        }
    }

    public function logout(string $reason = 'Cierre de sesión voluntario'): void
    {
        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);

        authRememberForgetCurrent($this->pdo, $reason);

        if ($currentUserId > 0) {
            systemSecurityRevokeCurrentSession($this->pdo, $reason);
            systemSecurityAudit(
                $this->pdo,
                'LOGOUT',
                $reason,
                $currentUserId,
                $currentUserId,
                'info'
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    private function updateFailedLogin(int $userId, int $attempts, ?string $lockedUntil): void
    {
        if (!systemSecurityColumnExists($this->pdo, 'users', 'failed_login_attempts')) {
            return;
        }

        try {
            $statement = $this->pdo->prepare(
                "UPDATE users
                 SET failed_login_attempts = :attempts,
                     failed_login_at = NOW(),
                     locked_until = :locked_until
                 WHERE id = :id"
            );
            $statement->execute([
                'attempts' => $attempts,
                'locked_until' => $lockedUntil,
                'id' => $userId,
            ]);
        } catch (Throwable $exception) {
            // La autenticación no falla por un problema en el contador.
        }
    }

    private function resetFailedLogin(int $userId): void
    {
        try {
            $sets = [];

            foreach ([
                'failed_login_attempts' => '0',
                'failed_login_at' => 'NULL',
                'locked_until' => 'NULL',
                'last_login_at' => 'NOW()',
            ] as $column => $value) {
                if (systemSecurityColumnExists($this->pdo, 'users', $column)) {
                    $sets[] = "`{$column}` = {$value}";
                }
            }

            if ($sets) {
                $statement = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $statement->execute(['id' => $userId]);
            }
        } catch (Throwable $exception) {
            // El acceso continúa aunque no se actualice la telemetría.
        }
    }
}
