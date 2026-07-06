<?php

/**
 * Autenticación persistente mediante selector + validador.
 *
 * - La cookie contiene un selector público y un validador secreto.
 * - La base de datos almacena únicamente el hash SHA-256 del validador.
 * - El validador rota cada vez que la cookie se utiliza correctamente.
 * - El token deja de funcionar automáticamente si cambia la contraseña.
 */

if (!defined('AUTH_REMEMBER_COOKIE')) {
    define('AUTH_REMEMBER_COOKIE', 'PRONET_REMEMBER');
}

if (!defined('COMPANY_REMEMBER_COOKIE')) {
    define('COMPANY_REMEMBER_COOKIE', 'PRONET_COMPANY_REMEMBER');
}

if (!defined('REMEMBER_ME_TTL_DAYS')) {
    define('REMEMBER_ME_TTL_DAYS', 14);
}

if (!function_exists('rememberMeIsHttps')) {
    function rememberMeIsHttps(): bool
    {
        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        return $forwardedProto === 'https';
    }
}

if (!function_exists('rememberMeCookieOptions')) {
    function rememberMeCookieOptions(int $expiresAt): array
    {
        return [
            'expires' => $expiresAt,
            'path' => '/helpdesk-php/',
            'domain' => '',
            'secure' => rememberMeIsHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('rememberMeSetCookie')) {
    function rememberMeSetCookie(string $name, string $value, int $expiresAt): void
    {
        setcookie($name, $value, rememberMeCookieOptions($expiresAt));
        $_COOKIE[$name] = $value;
    }
}

if (!function_exists('rememberMeClearCookie')) {
    function rememberMeClearCookie(string $name): void
    {
        setcookie($name, '', rememberMeCookieOptions(time() - 3600));
        unset($_COOKIE[$name]);
    }
}

if (!function_exists('rememberMeParseCookie')) {
    function rememberMeParseCookie(string $name): ?array
    {
        $raw = trim((string) ($_COOKIE[$name] ?? ''));
        if ($raw === '') {
            return null;
        }

        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) {
            rememberMeClearCookie($name);
            return null;
        }

        [$selector, $validator] = $parts;
        if (!preg_match('/^[a-f0-9]{48}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            rememberMeClearCookie($name);
            return null;
        }

        return [
            'selector' => $selector,
            'validator' => $validator,
        ];
    }
}

if (!function_exists('rememberMeTableExists')) {
    function rememberMeTableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        $key = spl_object_id($pdo) . ':' . $table;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $statement->execute(['table' => $table]);
            $cache[$key] = (int) $statement->fetchColumn() > 0;
        } catch (Throwable $exception) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('rememberMeCredentialFingerprint')) {
    function rememberMeCredentialFingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }
}

if (!function_exists('rememberMeUserAgentHash')) {
    function rememberMeUserAgentHash(): string
    {
        return hash('sha256', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
    }
}

if (!function_exists('rememberMeClientIp')) {
    function rememberMeClientIp(): string
    {
        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $candidate = trim(explode(',', $forwarded)[0] ?? '');
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
    }
}

if (!function_exists('rememberMeCleanup')) {
    function rememberMeCleanup(PDO $pdo, string $table): void
    {
        if (!rememberMeTableExists($pdo, $table)) {
            return;
        }

        try {
            $pdo->exec(
                "DELETE FROM `{$table}`
                 WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                    OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY))"
            );
        } catch (Throwable $exception) {
            // La limpieza nunca debe interrumpir la autenticación.
        }
    }
}

if (!function_exists('rememberMeRevokeSelector')) {
    function rememberMeRevokeSelector(PDO $pdo, string $table, string $selector, string $reason): void
    {
        if ($selector === '' || !rememberMeTableExists($pdo, $table)) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                "UPDATE `{$table}`
                 SET revoked_at = COALESCE(revoked_at, NOW()), revoke_reason = :reason
                 WHERE selector = :selector"
            );
            $statement->execute([
                'reason' => substr($reason, 0, 120),
                'selector' => $selector,
            ]);
        } catch (Throwable $exception) {
            // La cookie local igualmente será eliminada.
        }
    }
}

if (!function_exists('authRememberForgetCurrent')) {
    function authRememberForgetCurrent(PDO $pdo, string $reason = 'Recordatorio desactivado'): void
    {
        $parts = rememberMeParseCookie(AUTH_REMEMBER_COOKIE);
        if ($parts) {
            rememberMeRevokeSelector($pdo, 'user_remember_tokens', $parts['selector'], $reason);
        }
        rememberMeClearCookie(AUTH_REMEMBER_COOKIE);
    }
}

