<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/company_portal.php';
require_once __DIR__ . '/../helpers/system_security.php';

class CompanyPortalAuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if (!companyPortalModuleReady($this->pdo)) {
            return [
                'success' => false,
                'message' => 'El Portal corporativo aún no está instalado. Ejecuta database/company_portal.sql.',
            ];
        }

        $settings = getSystemSecuritySettings($this->pdo);
        $statement = $this->pdo->prepare(
            "SELECT
                a.*,
                c.business_name,
                c.trade_name,
                c.ruc,
                c.logo_path,
                c.sla_contract_type,
                c.status AS company_status
             FROM company_portal_accounts a
             INNER JOIN client_companies c ON c.id = a.company_id
             WHERE a.email = :email
             LIMIT 1"
        );
        $statement->execute(['email' => $email]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            companyPortalAudit(
                $this->pdo,
                'LOGIN_FAILED_UNKNOWN_EMAIL',
                'Intento de acceso corporativo con credenciales no válidas.',
                null,
                null,
                'warning',
                ['email' => $email]
            );

            return ['success' => false, 'message' => 'Correo o contraseña incorrectos.'];
        }

        $accountId = (int) $account['id'];
        $companyId = (int) $account['company_id'];

        if ((int) ($account['status'] ?? 0) !== 1 || (int) ($account['company_status'] ?? 0) !== 1) {
            companyPortalAudit(
                $this->pdo,
                'LOGIN_BLOCKED_INACTIVE',
                'Se rechazó el acceso de una cuenta corporativa o empresa inactiva.',
                $companyId,
                $accountId,
                'warning'
            );
            return ['success' => false, 'message' => 'Correo o contraseña incorrectos.'];
        }

        $lockedUntil = trim((string) ($account['locked_until'] ?? ''));
        if ($lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time()) {
            $minutes = max(1, (int) ceil((strtotime($lockedUntil) - time()) / 60));
            companyPortalAudit(
                $this->pdo,
                'LOGIN_BLOCKED_TEMPORARILY',
                'Se rechazó un acceso porque la cuenta corporativa continúa bloqueada.',
                $companyId,
                $accountId,
                'warning',
                ['locked_until' => $lockedUntil]
            );
            return [
                'success' => false,
                'message' => "Cuenta bloqueada temporalmente. Intenta nuevamente en {$minutes} minuto" . ($minutes === 1 ? '' : 's') . '.',
            ];
        }

        $failedAttempts = (int) ($account['failed_login_attempts'] ?? 0);
        $failedAt = trim((string) ($account['failed_login_at'] ?? ''));
        $resetMinutes = max(5, (int) ($settings['failed_attempt_reset_minutes'] ?? 30));

        if ($failedAt !== '' && strtotime($failedAt) !== false && strtotime($failedAt) < (time() - ($resetMinutes * 60))) {
            $failedAttempts = 0;
        }

        if (!password_verify($password, (string) $account['password_hash'])) {
            $failedAttempts++;
            $maxAttempts = max(3, (int) ($settings['max_failed_attempts'] ?? 5));
            $lockoutMinutes = max(1, (int) ($settings['lockout_minutes'] ?? 15));
            $newLockedUntil = $failedAttempts >= $maxAttempts
                ? date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60))
                : null;

            try {
                $update = $this->pdo->prepare(
                    "UPDATE company_portal_accounts
                     SET failed_login_attempts = :attempts,
                         failed_login_at = NOW(),
                         locked_until = :locked_until
                     WHERE id = :id"
                );
                $update->execute([
                    'attempts' => $failedAttempts,
                    'locked_until' => $newLockedUntil,
                    'id' => $accountId,
                ]);
            } catch (Throwable $exception) {
                // La respuesta de autenticación continúa.
            }

            companyPortalAudit(
                $this->pdo,
                $newLockedUntil ? 'ACCOUNT_TEMPORARILY_LOCKED' : 'LOGIN_FAILED_PASSWORD',
                $newLockedUntil
                    ? 'La cuenta corporativa fue bloqueada por exceder los intentos permitidos.'
                    : 'Intento de acceso corporativo con contraseña incorrecta.',
                $companyId,
                $accountId,
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

        try {
            $reset = $this->pdo->prepare(
                "UPDATE company_portal_accounts
                 SET failed_login_attempts = 0,
                     failed_login_at = NULL,
                     locked_until = NULL,
                     last_login_at = NOW(),
                     last_login_ip = :last_login_ip
                 WHERE id = :id"
            );
            $reset->execute([
                'last_login_ip' => companyPortalClientIp() ?: null,
                'id' => $accountId,
            ]);
        } catch (Throwable $exception) {
            // El acceso no depende de la telemetría.
        }

        if (password_needs_rehash((string) $account['password_hash'], PASSWORD_DEFAULT)) {
            try {
                $rehash = $this->pdo->prepare(
                    'UPDATE company_portal_accounts SET password_hash = :password_hash WHERE id = :id'
                );
                $rehash->execute([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $accountId,
                ]);
            } catch (Throwable $exception) {
                // Se conserva el hash válido actual.
            }
        }

        session_regenerate_id(true);

        $_SESSION['company_portal_account'] = [
            'id' => $accountId,
            'company_id' => $companyId,
            'name' => (string) ($account['name'] ?? ''),
            'email' => (string) ($account['email'] ?? ''),
            'is_primary' => (int) ($account['is_primary'] ?? 0),
            'force_password_change' => (int) ($account['force_password_change'] ?? 0),
            'business_name' => (string) ($account['business_name'] ?? ''),
            'trade_name' => (string) ($account['trade_name'] ?? ''),
            'ruc' => (string) ($account['ruc'] ?? ''),
            'logo_path' => $account['logo_path'] ?? null,
            'sla_contract_type' => (string) ($account['sla_contract_type'] ?? '8_5'),
        ];

        $token = companyPortalCreateSessionRecord($this->pdo, $accountId, $settings);
        if ($token !== null) {
            $_SESSION['company_portal_session_token'] = $token;
        }

        $_SESSION['company_portal_started_at'] = time();
        $_SESSION['company_portal_last_activity_at'] = time();
        $_SESSION['company_portal_db_touch_at'] = time();

        companyPortalAudit(
            $this->pdo,
            'LOGIN_SUCCESS',
            'Inicio de sesión corporativo correcto.',
            $companyId,
            $accountId,
            'info',
            ['is_primary' => (int) ($account['is_primary'] ?? 0)]
        );

        return [
            'success' => true,
            'force_password_change' => !empty($account['force_password_change']),
        ];
    }

    public function changePassword(string $currentPassword, string $newPassword, string $confirmation): array
    {
        $sessionAccount = companyPortalAccount();
        $accountId = (int) ($sessionAccount['id'] ?? 0);
        $companyId = (int) ($sessionAccount['company_id'] ?? 0);

        if ($accountId <= 0) {
            return ['success' => false, 'message' => 'La sesión corporativa no es válida.'];
        }

        $statement = $this->pdo->prepare(
            'SELECT id, name, email, password_hash FROM company_portal_accounts WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $accountId]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$account || !password_verify($currentPassword, (string) $account['password_hash'])) {
            companyPortalAudit(
                $this->pdo,
                'PASSWORD_CHANGE_FAILED',
                'No se pudo cambiar la contraseña porque la contraseña actual no coincidía.',
                $companyId,
                $accountId,
                'warning'
            );
            return ['success' => false, 'message' => 'La contraseña actual no es correcta.'];
        }

        if ($newPassword !== $confirmation) {
            return ['success' => false, 'message' => 'La nueva contraseña y su confirmación no coinciden.'];
        }

        if (password_verify($newPassword, (string) $account['password_hash'])) {
            return ['success' => false, 'message' => 'La nueva contraseña debe ser diferente a la actual.'];
        }

        $settings = getSystemSecuritySettings($this->pdo);
        $errors = systemSecurityPasswordErrors($newPassword, $settings, [
            'name' => (string) ($account['name'] ?? ''),
            'email' => (string) ($account['email'] ?? ''),
        ]);

        if ($errors) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        $currentToken = trim((string) ($_SESSION['company_portal_session_token'] ?? ''));

        try {
            $this->pdo->beginTransaction();

            $update = $this->pdo->prepare(
                "UPDATE company_portal_accounts
                 SET password_hash = :password_hash,
                     force_password_change = 0,
                     password_changed_at = NOW(),
                     failed_login_attempts = 0,
                     failed_login_at = NULL,
                     locked_until = NULL
                 WHERE id = :id"
            );
            $update->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $accountId,
            ]);

            if (!empty($settings['invalidate_sessions_on_password_change'])) {
                $sql = "UPDATE company_portal_sessions
                        SET revoked_at = NOW(), revoke_reason = 'Contraseña actualizada'
                        WHERE account_id = :account_id
                          AND revoked_at IS NULL";
                $params = ['account_id' => $accountId];

                if ($currentToken !== '') {
                    $sql .= ' AND session_token <> :current_token';
                    $params['current_token'] = $currentToken;
                }

                $revoke = $this->pdo->prepare($sql);
                $revoke->execute($params);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => 'No se pudo actualizar la contraseña. Inténtalo nuevamente.'];
        }

        $_SESSION['company_portal_account']['force_password_change'] = 0;

        companyPortalAudit(
            $this->pdo,
            'PASSWORD_CHANGED',
            'La contraseña de la cuenta corporativa fue actualizada.',
            $companyId,
            $accountId,
            'info'
        );

        return ['success' => true, 'message' => 'La contraseña fue actualizada correctamente.'];
    }

    public function logout(string $reason = 'Cierre de sesión voluntario'): void
    {
        $account = companyPortalAccount();
        $accountId = (int) ($account['id'] ?? 0);
        $companyId = (int) ($account['company_id'] ?? 0);

        if ($accountId > 0) {
            companyPortalRevokeCurrentSession($this->pdo, $reason);
            companyPortalAudit(
                $this->pdo,
                'LOGOUT',
                $reason,
                $companyId,
                $accountId,
                'info'
            );
        }

        companyPortalDestroyLocalSession();
    }
}
