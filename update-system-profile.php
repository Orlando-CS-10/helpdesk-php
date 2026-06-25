<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_profile.php';

$redirectUrl = '/helpdesk-php/admin-system-profile.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit;
}

if (!systemProfileTableExists($pdo)) {
    $_SESSION['settings_error'] = 'Primero ejecuta database/system_profile.sql en phpMyAdmin.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (!systemProfileVerifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Vuelve a intentarlo.';
    header('Location: ' . $redirectUrl);
    exit;
}

$currentUser = (array) user();
$currentUserId = (int) ($currentUser['id'] ?? 0);

$profileData = [
    'company_name' => trim((string) ($_POST['company_name'] ?? '')),
    'commercial_name' => trim((string) ($_POST['commercial_name'] ?? '')),
    'system_name' => trim((string) ($_POST['system_name'] ?? '')),
    'ruc' => preg_replace('/\D+/', '', (string) ($_POST['ruc'] ?? '')),
    'corporate_email' => trim((string) ($_POST['corporate_email'] ?? '')),
    'phone' => trim((string) ($_POST['phone'] ?? '')),
    'address' => trim((string) ($_POST['address'] ?? '')),
    'website' => trim((string) ($_POST['website'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'slogan' => trim((string) ($_POST['slogan'] ?? '')),
];

$_SESSION['system_profile_old'] = $profileData;

$validationError = null;

if ($profileData['company_name'] === '' || mb_strlen($profileData['company_name']) < 3) {
    $validationError = 'El nombre de la empresa debe tener al menos 3 caracteres.';
} elseif (mb_strlen($profileData['company_name']) > 180) {
    $validationError = 'El nombre de la empresa no puede superar los 180 caracteres.';
} elseif ($profileData['system_name'] === '' || mb_strlen($profileData['system_name']) < 3) {
    $validationError = 'El nombre del sistema debe tener al menos 3 caracteres.';
} elseif (mb_strlen($profileData['system_name']) > 120) {
    $validationError = 'El nombre del sistema no puede superar los 120 caracteres.';
} elseif ($profileData['commercial_name'] !== '' && mb_strlen($profileData['commercial_name']) > 150) {
    $validationError = 'El nombre comercial no puede superar los 150 caracteres.';
} elseif ($profileData['ruc'] !== '' && !preg_match('/^\d{11}$/', $profileData['ruc'])) {
    $validationError = 'El RUC debe contener exactamente 11 dígitos.';
} elseif ($profileData['corporate_email'] !== '' && !filter_var($profileData['corporate_email'], FILTER_VALIDATE_EMAIL)) {
    $validationError = 'Ingresa un correo corporativo válido.';
} elseif ($profileData['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{6,25}$/', $profileData['phone'])) {
    $validationError = 'Ingresa un número telefónico válido.';
} elseif ($profileData['website'] !== '' && !filter_var($profileData['website'], FILTER_VALIDATE_URL)) {
    $validationError = 'El sitio web debe incluir http:// o https:// y ser una dirección válida.';
} elseif (mb_strlen($profileData['address']) > 255) {
    $validationError = 'La dirección no puede superar los 255 caracteres.';
} elseif (mb_strlen($profileData['slogan']) > 180) {
    $validationError = 'El eslogan no puede superar los 180 caracteres.';
} elseif (mb_strlen($profileData['description']) > 1500) {
    $validationError = 'La descripción no puede superar los 1500 caracteres.';
}

if ($validationError !== null) {
    $_SESSION['settings_error'] = $validationError;
    header('Location: ' . $redirectUrl);
    exit;
}

$currentProfile = getSystemProfile($pdo);
$currentLogoPath = trim((string) ($currentProfile['logo_path'] ?? ''));
$newLogoPath = $currentLogoPath;
$newUploadedAbsolutePath = null;
$removeCurrentLogo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';

if ($removeCurrentLogo) {
    $newLogoPath = null;
}

$logoFile = $_FILES['logo'] ?? null;

if (is_array($logoFile) && (int) ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $uploadError = (int) ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError !== UPLOAD_ERR_OK) {
        $uploadMessages = [
            UPLOAD_ERR_INI_SIZE => 'El logo supera el límite permitido por el servidor.',
            UPLOAD_ERR_FORM_SIZE => 'El logo supera el límite permitido por el formulario.',
            UPLOAD_ERR_PARTIAL => 'El logo se subió de forma incompleta.',
            UPLOAD_ERR_NO_TMP_DIR => 'No existe una carpeta temporal para procesar el logo.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el logo en el servidor.',
            UPLOAD_ERR_EXTENSION => 'Una extensión del servidor bloqueó la subida del logo.',
        ];

        $_SESSION['settings_error'] = $uploadMessages[$uploadError] ?? 'No se pudo procesar el logo seleccionado.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $temporaryPath = (string) ($logoFile['tmp_name'] ?? '');
    $fileSize = (int) ($logoFile['size'] ?? 0);

    if ($fileSize <= 0 || $fileSize > 2 * 1024 * 1024) {
        $_SESSION['settings_error'] = 'El logo debe pesar como máximo 2 MB.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $imageInformation = @getimagesize($temporaryPath);

    if ($imageInformation === false) {
        $_SESSION['settings_error'] = 'El archivo seleccionado no es una imagen válida.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $fileInfo->file($temporaryPath);
    $allowedMimeTypes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        $_SESSION['settings_error'] = 'El logo debe ser una imagen PNG, JPG o WEBP.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $uploadDirectory = __DIR__ . '/public/uploads/system';

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        $_SESSION['settings_error'] = 'No se pudo crear la carpeta para guardar el logo.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $extension = $allowedMimeTypes[$mimeType];
    $storedName = 'system_logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
    $newUploadedAbsolutePath = $uploadDirectory . '/' . $storedName;

    if (!move_uploaded_file($temporaryPath, $newUploadedAbsolutePath)) {
        $_SESSION['settings_error'] = 'No se pudo guardar el logo en el servidor.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $newLogoPath = 'public/uploads/system/' . $storedName;
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        "INSERT INTO system_profile (
            id,
            company_name,
            commercial_name,
            system_name,
            ruc,
            corporate_email,
            phone,
            address,
            website,
            description,
            slogan,
            logo_path,
            updated_by
        ) VALUES (
            1,
            :company_name,
            :commercial_name,
            :system_name,
            :ruc,
            :corporate_email,
            :phone,
            :address,
            :website,
            :description,
            :slogan,
            :logo_path,
            :updated_by
        )
        ON DUPLICATE KEY UPDATE
            company_name = VALUES(company_name),
            commercial_name = VALUES(commercial_name),
            system_name = VALUES(system_name),
            ruc = VALUES(ruc),
            corporate_email = VALUES(corporate_email),
            phone = VALUES(phone),
            address = VALUES(address),
            website = VALUES(website),
            description = VALUES(description),
            slogan = VALUES(slogan),
            logo_path = VALUES(logo_path),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP"
    );

    $statement->execute([
        'company_name' => $profileData['company_name'],
        'commercial_name' => $profileData['commercial_name'] !== '' ? $profileData['commercial_name'] : null,
        'system_name' => $profileData['system_name'],
        'ruc' => $profileData['ruc'] !== '' ? $profileData['ruc'] : null,
        'corporate_email' => $profileData['corporate_email'] !== '' ? $profileData['corporate_email'] : null,
        'phone' => $profileData['phone'] !== '' ? $profileData['phone'] : null,
        'address' => $profileData['address'] !== '' ? $profileData['address'] : null,
        'website' => $profileData['website'] !== '' ? $profileData['website'] : null,
        'description' => $profileData['description'] !== '' ? $profileData['description'] : null,
        'slogan' => $profileData['slogan'] !== '' ? $profileData['slogan'] : null,
        'logo_path' => $newLogoPath !== '' ? $newLogoPath : null,
        'updated_by' => $currentUserId > 0 ? $currentUserId : null,
    ]);

    $pdo->commit();

    if (
        $currentLogoPath !== ''
        && $currentLogoPath !== (string) $newLogoPath
        && systemProfileIsManagedUpload($currentLogoPath)
    ) {
        $oldLogoAbsolutePath = __DIR__ . '/' . ltrim($currentLogoPath, '/');

        if (is_file($oldLogoAbsolutePath)) {
            @unlink($oldLogoAbsolutePath);
        }
    }

    unset($_SESSION['system_profile_old']);
    $_SESSION['settings_success'] = 'El Perfil del sistema fue actualizado correctamente.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($newUploadedAbsolutePath !== null && is_file($newUploadedAbsolutePath)) {
        @unlink($newUploadedAbsolutePath);
    }

    $_SESSION['settings_error'] = 'No se pudieron guardar los cambios. Revisa la base de datos e inténtalo nuevamente.';
}

header('Location: ' . $redirectUrl);
exit;