if (!function_exists('companyRememberForgetCurrent')) {
    function companyRememberForgetCurrent(PDO $pdo, string $reason = 'Recordatorio corporativo desactivado'): void
    {
        $parts = rememberMeParseCookie(COMPANY_REMEMBER_COOKIE);
        if ($parts) {
            rememberMeRevokeSelector($pdo, 'company_portal_remember_tokens', $parts['selector'], $reason);
        }
        rememberMeClearCookie(COMPANY_REMEMBER_COOKIE);
    }
}

if (!function_exists('authRememberIssueForUser')) {
    function authRememberIssueForUser(PDO $pdo, int $userId, string $passwordHash): bool
    {
        if ($userId <= 0 || $passwordHash === '' || !rememberMeTableExists($pdo, 'user_remember_tokens')) {
            return false;
        }

        authRememberForgetCurrent($pdo, 'Reemplazado por un nuevo recordatorio');
        rememberMeCleanup($pdo, 'user_remember_tokens');

        try {
            $selector = bin2hex(random_bytes(24));
            $validator = bin2hex(random_bytes(32));
            $expiresAtUnix = time() + (REMEMBER_ME_TTL_DAYS * 86400);
            $expiresAtSql = date('Y-m-d H:i:s', $expiresAtUnix);

            $statement = $pdo->prepare(
                "INSERT INTO user_remember_tokens
                    (user_id, selector, validator_hash, credential_fingerprint, user_agent_hash, ip_address, created_at, last_used_at, expires_at)
                 VALUES
                    (:user_id, :selector, :validator_hash, :credential_fingerprint, :user_agent_hash, :ip_address, NOW(), NOW(), :expires_at)"
            );
            $statement->execute([
                'user_id' => $userId,
                'selector' => $selector,
                'validator_hash' => hash('sha256', $validator),
                'credential_fingerprint' => rememberMeCredentialFingerprint($passwordHash),
                'user_agent_hash' => rememberMeUserAgentHash(),
                'ip_address' => rememberMeClientIp() ?: null,
                'expires_at' => $expiresAtSql,
            ]);

            rememberMeSetCookie(AUTH_REMEMBER_COOKIE, $selector . '.' . $validator, $expiresAtUnix);
            return true;
        } catch (Throwable $exception) {
            rememberMeClearCookie(AUTH_REMEMBER_COOKIE);
            return false;
        }
    }
}

if (!function_exists('companyRememberIssueForAccount')) {
    function companyRememberIssueForAccount(PDO $pdo, int $accountId, string $passwordHash): bool
    {
        if ($accountId <= 0 || $passwordHash === '' || !rememberMeTableExists($pdo, 'company_portal_remember_tokens')) {
            return false;
        }

        companyRememberForgetCurrent($pdo, 'Reemplazado por un nuevo recordatorio corporativo');
        rememberMeCleanup($pdo, 'company_portal_remember_tokens');

        try {
            $selector = bin2hex(random_bytes(24));
            $validator = bin2hex(random_bytes(32));
            $expiresAtUnix = time() + (REMEMBER_ME_TTL_DAYS * 86400);
            $expiresAtSql = date('Y-m-d H:i:s', $expiresAtUnix);

            $statement = $pdo->prepare(
                "INSERT INTO company_portal_remember_tokens
                    (account_id, selector, validator_hash, credential_fingerprint, user_agent_hash, ip_address, created_at, last_used_at, expires_at)
                 VALUES
                    (:account_id, :selector, :validator_hash, :credential_fingerprint, :user_agent_hash, :ip_address, NOW(), NOW(), :expires_at)"
            );
            $statement->execute([
                'account_id' => $accountId,
                'selector' => $selector,
                'validator_hash' => hash('sha256', $validator),
                'credential_fingerprint' => rememberMeCredentialFingerprint($passwordHash),
                'user_agent_hash' => rememberMeUserAgentHash(),
                'ip_address' => rememberMeClientIp() ?: null,
                'expires_at' => $expiresAtSql,
            ]);

            rememberMeSetCookie(COMPANY_REMEMBER_COOKIE, $selector . '.' . $validator, $expiresAtUnix);
            return true;
        } catch (Throwable $exception) {
            rememberMeClearCookie(COMPANY_REMEMBER_COOKIE);
            return false;
        }
    }
}

