<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_customization.php';

$redirectUrl = '/helpdesk-php/admin-system-customization.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit;
}

if (!systemCustomizationTableExists($pdo)) {
    $_SESSION['settings_error'] = 'Primero ejecuta database/system_customization.sql en phpMyAdmin.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (!systemCustomizationVerifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Vuelve a intentarlo.';
    header('Location: ' . $redirectUrl);
    exit;
}

$currentUser = (array) user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'save');
$defaults = systemCustomizationDefaults();

if ($action === 'restore') {
    $customizationData = [
        'primary_color' => $defaults['primary_color'],
        'secondary_color' => $defaults['secondary_color'],
        'accent_color' => $defaults['accent_color'],
        'theme' => $defaults['theme'],
        'sidebar_default' => $defaults['sidebar_default'],
    ];
} else {
    $customizationData = [
        'primary_color' => strtolower(trim((string) ($_POST['primary_color'] ?? ''))),
        'secondary_color' => strtolower(trim((string) ($_POST['secondary_color'] ?? ''))),
        'accent_color' => strtolower(trim((string) ($_POST['accent_color'] ?? ''))),
        'theme' => strtolower(trim((string) ($_POST['theme'] ?? ''))),
        'sidebar_default' => strtolower(trim((string) ($_POST['sidebar_default'] ?? ''))),
    ];
}

$_SESSION['system_customization_old'] = $customizationData;

$colorFields = [
    'primary_color' => 'El color principal',
    'secondary_color' => 'El color secundario',
    'accent_color' => 'El color de acento',
];

foreach ($colorFields as $field => $label) {
    if (!preg_match('/^#[0-9a-f]{6}$/', $customizationData[$field])) {
        $_SESSION['settings_error'] = $label . ' debe tener el formato hexadecimal #RRGGBB.';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

if (!in_array($customizationData['theme'], ['light', 'dark', 'auto'], true)) {
    $_SESSION['settings_error'] = 'Selecciona un tema válido: claro, oscuro o automático.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (!in_array($customizationData['sidebar_default'], ['expanded', 'collapsed'], true)) {
    $_SESSION['settings_error'] = 'Selecciona un estado válido para el menú lateral.';
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $statement = $pdo->prepare(
        "INSERT INTO system_customization (
            id,
            primary_color,
            secondary_color,
            accent_color,
            theme,
            sidebar_default,
            updated_by
        ) VALUES (
            1,
            :primary_color,
            :secondary_color,
            :accent_color,
            :theme,
            :sidebar_default,
            :updated_by
        )
        ON DUPLICATE KEY UPDATE
            primary_color = VALUES(primary_color),
            secondary_color = VALUES(secondary_color),
            accent_color = VALUES(accent_color),
            theme = VALUES(theme),
            sidebar_default = VALUES(sidebar_default),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    $statement->execute([
        'primary_color' => $customizationData['primary_color'],
        'secondary_color' => $customizationData['secondary_color'],
        'accent_color' => $customizationData['accent_color'],
        'theme' => $customizationData['theme'],
        'sidebar_default' => $customizationData['sidebar_default'],
        'updated_by' => $currentUserId > 0 ? $currentUserId : null,
    ]);

    unset($_SESSION['system_customization_old']);
    $_SESSION['settings_success'] = $action === 'restore'
        ? 'El diseño original del sistema fue restaurado correctamente.'
        : 'La personalización del sistema fue actualizada correctamente.';
} catch (Throwable $exception) {
    $_SESSION['settings_error'] = 'No se pudieron guardar los cambios. Revisa la base de datos e inténtalo nuevamente.';
}

header('Location: ' . $redirectUrl);
exit;
