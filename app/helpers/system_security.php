<?php

/**
 * Funciones compartidas de Seguridad del sistema.
 * El módulo usa un único registro de configuración con id = 1.
 */

if (!function_exists('systemSecurityTableExists')) {
    function systemSecurityTableExists(PDO $pdo, string $table): bool
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

if (!function_exists('systemSecurityColumnExists')) {
    function systemSecurityColumnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = spl_object_id($pdo) . ':' . $table . ':' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $statement = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
            $statement->execute(['column_name' => $column]);
            $cache[$key] = (bool) $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('systemSecurityReady')) {
    function systemSecurityReady(PDO $pdo): bool
    {
        return systemSecurityTableExists($pdo, 'system_security_settings')
            && systemSecurityTableExists($pdo, 'security_audit_logs')
            && systemSecurityTableExists($pdo, 'user_sessions');
    }
}

if (!function_exists('systemSecurityDefaults')) {
    function systemSecurityDefaults(): array
    {
        return [
            'id' => 1,
            'min_password_length' => 8,
            'require_uppercase' => 1,
            'require_lowercase' => 1,
            'require_number' => 1,
            'require_special' => 1,
            'block_common_passwords' => 1,
            'force_change_on_create' => 1,
            'password_expiry_days' => 0,
            'max_failed_attempts' => 5,
            'lockout_minutes' => 15,
            'failed_attempt_reset_minutes' => 30,
            'session_idle_minutes' => 30,
            'session_max_hours' => 12,
            'single_session' => 0,
            'invalidate_sessions_on_password_change' => 1,
            'block_inactive_users' => 1,
            'audit_enabled' => 1,
            'updated_by' => null,
            'updated_by_name' => '',
            'created_at' => null,
            'updated_at' => null,
        ];
    }
}

if (!function_exists('getSystemSecuritySettings')) {
    function getSystemSecuritySettings(PDO $pdo): array
    {
        $defaults = systemSecurityDefaults();

        if (!systemSecurityTableExists($pdo, 'system_security_settings')) {
            return $defaults;
        }

        try {
            $statement = $pdo->query(
                "SELECT s.*, u.name AS updated_by_name
                 FROM system_security_settings s
                 LEFT JOIN users u ON u.id = s.updated_by
                 WHERE s.id = 1
                 LIMIT 1"
            );
            $settings = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : false;

            return $settings ? array_merge($defaults, $settings) : $defaults;
        } catch (Throwable $exception) {
            return $defaults;
        }
    }
}

if (!function_exists('systemSecurityCsrfToken')) {
    function systemSecurityCsrfToken(string $scope = 'settings'): string
    {
        $key = 'system_security_csrf_' . preg_replace('/[^a-z0-9_\-]/i', '', $scope);

        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }
}

if (!function_exists('systemSecurityVerifyCsrf')) {
    function systemSecurityVerifyCsrf(?string $token, string $scope = 'settings'): bool
    {
        $key = 'system_security_csrf_' . preg_replace('/[^a-z0-9_\-]/i', '', $scope);
        $stored = $_SESSION[$key] ?? '';

        return is_string($token)
            && is_string($stored)
            && $stored !== ''
            && hash_equals($stored, $token);
    }
}

if (!function_exists('systemSecurityPasswordLength')) {
    function systemSecurityPasswordLength(string $password): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($password, 'UTF-8')
            : strlen($password);
    }
}

if (!function_exists('systemSecurityCommonPasswords')) {
    function systemSecurityCommonPasswords(): array
    {
        return [
            '123456', '12345678', '123456789', 'password', 'password1', 'qwerty',
            'qwerty123', 'admin', 'admin123', 'abc123', 'letmein', 'welcome',
            'iloveyou', 'contraseña', 'contrasena', 'pronet', 'pronet123',
            'usuario', 'usuario123', 'helpdesk', 'helpdesk123', '000000', '111111',
        ];
    }
}

