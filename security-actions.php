<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-system-security.php');
    exit;
}

if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'actions')) {
    $_SESSION['settings_error'] = 'La confirmación de seguridad venció. Recarga la página.';
    header('Location: /helpdesk-php/admin-system-security.php');
    exit;
}

$action = trim((string) ($_POST['security_action'] ?? ''));
$actorId = (int) (user()['id'] ?? 0);
$currentToken = trim((string) ($_SESSION['security_session_token'] ?? ''));

try {
    if (!systemSecurityReady($pdo)) {
        throw new RuntimeException('Primero ejecuta database/system_security.sql.');
    }

    switch ($action) {
        case 'revoke_session':
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            if ($sessionId <= 0) {
                throw new RuntimeException('La sesión seleccionada no es válida.');
            }

            $statement = $pdo->prepare(
                "UPDATE user_sessions
                 SET revoked_at = NOW(), revoke_reason = 'Cierre administrativo'
                 WHERE id = :id
                   AND revoked_at IS NULL
                   AND session_token <> :current_token"
            );
            $statement->execute([
                'id' => $sessionId,
                'current_token' => $currentToken,
            ]);
            $_SESSION['settings_success'] = $statement->rowCount() > 0
                ? 'La sesión seleccionada fue cerrada.'
                : 'La sesión ya estaba cerrada o corresponde a tu sesión actual.';
            systemSecurityAudit($pdo, 'SESSION_REVOKED_BY_ADMIN', 'Un administrador cerró una sesión activa.', null, $actorId, 'warning', ['session_id' => $sessionId]);
            break;

        case 'revoke_other_sessions':
            $statement = $pdo->prepare(
                "UPDATE user_sessions
                 SET revoked_at = NOW(), revoke_reason = 'Cierre global administrativo'
                 WHERE revoked_at IS NULL
                   AND session_token <> :current_token"
            );
            $statement->execute(['current_token' => $currentToken]);
            $_SESSION['settings_success'] = 'Se cerraron ' . $statement->rowCount() . ' sesiones activas. Tu sesión permaneció abierta.';
            systemSecurityAudit($pdo, 'ALL_OTHER_SESSIONS_REVOKED', 'Un administrador cerró todas las demás sesiones.', null, $actorId, 'critical');
            break;

        case 'unlock_all_users':
            $statement = $pdo->exec(
                "UPDATE users
                 SET failed_login_attempts = 0,
                     failed_login_at = NULL,
                     locked_until = NULL"
            );
            $_SESSION['settings_success'] = 'Se desbloquearon las cuentas y se limpiaron los intentos fallidos.';
            systemSecurityAudit($pdo, 'ALL_USERS_UNLOCKED', 'Se desbloquearon todas las cuentas.', null, $actorId, 'warning', ['affected_rows' => (int) $statement]);
            break;

        case 'force_password_change_all':
            $statement = $pdo->exec("UPDATE users SET force_password_change = 1 WHERE status = 1");
            $_SESSION['user']['force_password_change'] = 1;
            $_SESSION['settings_success'] = 'Se solicitó el cambio de contraseña a todas las cuentas activas.';
            systemSecurityAudit($pdo, 'FORCE_PASSWORD_CHANGE_ALL', 'Se forzó el cambio de contraseña para todas las cuentas activas.', null, $actorId, 'critical', ['affected_rows' => (int) $statement]);
            header('Location: /helpdesk-php/change-password.php');
            exit;

        case 'restore_defaults':
            $defaults = systemSecurityDefaults();
            $statement = $pdo->prepare(
                "UPDATE system_security_settings SET
                    min_password_length = :min_password_length,
                    require_uppercase = :require_uppercase,
                    require_lowercase = :require_lowercase,
                    require_number = :require_number,
                    require_special = :require_special,
                    block_common_passwords = :block_common_passwords,
                    force_change_on_create = :force_change_on_create,
                    password_expiry_days = :password_expiry_days,
                    max_failed_attempts = :max_failed_attempts,
                    lockout_minutes = :lockout_minutes,
                    failed_attempt_reset_minutes = :failed_attempt_reset_minutes,
                    session_idle_minutes = :session_idle_minutes,
                    session_max_hours = :session_max_hours,
                    single_session = :single_session,
                    invalidate_sessions_on_password_change = :invalidate_sessions_on_password_change,
                    block_inactive_users = :block_inactive_users,
                    audit_enabled = :audit_enabled,
                    updated_by = :updated_by,
                    updated_at = NOW()
                 WHERE id = 1"
            );
            $statement->execute([
                'min_password_length' => $defaults['min_password_length'],
                'require_uppercase' => $defaults['require_uppercase'],
                'require_lowercase' => $defaults['require_lowercase'],
                'require_number' => $defaults['require_number'],
                'require_special' => $defaults['require_special'],
                'block_common_passwords' => $defaults['block_common_passwords'],
                'force_change_on_create' => $defaults['force_change_on_create'],
                'password_expiry_days' => $defaults['password_expiry_days'],
                'max_failed_attempts' => $defaults['max_failed_attempts'],
                'lockout_minutes' => $defaults['lockout_minutes'],
                'failed_attempt_reset_minutes' => $defaults['failed_attempt_reset_minutes'],
                'session_idle_minutes' => $defaults['session_idle_minutes'],
                'session_max_hours' => $defaults['session_max_hours'],
                'single_session' => $defaults['single_session'],
                'invalidate_sessions_on_password_change' => $defaults['invalidate_sessions_on_password_change'],
                'block_inactive_users' => $defaults['block_inactive_users'],
                'audit_enabled' => $defaults['audit_enabled'],
                'updated_by' => $actorId ?: null,
            ]);
            $_SESSION['settings_success'] = 'Se restauraron los valores de seguridad recomendados.';
            systemSecurityAudit($pdo, 'SECURITY_DEFAULTS_RESTORED', 'Se restauraron las políticas de seguridad predeterminadas.', null, $actorId, 'warning');
            break;

        default:
            throw new RuntimeException('La acción solicitada no existe.');
    }
} catch (Throwable $exception) {
    $_SESSION['settings_error'] = $exception->getMessage();
}

header('Location: /helpdesk-php/admin-system-security.php');
exit;
