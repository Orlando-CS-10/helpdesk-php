<?php

require_once __DIR__ . '/company_portal_session.php';
require_once __DIR__ . '/system_security.php';

if (!function_exists('companyPortalTableExists')) {
    function companyPortalTableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        $key = spl_object_id($pdo) . ':' . $table;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $statement = $pdo->prepare('SHOW TABLES LIKE :table_name');
            $statement->execute(['table_name' => $table]);
            $cache[$key] = (bool) $statement->fetch(PDO::FETCH_NUM);
        } catch (Throwable $exception) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('companyPortalModuleReady')) {
    function companyPortalModuleReady(PDO $pdo): bool
    {
        return companyPortalTableExists($pdo, 'company_portal_accounts')
            && companyPortalTableExists($pdo, 'company_portal_sessions')
            && companyPortalTableExists($pdo, 'company_portal_audit_logs');
    }
}

if (!function_exists('companyPortalIsLoggedIn')) {
    function companyPortalIsLoggedIn(): bool
    {
        return isset($_SESSION['company_portal_account'])
            && is_array($_SESSION['company_portal_account']);
    }
}

if (!function_exists('companyPortalAccount')) {
    function companyPortalAccount(): ?array
    {
        return companyPortalIsLoggedIn()
            ? $_SESSION['company_portal_account']
            : null;
    }
}

if (!function_exists('companyPortalCompanyId')) {
    function companyPortalCompanyId(): int
    {
        return (int) ($_SESSION['company_portal_account']['company_id'] ?? 0);
    }
}

if (!function_exists('companyPortalCsrfToken')) {
    function companyPortalCsrfToken(string $scope = 'default'): string
    {
        $scope = preg_replace('/[^a-z0-9_\-]/i', '', $scope) ?: 'default';
        $key = 'company_portal_csrf_' . $scope;

        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }
}

if (!function_exists('companyPortalVerifyCsrf')) {
    function companyPortalVerifyCsrf(?string $token, string $scope = 'default'): bool
    {
        $scope = preg_replace('/[^a-z0-9_\-]/i', '', $scope) ?: 'default';
        $stored = $_SESSION['company_portal_csrf_' . $scope] ?? '';

        return is_string($token)
            && is_string($stored)
            && $stored !== ''
            && hash_equals($stored, $token);
    }
}

if (!function_exists('companyPortalClientIp')) {
    function companyPortalClientIp(): string
    {
        return function_exists('systemSecurityClientIp')
            ? systemSecurityClientIp()
            : (filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? (string) $_SERVER['REMOTE_ADDR'] : '');
    }
}

if (!function_exists('companyPortalUserAgent')) {
    function companyPortalUserAgent(): string
    {
        return function_exists('systemSecurityUserAgent')
            ? systemSecurityUserAgent()
            : substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
    }
}

if (!function_exists('companyPortalAudit')) {
    function companyPortalAudit(
        PDO $pdo,
        string $eventType,
        string $description,
        ?int $companyId = null,
        ?int $accountId = null,
        string $severity = 'info',
        array $metadata = []
    ): void {
        if (!companyPortalTableExists($pdo, 'company_portal_audit_logs')) {
            return;
        }

        if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
            $severity = 'info';
        }

        try {
            $statement = $pdo->prepare(
                "INSERT INTO company_portal_audit_logs
                    (company_id, account_id, event_type, severity, description, ip_address, user_agent, metadata_json, created_at)
                 VALUES
                    (:company_id, :account_id, :event_type, :severity, :description, :ip_address, :user_agent, :metadata_json, NOW())"
            );
            $statement->execute([
                'company_id' => $companyId ?: null,
                'account_id' => $accountId ?: null,
                'event_type' => substr($eventType, 0, 80),
                'severity' => $severity,
                'description' => substr($description, 0, 255),
                'ip_address' => companyPortalClientIp() ?: null,
                'user_agent' => companyPortalUserAgent() ?: null,
                'metadata_json' => $metadata
                    ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
            ]);
        } catch (Throwable $exception) {
            // La auditoría nunca debe interrumpir el acceso al portal.
        }
    }
}