if (!function_exists('systemSecurityPasswordErrors')) {
    function systemSecurityPasswordErrors(string $password, array $settings, array $context = []): array
    {
        $errors = [];
        $minimum = max(6, min(64, (int) ($settings['min_password_length'] ?? 8)));

        if (systemSecurityPasswordLength($password) < $minimum) {
            $errors[] = "La contraseña debe tener al menos {$minimum} caracteres.";
        }

        if (!empty($settings['require_uppercase']) && !preg_match('/[A-ZÁÉÍÓÚÑ]/u', $password)) {
            $errors[] = 'Debe incluir al menos una letra mayúscula.';
        }

        if (!empty($settings['require_lowercase']) && !preg_match('/[a-záéíóúñ]/u', $password)) {
            $errors[] = 'Debe incluir al menos una letra minúscula.';
        }

        if (!empty($settings['require_number']) && !preg_match('/\d/u', $password)) {
            $errors[] = 'Debe incluir al menos un número.';
        }

        if (!empty($settings['require_special']) && !preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
            $errors[] = 'Debe incluir al menos un carácter especial.';
        }

        if (!empty($settings['block_common_passwords'])) {
            $normalized = function_exists('mb_strtolower')
                ? mb_strtolower(trim($password), 'UTF-8')
                : strtolower(trim($password));

            if (in_array($normalized, systemSecurityCommonPasswords(), true)) {
                $errors[] = 'La contraseña elegida es demasiado común.';
            }
        }

        foreach (['email', 'name'] as $key) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $candidate = $key === 'email' ? strstr($value, '@', true) : $value;
            $candidate = $candidate === false ? $value : $candidate;
            $candidate = preg_replace('/\s+/', '', (string) $candidate);

            if (systemSecurityPasswordLength($candidate) >= 4 && stripos($password, $candidate) !== false) {
                $errors[] = $key === 'email'
                    ? 'La contraseña no debe contener el correo del usuario.'
                    : 'La contraseña no debe contener el nombre del usuario.';
                break;
            }
        }

        return array_values(array_unique($errors));
    }
}

if (!function_exists('systemSecurityPasswordRulesText')) {
    function systemSecurityPasswordRulesText(array $settings): string
    {
        $parts = ['mínimo ' . max(6, (int) ($settings['min_password_length'] ?? 8)) . ' caracteres'];

        if (!empty($settings['require_uppercase'])) {
            $parts[] = 'una mayúscula';
        }
        if (!empty($settings['require_lowercase'])) {
            $parts[] = 'una minúscula';
        }
        if (!empty($settings['require_number'])) {
            $parts[] = 'un número';
        }
        if (!empty($settings['require_special'])) {
            $parts[] = 'un carácter especial';
        }

        return implode(', ', $parts) . '.';
    }
}

if (!function_exists('systemSecurityClientIp')) {
    function systemSecurityClientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}

if (!function_exists('systemSecurityUserAgent')) {
    function systemSecurityUserAgent(): string
    {
        return substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
    }
}

