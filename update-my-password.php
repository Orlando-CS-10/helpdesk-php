<?php
require_once __DIR__ . '/app/helpers/session.php';
requireLogin();

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/change-password.php');
    exit;
}

if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'change_password')) {
    $_SESSION['password_change_error'] = 'El formulario venció. Recarga la página.';
    header('Location: /helpdesk-php/change-password.php');
    exit;
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$newPasswordConfirmation = (string) ($_POST['new_password_confirmation'] ?? '');
$currentUser = user() ?? [];
$userId = (int) ($currentUser['id'] ?? 0);

if ($userId <= 0 || $currentPassword === '' || $newPassword === '' || $newPasswordConfirmation === '') {
    $_SESSION['password_change_error'] = 'Completa todos los campos.';
    header('Location: /helpdesk-php/change-password.php');
    exit;
}

if ($newPassword !== $newPasswordConfirmation) {
    $_SESSION['password_change_error'] = 'Las nuevas contraseñas no coinciden.';
    header('Location: /helpdesk-php/change-password.php');
    exit;
}

$statement = $pdo->prepare('SELECT id, name, email, password FROM users WHERE id = :id LIMIT 1');
$statement->execute(['id' => $userId]);
$dbUser = $statement->fetch(PDO::FETCH_ASSOC);

if (!$dbUser || !password_verify($currentPassword, (string) ($dbUser['password'] ?? ''))) {
    $_SESSION['password_change_error'] = 'La contraseña actual no es correcta.';
    systemSecurityAudit($pdo, 'PASSWORD_CHANGE_CURRENT_INVALID', 'Se rechazó un cambio de contraseña por validación incorrecta.', $userId, $userId, 'warning');
    header('Location: /helpdesk-php/change-password.php');
    exit;
}

if (password_verify($newPassword, (string) ($dbUser['password'] ?? ''))) {
    $_SESSION['password_change_error'] = 'La nueva contraseña debe ser diferente a la actual.';
    header('Location: /helpdesk-php/change-password.php');
    exit;
}

$settings = getSystemSecuritySettings($pdo);
$errors = systemSecurityPasswordErrors($newPassword, $settings, [
    'name' => $dbUser['name'] ?? '',
    'email' => $dbUser['email'] ?? '',
]);

if ($errors) {
    $_SESSION['password_change_error'] = implode(' ', $errors);
    header('Location: /helpdesk-php/change-password.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $sets = ['password = :password'];

    if (systemSecurityColumnExists($pdo, 'users', 'password_changed_at')) {
        $sets[] = 'password_changed_at = NOW()';
    }
    if (systemSecurityColumnExists($pdo, 'users', 'force_password_change')) {
        $sets[] = 'force_password_change = 0';
    }

    $update = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $update->execute(['password' => $passwordHash, 'id' => $userId]);

    $currentToken = trim((string) ($_SESSION['security_session_token'] ?? ''));
    if (!empty($settings['invalidate_sessions_on_password_change'])) {
        systemSecurityRevokeUserSessions($pdo, $userId, 'Contraseña modificada', $currentToken);
    }

    systemSecurityAudit($pdo, 'PASSWORD_CHANGED_BY_USER', 'El usuario actualizó su contraseña.', $userId, $userId, 'warning');

    $pdo->commit();

    $_SESSION['user']['force_password_change'] = 0;
    session_regenerate_id(true);

    if ($currentToken !== '' && systemSecurityTableExists($pdo, 'user_sessions')) {
        $sessionUpdate = $pdo->prepare('UPDATE user_sessions SET php_session_hash = :hash, last_activity_at = NOW() WHERE session_token = :token');
        $sessionUpdate->execute([
            'hash' => hash('sha256', session_id()),
            'token' => $currentToken,
        ]);
    }

    $_SESSION['settings_success'] = 'Tu contraseña se actualizó correctamente.';
    header('Location: /helpdesk-php/index.php');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['password_change_error'] = 'No se pudo actualizar la contraseña.';
    header('Location: /helpdesk-php/change-password.php');
    exit;
}