if (!function_exists('authRememberAttempt')) {
    function authRememberAttempt(PDO $pdo): bool
    {
        if (!empty($_SESSION['user']) || !rememberMeTableExists($pdo, 'user_remember_tokens')) {
            return !empty($_SESSION['user']);
        }

        $parts = rememberMeParseCookie(AUTH_REMEMBER_COOKIE);
        if (!$parts) {
            return false;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT t.*, u.id AS account_id, u.name, u.email, u.password, u.role, u.status,
                        u.profile_photo, u.force_password_change, u.password_changed_at
                 FROM user_remember_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.selector = :selector
                 LIMIT 1"
            );
            $statement->execute(['selector' => $parts['selector']]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);

            $now = time();
            $expiresAt = $record ? strtotime((string) ($record['expires_at'] ?? '')) : false;
            $invalid = !$record
                || !empty($record['revoked_at'])
                || $expiresAt === false
                || $expiresAt <= $now
                || (int) ($record['status'] ?? 0) !== 1
                || !hash_equals((string) ($record['validator_hash'] ?? ''), hash('sha256', $parts['validator']))
                || !hash_equals((string) ($record['credential_fingerprint'] ?? ''), rememberMeCredentialFingerprint((string) ($record['password'] ?? '')))
                || !hash_equals((string) ($record['user_agent_hash'] ?? ''), rememberMeUserAgentHash());

            if ($invalid) {
                rememberMeRevokeSelector($pdo, 'user_remember_tokens', $parts['selector'], 'Token persistente no válido');
                rememberMeClearCookie(AUTH_REMEMBER_COOKIE);
                return false;
            }

            $userId = (int) $record['account_id'];
            $settings = getSystemSecuritySettings($pdo);
            $forcePasswordChange = !empty($record['force_password_change'])
                || systemSecurityPasswordExpired($record, $settings);

            $newValidator = bin2hex(random_bytes(32));
            $rotate = $pdo->prepare(
                "UPDATE user_remember_tokens
                 SET validator_hash = :validator_hash,
                     last_used_at = NOW(),
                     ip_address = :ip_address
                 WHERE id = :id AND revoked_at IS NULL"
            );
            $rotate->execute([
                'validator_hash' => hash('sha256', $newValidator),
                'ip_address' => rememberMeClientIp() ?: null,
                'id' => (int) $record['id'],
            ]);

            if ($rotate->rowCount() !== 1) {
                rememberMeClearCookie(AUTH_REMEMBER_COOKIE);
                return false;
            }

            rememberMeSetCookie(AUTH_REMEMBER_COOKIE, $parts['selector'] . '.' . $newValidator, (int) $expiresAt);

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $userId,
                'name' => (string) ($record['name'] ?? ''),
                'email' => (string) ($record['email'] ?? ''),
                'role' => (string) ($record['role'] ?? ''),
                'status' => (int) ($record['status'] ?? 1),
                'profile_photo' => $record['profile_photo'] ?? null,
                'force_password_change' => $forcePasswordChange ? 1 : 0,
            ];

            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = (string) ($record['name'] ?? '');
            $_SESSION['user_email'] = (string) ($record['email'] ?? '');
            $_SESSION['user_role'] = (string) ($record['role'] ?? '');

            $sessionToken = systemSecurityCreateSession($pdo, $userId, $settings);
            if ($sessionToken !== null) {
                $_SESSION['security_session_token'] = $sessionToken;
            }

            $_SESSION['security_session_started_at'] = $now;
            $_SESSION['security_last_activity_at'] = $now;
            $_SESSION['security_db_touch_at'] = $now;

            try {
                $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $update->execute(['id' => $userId]);
            } catch (Throwable $exception) {
                // El acceso persistente no depende de la telemetría.
            }

            systemSecurityAudit(
                $pdo,
                'REMEMBER_LOGIN_SUCCESS',
                'La sesión fue restaurada mediante un dispositivo recordado.',
                $userId,
                $userId,
                'info',
                ['role' => (string) ($record['role'] ?? '')]
            );

            return true;
        } catch (Throwable $exception) {
            rememberMeClearCookie(AUTH_REMEMBER_COOKIE);
            return false;
        }
    }
}

