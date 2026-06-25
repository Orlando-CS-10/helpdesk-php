<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

requireRole('ADMIN');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$redirectUrl = '/helpdesk-php/reset-user-password.php?id=' . $id;

if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'reset_user_password')) {
    $_SESSION['user_error'] = 'El formulario venció. Recarga la página.';
    header('Location: ' . $redirectUrl);
    exit;
}

$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$forceChangeNextLogin = isset($_POST['force_change_next_login']) ? 1 : 0;

if ($id <= 0 || $password === '' || $passwordConfirm === '') {
    $_SESSION['user_error'] = 'Completa todos los campos.';
    header('Location: ' . $redirectUrl);
    exit;
}

if ($password !== $passwordConfirm) {
    $_SESSION['user_error'] = 'Las contraseñas no coinciden.';
    header('Location: ' . $redirectUrl);
    exit;
}

$statement = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id LIMIT 1');
$statement->execute(['id' => $id]);
$targetUser = $statement->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    $_SESSION['user_error'] = 'El usuario seleccionado no existe.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$settings = getSystemSecuritySettings($pdo);
$passwordErrors = systemSecurityPasswordErrors($password, $settings, [
    'name' => $targetUser['name'] ?? '',
    'email' => $targetUser['email'] ?? '',
]);

if ($passwordErrors) {
    $_SESSION['user_error'] = implode(' ', $passwordErrors);
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $pdo->beginTransaction();

    $sets = ['password = :password'];
    $params = [
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $id,
    ];

    if (systemSecurityColumnExists($pdo, 'users', 'password_changed_at')) {
        $sets[] = 'password_changed_at = NOW()';
    }

    if (systemSecurityColumnExists($pdo, 'users', 'force_password_change')) {
        $sets[] = 'force_password_change = :force_password_change';
        $params['force_password_change'] = $forceChangeNextLogin;
    }

    $update = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $update->execute($params);

    if (!empty($settings['invalidate_sessions_on_password_change'])) {
        systemSecurityRevokeUserSessions($pdo, $id, 'Contraseña restablecida por un administrador');
    }

    systemSecurityAudit(
        $pdo,
        'PASSWORD_RESET_BY_ADMIN',
        'Un administrador restableció la contraseña de un usuario.',
        $id,
        (int) (user()['id'] ?? 0),
        'critical',
        ['force_change_next_login' => $forceChangeNextLogin]
    );

    $pdo->commit();

    $_SESSION['user_success'] = 'La contraseña fue actualizada y las sesiones anteriores quedaron invalidadas.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['user_error'] = 'No se pudo actualizar la contraseña.';
    header('Location: ' . $redirectUrl);
    exit;
}