if (!function_exists('companyPortalCreateSessionRecord')) {
    function companyPortalCreateSessionRecord(PDO $pdo, int $accountId, array $settings): ?string
    {
        if ($accountId <= 0 || !companyPortalTableExists($pdo, 'company_portal_sessions')) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $maxHours = max(1, min(168, (int) ($settings['session_max_hours'] ?? 12)));
        $expiresAt = date('Y-m-d H:i:s', time() + ($maxHours * 3600));

        try {
            if (!empty($settings['single_session'])) {
                $revoke = $pdo->prepare(
                    "UPDATE company_portal_sessions
                     SET revoked_at = NOW(), revoke_reason = 'Nueva sesión iniciada'
                     WHERE account_id = :account_id AND revoked_at IS NULL"
                );
                $revoke->execute(['account_id' => $accountId]);
            }

            $statement = $pdo->prepare(
                "INSERT INTO company_portal_sessions
                    (account_id, session_token, php_session_hash, ip_address, user_agent, device_label, created_at, last_activity_at, expires_at)
                 VALUES
                    (:account_id, :session_token, :php_session_hash, :ip_address, :user_agent, :device_label, NOW(), NOW(), :expires_at)"
            );
            $statement->execute([
                'account_id' => $accountId,
                'session_token' => $token,
                'php_session_hash' => hash('sha256', session_id()),
                'ip_address' => companyPortalClientIp() ?: null,
                'user_agent' => companyPortalUserAgent() ?: null,
                'device_label' => function_exists('systemSecurityDeviceLabel')
                    ? systemSecurityDeviceLabel()
                    : 'Dispositivo',
                'expires_at' => $expiresAt,
            ]);

            return $token;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('companyPortalRevokeCurrentSession')) {
    function companyPortalRevokeCurrentSession(PDO $pdo, string $reason = 'Cierre de sesión'): void
    {
        $token = trim((string) ($_SESSION['company_portal_session_token'] ?? ''));

        if ($token === '' || !companyPortalTableExists($pdo, 'company_portal_sessions')) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                "UPDATE company_portal_sessions
                 SET revoked_at = COALESCE(revoked_at, NOW()), revoke_reason = :reason
                 WHERE session_token = :token"
            );
            $statement->execute([
                'reason' => substr($reason, 0, 120),
                'token' => $token,
            ]);
        } catch (Throwable $exception) {
            // El cierre local continuará.
        }
    }
}