if (!function_exists('companyRememberAttempt')) {
    function companyRememberAttempt(PDO $pdo): bool
    {
        if (!empty($_SESSION['company_portal_account']) || !rememberMeTableExists($pdo, 'company_portal_remember_tokens')) {
            return !empty($_SESSION['company_portal_account']);
        }

        $parts = rememberMeParseCookie(COMPANY_REMEMBER_COOKIE);
        if (!$parts) {
            return false;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT t.*, a.id AS portal_account_id, a.company_id, a.name, a.email, a.password_hash,
                        a.is_primary, a.status, a.force_password_change,
                        c.business_name, c.trade_name, c.ruc, c.logo_path, c.sla_contract_type,
                        c.status AS company_status
                 FROM company_portal_remember_tokens t
                 INNER JOIN company_portal_accounts a ON a.id = t.account_id
                 INNER JOIN client_companies c ON c.id = a.company_id
                 WHERE t.selector = :selector
                 LIMIT 1"
            );
            $statement->execute(['selector' => $parts['selector']]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);

            $now = time();
            $expiresAt = $record ? strtotime((string) ($record['expires_at'] ?? '')) : false;
            $invalid = !$record
                || !empty($record['revoked_at'])
                || $expiresAt === false
                || $expiresAt <= $now
                || (int) ($record['status'] ?? 0) !== 1
                || (int) ($record['company_status'] ?? 0) !== 1
                || !hash_equals((string) ($record['validator_hash'] ?? ''), hash('sha256', $parts['validator']))
                || !hash_equals((string) ($record['credential_fingerprint'] ?? ''), rememberMeCredentialFingerprint((string) ($record['password_hash'] ?? '')))
                || !hash_equals((string) ($record['user_agent_hash'] ?? ''), rememberMeUserAgentHash());

            if ($invalid) {
                rememberMeRevokeSelector($pdo, 'company_portal_remember_tokens', $parts['selector'], 'Token corporativo persistente no válido');
                rememberMeClearCookie(COMPANY_REMEMBER_COOKIE);
                return false;
            }

            $accountId = (int) $record['portal_account_id'];
            $companyId = (int) $record['company_id'];
            $settings = getSystemSecuritySettings($pdo);

            $newValidator = bin2hex(random_bytes(32));
            $rotate = $pdo->prepare(
                "UPDATE company_portal_remember_tokens
                 SET validator_hash = :validator_hash,
                     last_used_at = NOW(),
                     ip_address = :ip_address
                 WHERE id = :id AND revoked_at IS NULL"
            );
            $rotate->execute([
                'validator_hash' => hash('sha256', $newValidator),
                'ip_address' => rememberMeClientIp() ?: null,
                'id' => (int) $record['id'],
            ]);

            if ($rotate->rowCount() !== 1) {
                rememberMeClearCookie(COMPANY_REMEMBER_COOKIE);
                return false;
            }

            rememberMeSetCookie(COMPANY_REMEMBER_COOKIE, $parts['selector'] . '.' . $newValidator, (int) $expiresAt);

            session_regenerate_id(true);

            $_SESSION['company_portal_account'] = [
                'id' => $accountId,
                'company_id' => $companyId,
                'name' => (string) ($record['name'] ?? ''),
                'email' => (string) ($record['email'] ?? ''),
                'is_primary' => (int) ($record['is_primary'] ?? 0),
                'force_password_change' => (int) ($record['force_password_change'] ?? 0),
                'business_name' => (string) ($record['business_name'] ?? ''),
                'trade_name' => (string) ($record['trade_name'] ?? ''),
                'ruc' => (string) ($record['ruc'] ?? ''),
                'logo_path' => $record['logo_path'] ?? null,
                'sla_contract_type' => (string) ($record['sla_contract_type'] ?? '8_5'),
            ];

            $sessionToken = companyPortalCreateSessionRecord($pdo, $accountId, $settings);
            if ($sessionToken !== null) {
                $_SESSION['company_portal_session_token'] = $sessionToken;
            }

            $_SESSION['company_portal_started_at'] = $now;
            $_SESSION['company_portal_last_activity_at'] = $now;
            $_SESSION['company_portal_db_touch_at'] = $now;

            try {
                $update = $pdo->prepare(
                    'UPDATE company_portal_accounts SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id'
                );
                $update->execute([
                    'ip' => rememberMeClientIp() ?: null,
                    'id' => $accountId,
                ]);
            } catch (Throwable $exception) {
                // El acceso persistente no depende de la telemetría.
            }

            companyPortalAudit(
                $pdo,
                'REMEMBER_LOGIN_SUCCESS',
                'La sesión corporativa fue restaurada mediante un dispositivo recordado.',
                $companyId,
                $accountId,
                'info'
            );

            return true;
        } catch (Throwable $exception) {
            rememberMeClearCookie(COMPANY_REMEMBER_COOKIE);
            return false;
        }
    }
}