if (!function_exists('systemSecurityDeviceLabel')) {
    function systemSecurityDeviceLabel(?string $userAgent = null): string
    {
        $agent = strtolower($userAgent ?? systemSecurityUserAgent());
        $device = str_contains($agent, 'mobile') ? 'Móvil' : (str_contains($agent, 'tablet') ? 'Tableta' : 'Computadora');
        $browser = 'Navegador';

        if (str_contains($agent, 'edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($agent, 'chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($agent, 'firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($agent, 'safari/') && !str_contains($agent, 'chrome/')) {
            $browser = 'Safari';
        }

        return $device . ' · ' . $browser;
    }
}

if (!function_exists('systemSecurityAudit')) {
    function systemSecurityAudit(
        PDO $pdo,
        string $eventType,
        string $description,
        ?int $userId = null,
        ?int $actorUserId = null,
        string $severity = 'info',
        array $metadata = []
    ): void {
        if (!systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return;
        }

        $settings = getSystemSecuritySettings($pdo);
        if (empty($settings['audit_enabled'])) {
            return;
        }

        $allowedSeverity = ['info', 'warning', 'critical'];
        if (!in_array($severity, $allowedSeverity, true)) {
            $severity = 'info';
        }

        try {
            $statement = $pdo->prepare(
                "INSERT INTO security_audit_logs
                    (user_id, actor_user_id, event_type, severity, description, ip_address, user_agent, metadata_json, created_at)
                 VALUES
                    (:user_id, :actor_user_id, :event_type, :severity, :description, :ip_address, :user_agent, :metadata_json, NOW())"
            );
            $statement->execute([
                'user_id' => $userId ?: null,
                'actor_user_id' => $actorUserId ?: null,
                'event_type' => substr($eventType, 0, 80),
                'severity' => $severity,
                'description' => substr($description, 0, 255),
                'ip_address' => systemSecurityClientIp() ?: null,
                'user_agent' => systemSecurityUserAgent() ?: null,
                'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);
        } catch (Throwable $exception) {
            // La auditoría no debe interrumpir el flujo principal del sistema.
        }
    }
}

if (!function_exists('systemSecurityRevokeUserSessions')) {
    function systemSecurityRevokeUserSessions(PDO $pdo, int $userId, string $reason, ?string $exceptToken = null): int
    {
        if ($userId <= 0 || !systemSecurityTableExists($pdo, 'user_sessions')) {
            return 0;
        }

        $sql = "UPDATE user_sessions
                SET revoked_at = NOW(), revoke_reason = :reason
                WHERE user_id = :user_id
                  AND revoked_at IS NULL";
        $params = [
            'reason' => substr($reason, 0, 120),
            'user_id' => $userId,
        ];

        if ($exceptToken !== null && $exceptToken !== '') {
            $sql .= ' AND session_token <> :except_token';
            $params['except_token'] = $exceptToken;
        }

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }
}

if (!function_exists('systemSecurityCreateSession')) {
    function systemSecurityCreateSession(PDO $pdo, int $userId, array $settings): ?string
    {
        if ($userId <= 0 || !systemSecurityTableExists($pdo, 'user_sessions')) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $maxHours = max(1, min(168, (int) ($settings['session_max_hours'] ?? 12)));
        $expiresAt = date('Y-m-d H:i:s', time() + ($maxHours * 3600));

        try {
            if (!empty($settings['single_session'])) {
                systemSecurityRevokeUserSessions($pdo, $userId, 'Nueva sesión iniciada');
            }

            $statement = $pdo->prepare(
                "INSERT INTO user_sessions
                    (user_id, session_token, php_session_hash, ip_address, user_agent, device_label, created_at, last_activity_at, expires_at)
                 VALUES
                    (:user_id, :session_token, :php_session_hash, :ip_address, :user_agent, :device_label, NOW(), NOW(), :expires_at)"
            );
            $statement->execute([
                'user_id' => $userId,
                'session_token' => $token,
                'php_session_hash' => hash('sha256', session_id()),
                'ip_address' => systemSecurityClientIp() ?: null,
                'user_agent' => systemSecurityUserAgent() ?: null,
                'device_label' => systemSecurityDeviceLabel(),
                'expires_at' => $expiresAt,
            ]);

            return $token;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('systemSecurityEnsureSessionRecord')) {
    function systemSecurityEnsureSessionRecord(PDO $pdo, int $userId, array $settings): ?string
    {
        $token = trim((string) ($_SESSION['security_session_token'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        $token = systemSecurityCreateSession($pdo, $userId, $settings);
        if ($token !== null) {
            $_SESSION['security_session_token'] = $token;
            $_SESSION['security_session_started_at'] = time();
            $_SESSION['security_last_activity_at'] = time();
        }

        return $token;
    }
}

if (!function_exists('systemSecuritySessionRecord')) {
    function systemSecuritySessionRecord(PDO $pdo, string $token): ?array
    {
        if ($token === '' || !systemSecurityTableExists($pdo, 'user_sessions')) {
            return null;
        }

        try {
            $statement = $pdo->prepare(
                "SELECT * FROM user_sessions WHERE session_token = :token LIMIT 1"
            );
            $statement->execute(['token' => $token]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('systemSecurityTouchSession')) {
    function systemSecurityTouchSession(PDO $pdo, string $token): void
    {
        if ($token === '' || !systemSecurityTableExists($pdo, 'user_sessions')) {
            return;
        }

        $lastTouch = (int) ($_SESSION['security_db_touch_at'] ?? 0);
        if ($lastTouch > 0 && (time() - $lastTouch) < 60) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                "UPDATE user_sessions
                 SET last_activity_at = NOW()
                 WHERE session_token = :token AND revoked_at IS NULL"
            );
            $statement->execute(['token' => $token]);
            $_SESSION['security_db_touch_at'] = time();
        } catch (Throwable $exception) {
            // No se interrumpe la navegación por un fallo de telemetría.
        }
    }
}

if (!function_exists('systemSecurityRevokeCurrentSession')) {
    function systemSecurityRevokeCurrentSession(PDO $pdo, string $reason = 'Cierre de sesión'): void
    {
        $token = trim((string) ($_SESSION['security_session_token'] ?? ''));

        if ($token === '' || !systemSecurityTableExists($pdo, 'user_sessions')) {
            return;
        }

        try {
            $statement = $pdo->prepare(
                "UPDATE user_sessions
                 SET revoked_at = COALESCE(revoked_at, NOW()), revoke_reason = :reason
                 WHERE session_token = :token"
            );
            $statement->execute([
                'reason' => substr($reason, 0, 120),
                'token' => $token,
            ]);
        } catch (Throwable $exception) {
            // El cierre local de la sesión continuará aunque falle el registro.
        }
    }
}

if (!function_exists('systemSecurityRecentLogs')) {
    function systemSecurityRecentLogs(PDO $pdo, int $limit = 20): array
    {
        if (!systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        try {
            $statement = $pdo->query(
                "SELECT l.*, u.name AS user_name, a.name AS actor_name
                 FROM security_audit_logs l
                 LEFT JOIN users u ON u.id = l.user_id
                 LEFT JOIN users a ON a.id = l.actor_user_id
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT {$limit}"
            );
            return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSecurityActiveSessions')) {
    function systemSecurityActiveSessions(PDO $pdo, int $limit = 30): array
    {
        if (!systemSecurityTableExists($pdo, 'user_sessions')) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        try {
            $statement = $pdo->query(
                "SELECT s.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
                 FROM user_sessions s
                 INNER JOIN users u ON u.id = s.user_id
                 WHERE s.revoked_at IS NULL
                   AND (s.expires_at IS NULL OR s.expires_at > NOW())
                 ORDER BY s.last_activity_at DESC, s.id DESC
                 LIMIT {$limit}"
            );
            return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSecurityProtectionLevel')) {
    function systemSecurityProtectionLevel(array $settings): array
    {
        $score = 0;
        $score += min(20, max(0, ((int) ($settings['min_password_length'] ?? 8) - 6) * 4));
        $score += !empty($settings['require_uppercase']) ? 8 : 0;
        $score += !empty($settings['require_lowercase']) ? 8 : 0;
        $score += !empty($settings['require_number']) ? 8 : 0;
        $score += !empty($settings['require_special']) ? 10 : 0;
        $score += !empty($settings['block_common_passwords']) ? 8 : 0;
        $score += ((int) ($settings['max_failed_attempts'] ?? 5) <= 5) ? 10 : 5;
        $score += ((int) ($settings['lockout_minutes'] ?? 15) >= 10) ? 8 : 4;
        $score += ((int) ($settings['session_idle_minutes'] ?? 30) <= 30) ? 10 : 5;
        $score += !empty($settings['invalidate_sessions_on_password_change']) ? 8 : 0;
        $score += !empty($settings['audit_enabled']) ? 8 : 0;
        $score = min(100, $score);

        if ($score >= 80) {
            return ['label' => 'Alto', 'class' => 'high', 'score' => $score];
        }
        if ($score >= 55) {
            return ['label' => 'Moderado', 'class' => 'medium', 'score' => $score];
        }

        return ['label' => 'Básico', 'class' => 'basic', 'score' => $score];
    }
}

if (!function_exists('systemSecurityPasswordExpired')) {
    function systemSecurityPasswordExpired(array $user, array $settings): bool
    {
        $days = (int) ($settings['password_expiry_days'] ?? 0);
        if ($days <= 0) {
            return false;
        }

        $changedAt = trim((string) ($user['password_changed_at'] ?? $user['created_at'] ?? ''));
        if ($changedAt === '') {
            return true;
        }

        $timestamp = strtotime($changedAt);
        return $timestamp === false || $timestamp < (time() - ($days * 86400));
    }
}

/* =========================================================
   TRAZABILIDAD ORGANIZADA POR EMPRESA Y CONTACTO
   ========================================================= */
if (!function_exists('systemSecurityCompanyLogoSql')) {
    function systemSecurityCompanyLogoSql(PDO $pdo, string $alias = 'c'): string
    {
        return systemSecurityColumnExists($pdo, 'client_companies', 'logo_path')
            ? "{$alias}.logo_path"
            : 'NULL AS logo_path';
    }
}

if (!function_exists('systemSecurityCompanyTraceCount')) {
    function systemSecurityCompanyTraceCount(PDO $pdo, string $search = ''): int
    {
        if (!systemSecurityTableExists($pdo, 'client_companies') || !systemSecurityTableExists($pdo, 'users')) {
            return 0;
        }

        $search = trim($search);
        $where = "u.role = 'CLIENT'";
        $params = [];

        if ($search !== '') {
            $where .= " AND (c.business_name LIKE :search OR c.trade_name LIKE :search OR c.ruc LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        try {
            $statement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM (
                    SELECT c.id
                    FROM client_companies c
                    INNER JOIN users u ON u.company_id = c.id
                    WHERE {$where}
                    GROUP BY c.id
                 ) companies"
            );
            $statement->execute($params);
            return (int) $statement->fetchColumn();
        } catch (Throwable $exception) {
            return 0;
        }
    }
}

if (!function_exists('systemSecurityCompanyTraceSummaries')) {
    function systemSecurityCompanyTraceSummaries(
        PDO $pdo,
        int $limit = 5,
        int $offset = 0,
        string $search = ''
    ): array {
        if (!systemSecurityTableExists($pdo, 'client_companies')
            || !systemSecurityTableExists($pdo, 'users')
            || !systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $search = trim($search);
        $where = "u.role = 'CLIENT'";
        $params = [];

        if ($search !== '') {
            $where .= " AND (c.business_name LIKE :search OR c.trade_name LIKE :search OR c.ruc LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $logoSql = systemSecurityCompanyLogoSql($pdo, 'c');

        try {
            $statement = $pdo->prepare(
                "SELECT
                    c.id AS company_id,
                    c.business_name,
                    c.trade_name,
                    c.ruc,
                    c.status,
                    {$logoSql},
                    COUNT(DISTINCT u.id) AS contact_count,
                    COUNT(l.id) AS event_count,
                    SUM(CASE WHEN l.severity = 'warning' THEN 1 ELSE 0 END) AS warning_count,
                    SUM(CASE WHEN l.severity = 'critical' THEN 1 ELSE 0 END) AS critical_count,
                    MAX(l.created_at) AS last_activity
                 FROM client_companies c
                 INNER JOIN users u ON u.company_id = c.id
                 LEFT JOIN security_audit_logs l ON l.user_id = u.id
                 WHERE {$where}
                 GROUP BY c.id, c.business_name, c.trade_name, c.ruc, c.status" .
                    (systemSecurityColumnExists($pdo, 'client_companies', 'logo_path') ? ', c.logo_path' : '') .
                " ORDER BY (MAX(l.created_at) IS NULL) ASC, MAX(l.created_at) DESC,
                    COALESCE(NULLIF(c.trade_name, ''), c.business_name) ASC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSecurityCompanyTraceDetail')) {
    function systemSecurityCompanyTraceDetail(PDO $pdo, int $companyId): ?array
    {
        if ($companyId <= 0
            || !systemSecurityTableExists($pdo, 'client_companies')
            || !systemSecurityTableExists($pdo, 'users')) {
            return null;
        }

        $logoSql = systemSecurityCompanyLogoSql($pdo, 'c');

        try {
            $statement = $pdo->prepare(
                "SELECT
                    c.id AS company_id,
                    c.business_name,
                    c.trade_name,
                    c.ruc,
                    c.email,
                    c.phone,
                    c.fiscal_address,
                    c.status,
                    {$logoSql},
                    COUNT(DISTINCT CASE WHEN u.role = 'CLIENT' THEN u.id END) AS contact_count,
                    COUNT(l.id) AS event_count,
                    MAX(l.created_at) AS last_activity
                 FROM client_companies c
                 LEFT JOIN users u ON u.company_id = c.id AND u.role = 'CLIENT'
                 LEFT JOIN security_audit_logs l ON l.user_id = u.id
                 WHERE c.id = :company_id
                 GROUP BY c.id, c.business_name, c.trade_name, c.ruc, c.email, c.phone,
                          c.fiscal_address, c.status" .
                    (systemSecurityColumnExists($pdo, 'client_companies', 'logo_path') ? ', c.logo_path' : '') .
                " LIMIT 1"
            );
            $statement->execute(['company_id' => $companyId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('systemSecurityCompanyContactTraceCount')) {
    function systemSecurityCompanyContactTraceCount(PDO $pdo, int $companyId, string $search = ''): int
    {
        if ($companyId <= 0 || !systemSecurityTableExists($pdo, 'users')) {
            return 0;
        }

        $where = "company_id = :company_id AND role = 'CLIENT'";
        $params = ['company_id' => $companyId];
        $search = trim($search);

        if ($search !== '') {
            $where .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search OR position LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        try {
            $statement = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
            $statement->execute($params);
            return (int) $statement->fetchColumn();
        } catch (Throwable $exception) {
            return 0;
        }
    }
}

if (!function_exists('systemSecurityCompanyContactTraceSummaries')) {
    function systemSecurityCompanyContactTraceSummaries(
        PDO $pdo,
        int $companyId,
        int $limit = 12,
        int $offset = 0,
        string $search = ''
    ): array {
        if ($companyId <= 0
            || !systemSecurityTableExists($pdo, 'users')
            || !systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $where = "u.company_id = :company_id AND u.role = 'CLIENT'";
        $params = ['company_id' => $companyId];
        $search = trim($search);

        if ($search !== '') {
            $where .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR u.position LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        try {
            $statement = $pdo->prepare(
                "SELECT
                    u.id AS user_id,
                    u.name,
                    u.email,
                    u.phone,
                    u.position,
                    u.status,
                    u.profile_photo,
                    COUNT(l.id) AS event_count,
                    SUM(CASE WHEN l.severity = 'info' THEN 1 ELSE 0 END) AS info_count,
                    SUM(CASE WHEN l.severity = 'warning' THEN 1 ELSE 0 END) AS warning_count,
                    SUM(CASE WHEN l.severity = 'critical' THEN 1 ELSE 0 END) AS critical_count,
                    MAX(l.created_at) AS last_activity
                 FROM users u
                 LEFT JOIN security_audit_logs l ON l.user_id = u.id
                 WHERE {$where}
                 GROUP BY u.id, u.name, u.email, u.phone, u.position, u.status, u.profile_photo
                 ORDER BY (MAX(l.created_at) IS NULL) ASC, MAX(l.created_at) DESC, u.name ASC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSecurityContactTraceDetail')) {
    function systemSecurityContactTraceDetail(PDO $pdo, int $userId): ?array
    {
        if ($userId <= 0
            || !systemSecurityTableExists($pdo, 'users')
            || !systemSecurityTableExists($pdo, 'client_companies')) {
            return null;
        }

        $logoSql = systemSecurityCompanyLogoSql($pdo, 'c');

        try {
            $statement = $pdo->prepare(
                "SELECT
                    u.id AS user_id,
                    u.name,
                    u.email,
                    u.phone,
                    u.position,
                    u.status,
                    u.profile_photo,
                    u.last_login_at,
                    c.id AS company_id,
                    c.business_name,
                    c.trade_name,
                    c.ruc,
                    {$logoSql}
                 FROM users u
                 INNER JOIN client_companies c ON c.id = u.company_id
                 WHERE u.id = :user_id AND u.role = 'CLIENT'
                 LIMIT 1"
            );
            $statement->execute(['user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('systemSecurityTraceFilterSql')) {
    function systemSecurityTraceFilterSql(array $filters, array &$params): string
    {
        $parts = [];
        $search = trim((string) ($filters['search'] ?? ''));
        $severity = trim((string) ($filters['severity'] ?? ''));
        $eventType = trim((string) ($filters['event_type'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($search !== '') {
            $parts[] = "(l.description LIKE :trace_search OR l.event_type LIKE :trace_search OR l.ip_address LIKE :trace_search OR a.name LIKE :trace_search)";
            $params['trace_search'] = '%' . $search . '%';
        }
        if (in_array($severity, ['info', 'warning', 'critical'], true)) {
            $parts[] = 'l.severity = :trace_severity';
            $params['trace_severity'] = $severity;
        }
        if ($eventType !== '') {
            $parts[] = 'l.event_type = :trace_event_type';
            $params['trace_event_type'] = $eventType;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $parts[] = 'DATE(l.created_at) >= :trace_date_from';
            $params['trace_date_from'] = $dateFrom;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $parts[] = 'DATE(l.created_at) <= :trace_date_to';
            $params['trace_date_to'] = $dateTo;
        }

        return $parts ? ' AND ' . implode(' AND ', $parts) : '';
    }
}

if (!function_exists('systemSecurityContactTraceCount')) {
    function systemSecurityContactTraceCount(PDO $pdo, int $userId, array $filters = []): int
    {
        if ($userId <= 0 || !systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return 0;
        }

        $params = ['user_id' => $userId];
        $filterSql = systemSecurityTraceFilterSql($filters, $params);

        try {
            $statement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM security_audit_logs l
                 LEFT JOIN users a ON a.id = l.actor_user_id
                 WHERE l.user_id = :user_id{$filterSql}"
            );
            $statement->execute($params);
            return (int) $statement->fetchColumn();
        } catch (Throwable $exception) {
            return 0;
        }
    }
}

if (!function_exists('systemSecurityContactTraceLogs')) {
    function systemSecurityContactTraceLogs(
        PDO $pdo,
        int $userId,
        int $limit = 15,
        int $offset = 0,
        array $filters = []
    ): array {
        if ($userId <= 0 || !systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $params = ['user_id' => $userId];
        $filterSql = systemSecurityTraceFilterSql($filters, $params);

        try {
            $statement = $pdo->prepare(
                "SELECT l.*, u.name AS user_name, a.name AS actor_name
                 FROM security_audit_logs l
                 LEFT JOIN users u ON u.id = l.user_id
                 LEFT JOIN users a ON a.id = l.actor_user_id
                 WHERE l.user_id = :user_id{$filterSql}
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSecurityGeneralTraceBaseCondition')) {
    function systemSecurityGeneralTraceBaseCondition(): string
    {
        return "(l.user_id IS NULL OR u.id IS NULL OR u.role <> 'CLIENT' OR u.company_id IS NULL)";
    }
}

if (!function_exists('systemSecurityGeneralTraceCount')) {
    function systemSecurityGeneralTraceCount(PDO $pdo, array $filters = []): int
    {
        if (!systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return 0;
        }

        $params = [];
        $filterSql = systemSecurityTraceFilterSql($filters, $params);

        try {
            $statement = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM security_audit_logs l
                 LEFT JOIN users u ON u.id = l.user_id
                 LEFT JOIN users a ON a.id = l.actor_user_id
                 WHERE " . systemSecurityGeneralTraceBaseCondition() . $filterSql
            );
            $statement->execute($params);
            return (int) $statement->fetchColumn();
        } catch (Throwable $exception) {
            return 0;
        }
    }
}

if (!function_exists('systemSecurityGeneralTraceLogs')) {
    function systemSecurityGeneralTraceLogs(
        PDO $pdo,
        int $limit = 5,
        int $offset = 0,
        array $filters = []
    ): array {
        if (!systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $params = [];
        $filterSql = systemSecurityTraceFilterSql($filters, $params);

        try {
            $statement = $pdo->prepare(
                "SELECT l.*, u.name AS user_name, u.role AS user_role, a.name AS actor_name
                 FROM security_audit_logs l
                 LEFT JOIN users u ON u.id = l.user_id
                 LEFT JOIN users a ON a.id = l.actor_user_id
                 WHERE " . systemSecurityGeneralTraceBaseCondition() . $filterSql .
                " ORDER BY l.created_at DESC, l.id DESC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSecurityTraceEventTypes')) {
    function systemSecurityTraceEventTypes(PDO $pdo, string $scope = 'general', ?int $userId = null): array
    {
        if (!systemSecurityTableExists($pdo, 'security_audit_logs')) {
            return [];
        }

        try {
            if ($scope === 'contact' && $userId !== null && $userId > 0) {
                $statement = $pdo->prepare(
                    "SELECT DISTINCT event_type
                     FROM security_audit_logs
                     WHERE user_id = :user_id
                     ORDER BY event_type ASC"
                );
                $statement->execute(['user_id' => $userId]);
            } else {
                $statement = $pdo->query(
                    "SELECT DISTINCT l.event_type
                     FROM security_audit_logs l
                     LEFT JOIN users u ON u.id = l.user_id
                     WHERE " . systemSecurityGeneralTraceBaseCondition() . "
                     ORDER BY l.event_type ASC"
                );
            }

            return $statement ? array_values(array_filter(array_map(
                static fn ($value) => trim((string) $value),
                $statement->fetchAll(PDO::FETCH_COLUMN)
            ))) : [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

