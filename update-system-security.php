<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-system-security.php');
    exit;
}

if (!systemSecurityReady($pdo)) {
    $_SESSION['settings_error'] = 'Primero ejecuta database/system_security.sql en phpMyAdmin.';
    header('Location: /helpdesk-php/admin-system-security.php');
    exit;
}

if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'settings')) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Recarga la página e inténtalo nuevamente.';
    header('Location: /helpdesk-php/admin-system-security.php');
    exit;
}

function securityBoundedInt(string $key, int $minimum, int $maximum, int $fallback): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if ($value === false || $value === null) {
        return $fallback;
    }

    return max($minimum, min($maximum, (int) $value));
}

$settings = [
    'min_password_length' => securityBoundedInt('min_password_length', 6, 64, 8),
    'require_uppercase' => isset($_POST['require_uppercase']) ? 1 : 0,
    'require_lowercase' => isset($_POST['require_lowercase']) ? 1 : 0,
    'require_number' => isset($_POST['require_number']) ? 1 : 0,
    'require_special' => isset($_POST['require_special']) ? 1 : 0,
    'block_common_passwords' => isset($_POST['block_common_passwords']) ? 1 : 0,
    'force_change_on_create' => isset($_POST['force_change_on_create']) ? 1 : 0,
    'password_expiry_days' => securityBoundedInt('password_expiry_days', 0, 365, 0),
    'max_failed_attempts' => securityBoundedInt('max_failed_attempts', 3, 20, 5),
    'lockout_minutes' => securityBoundedInt('lockout_minutes', 1, 1440, 15),
    'failed_attempt_reset_minutes' => securityBoundedInt('failed_attempt_reset_minutes', 5, 1440, 30),
    'session_idle_minutes' => securityBoundedInt('session_idle_minutes', 5, 1440, 30),
    'session_max_hours' => securityBoundedInt('session_max_hours', 1, 168, 12),
    'single_session' => isset($_POST['single_session']) ? 1 : 0,
    'invalidate_sessions_on_password_change' => isset($_POST['invalidate_sessions_on_password_change']) ? 1 : 0,
    'block_inactive_users' => isset($_POST['block_inactive_users']) ? 1 : 0,
    'audit_enabled' => isset($_POST['audit_enabled']) ? 1 : 0,
];

$_SESSION['system_security_old'] = $settings;

try {
    $statement = $pdo->prepare(
        "INSERT INTO system_security_settings
            (id, min_password_length, require_uppercase, require_lowercase, require_number,
             require_special, block_common_passwords, force_change_on_create, password_expiry_days,
             max_failed_attempts, lockout_minutes, failed_attempt_reset_minutes,
             session_idle_minutes, session_max_hours, single_session,
             invalidate_sessions_on_password_change, block_inactive_users, audit_enabled,
             updated_by, created_at, updated_at)
         VALUES
            (1, :min_password_length, :require_uppercase, :require_lowercase, :require_number,
             :require_special, :block_common_passwords, :force_change_on_create, :password_expiry_days,
             :max_failed_attempts, :lockout_minutes, :failed_attempt_reset_minutes,
             :session_idle_minutes, :session_max_hours, :single_session,
             :invalidate_sessions_on_password_change, :block_inactive_users, :audit_enabled,
             :updated_by, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            min_password_length = VALUES(min_password_length),
            require_uppercase = VALUES(require_uppercase),
            require_lowercase = VALUES(require_lowercase),
            require_number = VALUES(require_number),
            require_special = VALUES(require_special),
            block_common_passwords = VALUES(block_common_passwords),
            force_change_on_create = VALUES(force_change_on_create),
            password_expiry_days = VALUES(password_expiry_days),
            max_failed_attempts = VALUES(max_failed_attempts),
            lockout_minutes = VALUES(lockout_minutes),
            failed_attempt_reset_minutes = VALUES(failed_attempt_reset_minutes),
            session_idle_minutes = VALUES(session_idle_minutes),
            session_max_hours = VALUES(session_max_hours),
            single_session = VALUES(single_session),
            invalidate_sessions_on_password_change = VALUES(invalidate_sessions_on_password_change),
            block_inactive_users = VALUES(block_inactive_users),
            audit_enabled = VALUES(audit_enabled),
            updated_by = VALUES(updated_by),
            updated_at = NOW()"
    );

    $statement->execute($settings + [
        'updated_by' => (int) (user()['id'] ?? 0) ?: null,
    ]);

    systemSecurityAudit(
        $pdo,
        'SECURITY_SETTINGS_UPDATED',
        'Se actualizaron las políticas de seguridad del sistema.',
        null,
        (int) (user()['id'] ?? 0),
        'warning',
        ['settings' => $settings]
    );

    unset($_SESSION['system_security_old']);
    $_SESSION['settings_success'] = 'La configuración de seguridad se guardó correctamente.';
} catch (Throwable $exception) {
    $_SESSION['settings_error'] = 'No se pudo guardar la configuración de seguridad.';
}

header('Location: /helpdesk-php/admin-system-security.php');
exit;
