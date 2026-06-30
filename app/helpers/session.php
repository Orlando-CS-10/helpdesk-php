<?php

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}

function destroyLocalSession(): void
{
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

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function enforceCurrentSecuritySession(): array
{
    if (!isLoggedIn()) {
        return ['valid' => false, 'reason' => 'not_authenticated'];
    }

    /*
     * La conexión debe quedar en el ámbito global. Antes se cargaba
     * database.php con require_once dentro de esta función; eso dejaba
     * $pdo únicamente en el ámbito local y las vistas posteriores no
     * podían reutilizarlo porque PHP ya consideraba el archivo incluido.
     */
    global $pdo;

    try {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            require __DIR__ . '/../config/database.php';
        }

        require_once __DIR__ . '/system_security.php';

        if (!isset($pdo) || !$pdo instanceof PDO) {
            return ['valid' => true, 'reason' => 'database_unavailable'];
        }

        $settings = getSystemSecuritySettings($pdo);
        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($currentUserId <= 0) {
            destroyLocalSession();
            return ['valid' => false, 'reason' => 'invalid_user'];
        }

        $statement = $pdo->prepare('SELECT id, status, force_password_change FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $currentUserId]);
        $freshUser = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$freshUser) {
            destroyLocalSession();
            return ['valid' => false, 'reason' => 'user_not_found'];
        }

        if (!empty($settings['block_inactive_users']) && (int) ($freshUser['status'] ?? 0) !== 1) {
            systemSecurityRevokeCurrentSession($pdo, 'Cuenta desactivada');
            systemSecurityAudit($pdo, 'SESSION_REVOKED_INACTIVE_USER', 'Se cerró la sesión de una cuenta inactiva.', $currentUserId, null, 'warning');
            destroyLocalSession();
            return ['valid' => false, 'reason' => 'inactive_user'];
        }

        $_SESSION['user']['status'] = (int) ($freshUser['status'] ?? 1);
        $_SESSION['user']['force_password_change'] = (int) ($freshUser['force_password_change'] ?? 0);

        $now = time();
        $startedAt = (int) ($_SESSION['security_session_started_at'] ?? $now);
        $lastActivityAt = (int) ($_SESSION['security_last_activity_at'] ?? $now);
        $idleMinutes = max(5, min(1440, (int) ($settings['session_idle_minutes'] ?? 30)));
        $maxHours = max(1, min(168, (int) ($settings['session_max_hours'] ?? 12)));

        if (($now - $lastActivityAt) > ($idleMinutes * 60)) {
            systemSecurityRevokeCurrentSession($pdo, 'Sesión vencida por inactividad');
            systemSecurityAudit($pdo, 'SESSION_EXPIRED_IDLE', 'La sesión venció por inactividad.', $currentUserId, null, 'info');
            destroyLocalSession();
            return ['valid' => false, 'reason' => 'idle_timeout'];
        }

        if (($now - $startedAt) > ($maxHours * 3600)) {
            systemSecurityRevokeCurrentSession($pdo, 'Duración máxima alcanzada');
            systemSecurityAudit($pdo, 'SESSION_EXPIRED_MAX_DURATION', 'La sesión alcanzó su duración máxima.', $currentUserId, null, 'info');
            destroyLocalSession();
            return ['valid' => false, 'reason' => 'absolute_timeout'];
        }

        $token = systemSecurityEnsureSessionRecord($pdo, $currentUserId, $settings);
        if ($token !== null && systemSecurityTableExists($pdo, 'user_sessions')) {
            $sessionRecord = systemSecuritySessionRecord($pdo, $token);

            if (!$sessionRecord || !empty($sessionRecord['revoked_at'])) {
                systemSecurityAudit($pdo, 'SESSION_REVOKED_ACCESS_ATTEMPT', 'Se detectó una sesión revocada.', $currentUserId, null, 'warning');
                destroyLocalSession();
                return ['valid' => false, 'reason' => 'revoked'];
            }

            $expiresAt = trim((string) ($sessionRecord['expires_at'] ?? ''));
            if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= $now) {
                systemSecurityRevokeCurrentSession($pdo, 'Sesión expirada');
                destroyLocalSession();
                return ['valid' => false, 'reason' => 'expired'];
            }

            systemSecurityTouchSession($pdo, $token);
        }

        $_SESSION['security_last_activity_at'] = $now;

        return [
            'valid' => true,
            'reason' => 'ok',
            'force_password_change' => !empty($_SESSION['user']['force_password_change']),
        ];
    } catch (Throwable $exception) {
        // Ante un fallo técnico, se conserva la sesión para no bloquear el sistema completo.
        return ['valid' => true, 'reason' => 'security_check_unavailable'];
    }
}

function requireLogin(): void
{
    $result = enforceCurrentSecuritySession();

    if (empty($result['valid'])) {
        $reason = rawurlencode((string) ($result['reason'] ?? 'expired'));
        header('Location: /helpdesk-php/login.php?reason=' . $reason);
        exit;
    }

    $currentScript = basename((string) parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH));

    // Modo mantenimiento global. Los administradores conservan el acceso
    // para poder revisar y desactivar la ventana de mantenimiento.
    $maintenanceAllowedScripts = ['maintenance.php', 'logout.php'];
    if (!in_array($currentScript, $maintenanceAllowedScripts, true)) {
        global $pdo;

        try {
            if (!isset($pdo) || !$pdo instanceof PDO) {
                require __DIR__ . '/../config/database.php';
            }

            $maintenanceHelper = __DIR__ . '/system_maintenance.php';
            if (is_file($maintenanceHelper)) {
                require_once $maintenanceHelper;

                if (isset($pdo) && $pdo instanceof PDO
                    && function_exists('systemMaintenanceShouldBlock')
                    && systemMaintenanceShouldBlock($pdo, user())) {
                    header('Location: /helpdesk-php/maintenance.php');
                    exit;
                }
            }
        } catch (Throwable $exception) {
            // Un fallo en la comprobación no debe dejar el sistema inaccesible.
        }
    }

    $passwordChangeAllowed = ['change-password.php', 'update-my-password.php', 'logout.php'];

    if (!empty($result['force_password_change']) && !in_array($currentScript, $passwordChangeAllowed, true)) {
        header('Location: /helpdesk-php/change-password.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== $role) {
        header('Location: /helpdesk-php/index.php');
        exit;
    }
}