if (!function_exists('companyPortalDestroyLocalSession')) {
    function companyPortalDestroyLocalSession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/helpdesk-php/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('companyPortalFreshAccount')) {
    function companyPortalFreshAccount(PDO $pdo, int $accountId): ?array
    {
        if ($accountId <= 0 || !companyPortalModuleReady($pdo)) {
            return null;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT
                    a.id,
                    a.company_id,
                    a.name,
                    a.email,
                    a.is_primary,
                    a.status,
                    a.force_password_change,
                    a.password_changed_at,
                    a.last_login_at,
                    c.business_name,
                    c.trade_name,
                    c.ruc,
                    c.email AS company_email,
                    c.phone AS company_phone,
                    c.fiscal_address,
                    c.logo_path,
                    c.sla_contract_type,
                    c.sla_profile_id,
                    c.status AS company_status
                 FROM company_portal_accounts a
                 INNER JOIN client_companies c ON c.id = a.company_id
                 WHERE a.id = :account_id
                 LIMIT 1"
            );
            $statement->execute(['account_id' => $accountId]);
            $account = $statement->fetch(PDO::FETCH_ASSOC);
            return $account ?: null;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('companyPortalEnforceSession')) {
    function companyPortalEnforceSession(PDO $pdo): array
    {
        if (!companyPortalIsLoggedIn()) {
            return ['valid' => false, 'reason' => 'not_authenticated'];
        }

        if (!companyPortalModuleReady($pdo)) {
            return ['valid' => false, 'reason' => 'module_unavailable'];
        }

        $accountId = (int) ($_SESSION['company_portal_account']['id'] ?? 0);
        $account = companyPortalFreshAccount($pdo, $accountId);

        if (!$account) {
            companyPortalDestroyLocalSession();
            return ['valid' => false, 'reason' => 'account_not_found'];
        }

        if ((int) ($account['status'] ?? 0) !== 1 || (int) ($account['company_status'] ?? 0) !== 1) {
            companyPortalRevokeCurrentSession($pdo, 'Cuenta o empresa desactivada');
            companyPortalAudit(
                $pdo,
                'SESSION_REVOKED_INACTIVE_ACCOUNT',
                'Se cerró una sesión porque la cuenta corporativa o la empresa está inactiva.',
                (int) $account['company_id'],
                $accountId,
                'warning'
            );
            companyPortalDestroyLocalSession();
            return ['valid' => false, 'reason' => 'inactive_account'];
        }

        $settings = getSystemSecuritySettings($pdo);
        $now = time();
        $startedAt = (int) ($_SESSION['company_portal_started_at'] ?? $now);
        $lastActivityAt = (int) ($_SESSION['company_portal_last_activity_at'] ?? $now);
        $idleMinutes = max(5, min(1440, (int) ($settings['session_idle_minutes'] ?? 30)));
        $maxHours = max(1, min(168, (int) ($settings['session_max_hours'] ?? 12)));

        if (($now - $lastActivityAt) > ($idleMinutes * 60)) {
            companyPortalRevokeCurrentSession($pdo, 'Sesión vencida por inactividad');
            companyPortalAudit($pdo, 'SESSION_EXPIRED_IDLE', 'La sesión corporativa venció por inactividad.', (int) $account['company_id'], $accountId);
            companyPortalDestroyLocalSession();
            return ['valid' => false, 'reason' => 'idle_timeout'];
        }

        if (($now - $startedAt) > ($maxHours * 3600)) {
            companyPortalRevokeCurrentSession($pdo, 'Duración máxima alcanzada');
            companyPortalAudit($pdo, 'SESSION_EXPIRED_MAX_DURATION', 'La sesión corporativa alcanzó su duración máxima.', (int) $account['company_id'], $accountId);
            companyPortalDestroyLocalSession();
            return ['valid' => false, 'reason' => 'absolute_timeout'];
        }

        $token = trim((string) ($_SESSION['company_portal_session_token'] ?? ''));
        if ($token === '') {
            $token = companyPortalCreateSessionRecord($pdo, $accountId, $settings) ?? '';
            if ($token !== '') {
                $_SESSION['company_portal_session_token'] = $token;
            }
        }

        if ($token !== '') {
            try {
                $statement = $pdo->prepare(
                    "SELECT * FROM company_portal_sessions
                     WHERE session_token = :token AND account_id = :account_id
                     LIMIT 1"
                );
                $statement->execute(['token' => $token, 'account_id' => $accountId]);
                $sessionRecord = $statement->fetch(PDO::FETCH_ASSOC);

                if (!$sessionRecord || !empty($sessionRecord['revoked_at'])) {
                    companyPortalDestroyLocalSession();
                    return ['valid' => false, 'reason' => 'revoked'];
                }

                $phpSessionHash = trim((string) ($sessionRecord['php_session_hash'] ?? ''));
                if ($phpSessionHash !== '' && !hash_equals($phpSessionHash, hash('sha256', session_id()))) {
                    companyPortalRevokeCurrentSession($pdo, 'Identificador de sesión no válido');
                    companyPortalDestroyLocalSession();
                    return ['valid' => false, 'reason' => 'session_mismatch'];
                }

                $expiresAt = trim((string) ($sessionRecord['expires_at'] ?? ''));
                if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= $now) {
                    companyPortalRevokeCurrentSession($pdo, 'Sesión expirada');
                    companyPortalDestroyLocalSession();
                    return ['valid' => false, 'reason' => 'expired'];
                }

                $lastTouch = (int) ($_SESSION['company_portal_db_touch_at'] ?? 0);
                if ($lastTouch <= 0 || ($now - $lastTouch) >= 60) {
                    $touch = $pdo->prepare(
                        "UPDATE company_portal_sessions
                         SET last_activity_at = NOW()
                         WHERE session_token = :token AND revoked_at IS NULL"
                    );
                    $touch->execute(['token' => $token]);
                    $_SESSION['company_portal_db_touch_at'] = $now;
                }
            } catch (Throwable $exception) {
                // La navegación continúa si la telemetría falla temporalmente.
            }
        }

        $_SESSION['company_portal_account'] = [
            'id' => (int) $account['id'],
            'company_id' => (int) $account['company_id'],
            'name' => (string) $account['name'],
            'email' => (string) $account['email'],
            'is_primary' => (int) $account['is_primary'],
            'force_password_change' => (int) $account['force_password_change'],
            'business_name' => (string) $account['business_name'],
            'trade_name' => (string) ($account['trade_name'] ?? ''),
            'ruc' => (string) ($account['ruc'] ?? ''),
            'logo_path' => $account['logo_path'] ?? null,
            'sla_contract_type' => (string) ($account['sla_contract_type'] ?? '8_5'),
        ];
        $_SESSION['company_portal_last_activity_at'] = $now;

        return [
            'valid' => true,
            'reason' => 'ok',
            'force_password_change' => !empty($account['force_password_change']),
            'account' => $account,
        ];
    }
}

if (!function_exists('companyPortalRequireLogin')) {
    function companyPortalRequireLogin(PDO $pdo): array
    {
        $result = companyPortalEnforceSession($pdo);

        if (empty($result['valid'])) {
            $reason = rawurlencode((string) ($result['reason'] ?? 'expired'));
            header('Location: /helpdesk-php/company-login.php?reason=' . $reason);
            exit;
        }

        $currentScript = basename((string) parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH));
        $passwordAllowed = ['company-change-password.php', 'company-logout.php'];

        if (!empty($result['force_password_change']) && !in_array($currentScript, $passwordAllowed, true)) {
            header('Location: /helpdesk-php/company-change-password.php');
            exit;
        }

        return $result;
    }
}

if (!function_exists('companyPortalLogoUrl')) {
    function companyPortalLogoUrl(?string $logoPath): ?string
    {
        $logoPath = trim((string) $logoPath);
        if ($logoPath === '') {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $logoPath)) {
            return $logoPath;
        }
        if (str_starts_with($logoPath, '/')) {
            return $logoPath;
        }
        return '/helpdesk-php/' . ltrim($logoPath, '/');
    }
}

if (!function_exists('companyPortalDisplayName')) {
    function companyPortalDisplayName(array $account): string
    {
        $tradeName = trim((string) ($account['trade_name'] ?? ''));
        return $tradeName !== '' ? $tradeName : trim((string) ($account['business_name'] ?? 'Empresa'));
    }
}
