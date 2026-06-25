<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/session.php';
require_once __DIR__ . '/../helpers/system_security.php';

class AuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(string $email, string $password): array
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

        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
            try {
                $rehash = password_hash($password, PASSWORD_DEFAULT);
                $rehashStatement = $this->pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $rehashStatement->execute(['password' => $rehash, 'id' => $userId]);
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

        systemSecurityAudit(
            $this->pdo,
            'LOGIN_SUCCESS',
            'Inicio de sesión correcto.',
            $userId,
            $userId,
            'info',
            ['role' => (string) ($user['role'] ?? '')]
        );

        return [
            'success' => true,
            'role' => (string) ($user['role'] ?? ''),
            'force_password_change' => $forcePasswordChange,
        ];
    }

    public function logout(string $reason = 'Cierre de sesión voluntario'): void
    {
        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);

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
