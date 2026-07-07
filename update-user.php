<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';
$managerRoles = ['ADMIN', 'TECH'];

if (!in_array($currentRole, $managerRoles, true)) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

function redirectUserEdit(int $id): void
{
    header('Location: /helpdesk-php/edit-user.php?id=' . $id);
    exit;
}

function updateUserTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
    $stmt->execute(['table_name' => $table]);
    return (bool) $stmt->fetch(PDO::FETCH_NUM);
}

function updateUserColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserFetchCompanyById(PDO $pdo, int $companyId): ?array
{
    $stmt = $pdo->prepare("SELECT id, business_name, trade_name FROM client_companies WHERE id = :id AND status = 1 LIMIT 1");
    $stmt->execute(['id' => $companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    return $company ?: null;
}

function updateUserCompanyDisplayName(array $company): string
{
    return trim((string)($company['trade_name'] ?? '')) !== ''
        ? trim((string)$company['trade_name'])
        : trim((string)($company['business_name'] ?? ''));
}

function updateUserPhoneCountryRules(): array
{
    return [
        'PE' => ['label' => 'Perú', 'digits' => 9],
        'CO' => ['label' => 'Colombia', 'digits' => 10],
        'MX' => ['label' => 'México', 'digits' => 10],
        'CL' => ['label' => 'Chile', 'digits' => 9],
        'EC' => ['label' => 'Ecuador', 'digits' => 10],
        'BO' => ['label' => 'Bolivia', 'digits' => 8],
        'AR' => ['label' => 'Argentina', 'digits' => 10],
        'US' => ['label' => 'Estados Unidos', 'digits' => 10],
    ];
}

function updateUserNormalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function updateUserValidatePhoneByCountry(string $phone, string $countryCode): ?string
{
    $phone = trim($phone);

    if ($phone === '') {
        return null;
    }

    $rules = updateUserPhoneCountryRules();
    $countryCode = array_key_exists($countryCode, $rules) ? $countryCode : 'PE';
    $rule = $rules[$countryCode];
    $digits = (int)$rule['digits'];

    if (!preg_match('/^[0-9]+$/', $phone)) {
        return 'El teléfono solo debe contener números. No se permiten letras, espacios ni símbolos.';
    }

    if (strlen($phone) !== $digits) {
        return 'El teléfono de ' . $rule['label'] . ' debe contener exactamente ' . $digits . ' números.';
    }

    return null;
}


function updateUserNormalizePlainText(string $value): string
{
    $value = strip_tags($value);
    $collapsed = preg_replace('/\s+/u', ' ', $value);
    return trim($collapsed ?? $value);
}

function updateUserTextLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function updateUserValidateFullName(string $name): ?string
{
    $length = updateUserTextLength($name);

    if ($length < 3 || $length > 80) {
        return 'El nombre completo debe tener entre 3 y 80 caracteres.';
    }

    if (!preg_match("/^[\\p{L}]+(?:[\\s'.-]+[\\p{L}]+)*$/u", $name)) {
        return 'El nombre completo solo debe contener letras, espacios, tildes, ñ, apóstrofes o guiones.';
    }

    return null;
}

function updateUserValidateEmailAddress(string $email): ?string
{
    if (updateUserTextLength($email) > 120) {
        return 'El correo no debe superar los 120 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El correo no es válido.';
    }

    return null;
}

function updateUserValidatePositionField(string $position): ?string
{
    if ($position === '') {
        return null;
    }

    $length = updateUserTextLength($position);
    if ($length < 2 || $length > 80) {
        return 'El cargo debe tener entre 2 y 80 caracteres.';
    }

    if (!preg_match("/^[\\p{L}\\p{N}\\s().,\\/#&+_-]+$/u", $position)) {
        return 'El cargo contiene caracteres no permitidos.';
    }

    return null;
}

function updateUserValidateBusinessNameField(string $value, string $label, bool $required = false): ?string
{
    if ($value === '') {
        return $required ? 'Ingresa ' . strtolower($label) . '.' : null;
    }

    $length = updateUserTextLength($value);
    if ($length < 2 || $length > 150) {
        return $label . ' debe tener entre 2 y 150 caracteres.';
    }

    if (!preg_match("/^[\\p{L}\\p{N}\\s.,&'()\\/#_+-]+$/u", $value)) {
        return $label . ' contiene caracteres no permitidos.';
    }

    return null;
}

function updateUserDeleteLocalPhoto(?string $photo): void
{
    $photo = trim((string)$photo);

    if ($photo === '' || preg_match('/^https?:\/\//i', $photo)) {
        return;
    }

    $projectRoot = __DIR__;
    $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'users';
    $relativePath = ltrim($photo, '/');

    if (str_starts_with($relativePath, 'public/')) {
        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    } else {
        $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . basename($relativePath);
    }

    $realUploadDir = realpath($uploadDir);
    $realFile = realpath($absolutePath);

    if ($realUploadDir && $realFile && str_starts_with($realFile, $realUploadDir) && is_file($realFile)) {
        @unlink($realFile);
    }
}


function updateUserRefreshCurrentSession(PDO $pdo, int $updatedUserId, array $currentUser, array $availableColumns): void
{
    $currentSessionUserId = (int)($currentUser['id'] ?? ($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0)));

    if ($currentSessionUserId <= 0 || $currentSessionUserId !== $updatedUserId) {
        return;
    }

    $selectColumns = [
        'id',
        'name',
        'email',
        'role',
        'status',
        'phone',
        'position',
        'company',
    ];

    if (!empty($availableColumns['tech_level'])) {
        $selectColumns[] = 'tech_level';
    }

    if (!empty($availableColumns['company_id'])) {
        $selectColumns[] = 'company_id';
    }

    if (!empty($availableColumns['can_view_company_tickets'])) {
        $selectColumns[] = 'can_view_company_tickets';
    }

    if (!empty($availableColumns['profile_photo'])) {
        $selectColumns[] = 'profile_photo';
    }

    $sql = 'SELECT ' . implode(', ', array_map(fn($column) => "`$column`", $selectColumns)) . ' FROM users WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $updatedUserId]);
    $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$freshUser) {
        return;
    }

    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $_SESSION['user'] = array_merge($_SESSION['user'], $freshUser);
    } else {
        $_SESSION['user'] = $freshUser;
    }

    // Compatibilidad con distintos formatos de sesión usados en el proyecto.
    $_SESSION['user_id'] = (int)$freshUser['id'];
    $_SESSION['user_name'] = (string)$freshUser['name'];
    $_SESSION['user_email'] = (string)$freshUser['email'];
    $_SESSION['user_role'] = (string)$freshUser['role'];

    $_SESSION['name'] = (string)$freshUser['name'];
    $_SESSION['email'] = (string)$freshUser['email'];
    $_SESSION['role'] = (string)$freshUser['role'];

    if (array_key_exists('profile_photo', $freshUser)) {
        $_SESSION['profile_photo'] = $freshUser['profile_photo'];
        $_SESSION['user_photo'] = $freshUser['profile_photo'];
        $_SESSION['user_profile_photo'] = $freshUser['profile_photo'];
    }
}

function updateUserHandleProfilePhoto(int $userId): ?string
{
    if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
        return null;
    }

    $file = $_FILES['profile_photo'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la foto. Inténtalo nuevamente.');
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('La foto no debe superar los 2 MB.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('El archivo subido no es válido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('La foto debe ser JPG, PNG o WEBP.');
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'users';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta para fotos de usuario.');
    }

    $extension = $allowedMimeTypes[$mimeType];
    $fileName = 'user_' . $userId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('No se pudo guardar la foto en el servidor.');
    }

    return 'public/uploads/users/' . $fileName;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name = updateUserNormalizePlainText((string)($_POST['name'] ?? ''));
$email = strtolower(updateUserNormalizePlainText((string)($_POST['email'] ?? '')));
$role = trim((string)($_POST['role'] ?? ''));
$phoneCountry = trim((string)($_POST['phone_country'] ?? 'PE'));
$rawPhone = trim((string)($_POST['phone'] ?? ''));
$phone = updateUserNormalizePhone($rawPhone);
$position = updateUserNormalizePlainText((string)($_POST['position'] ?? ''));
$company = updateUserNormalizePlainText((string)($_POST['company'] ?? ''));
$techLevel = isset($_POST['tech_level']) ? (int) $_POST['tech_level'] : 1;

if ($techLevel < 1 || $techLevel > 3) {
    $techLevel = 1;
}

$allowedRoles = $currentRole === 'ADMIN'
    ? ['CLIENT', 'TECH', 'ADMIN']
    : ['CLIENT'];

if ($id <= 0 || $name === '' || $email === '' || !in_array($role, $allowedRoles, true)) {
    $_SESSION['user_error'] = $currentRole === 'TECH'
        ? 'Completa correctamente los campos. Un técnico solo puede gestionar usuarios clientes.'
        : 'Completa correctamente los campos obligatorios.';
    redirectUserEdit($id);
}

$nameError = updateUserValidateFullName($name);
if ($nameError !== null) {
    $_SESSION['user_error'] = $nameError;
    redirectUserEdit($id);
}

$emailError = updateUserValidateEmailAddress($email);
if ($emailError !== null) {
    $_SESSION['user_error'] = $emailError;
    redirectUserEdit($id);
}

$positionError = updateUserValidatePositionField($position);
if ($positionError !== null) {
    $_SESSION['user_error'] = $positionError;
    redirectUserEdit($id);
}

$legacyCompanyError = updateUserValidateBusinessNameField($company, 'La empresa');
if ($legacyCompanyError !== null) {
    $_SESSION['user_error'] = $legacyCompanyError;
    redirectUserEdit($id);
}

$phoneError = updateUserValidatePhoneByCountry($rawPhone, $phoneCountry);
if ($phoneError !== null) {
    $_SESSION['user_error'] = $phoneError;
    redirectUserEdit($id);
}

$hasClientCompanies = updateUserTableExists($pdo, 'client_companies');
$hasCompanyId = updateUserColumnExists($pdo, 'users', 'company_id');
$hasCanViewCompanyTickets = updateUserColumnExists($pdo, 'users', 'can_view_company_tickets');
$hasTechLevel = updateUserColumnExists($pdo, 'users', 'tech_level');
$hasProfilePhoto = updateUserColumnExists($pdo, 'users', 'profile_photo');
$companyModuleReady = $hasClientCompanies && $hasCompanyId;

$stmtTarget = $pdo->prepare("SELECT id, role, " . ($hasProfilePhoto ? "profile_photo" : "NULL AS profile_photo") . " FROM users WHERE id = :id LIMIT 1");
$stmtTarget->execute(['id' => $id]);
$targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    $_SESSION['user_error'] = 'El usuario seleccionado no existe.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

if ($currentRole === 'TECH' && ($targetUser['role'] ?? '') !== 'CLIENT') {
    $_SESSION['user_error'] = 'No puedes editar usuarios administradores o técnicos.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$sqlCheck = "SELECT id
             FROM users
             WHERE email = :email
               AND id <> :id
             LIMIT 1";

$stmtCheck = $pdo->prepare($sqlCheck);
$stmtCheck->execute([
    'email' => $email,
    'id' => $id
]);

if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
    $_SESSION['user_error'] = 'Ya existe otro usuario con ese correo.';
    redirectUserEdit($id);
}

$setParts = [
    'name = :name',
    'email = :email',
    'role = :role',
    'phone = :phone',
    'position = :position',
    'company = :company',
];

$params = [
    'id' => $id,
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'phone' => $phone !== '' ? $phone : null,
    'position' => $position !== '' ? $position : null,
    'company' => $company !== '' ? $company : null,
];

if ($hasTechLevel) {
    $setParts[] = 'tech_level = :tech_level';
    $params['tech_level'] = $role === 'TECH' ? $techLevel : null;
}

if ($role === 'CLIENT' && $companyModuleReady) {
    $postedCompanyId = (int) ($_POST['company_id'] ?? 0);

    if ($postedCompanyId <= 0) {
        $_SESSION['user_error'] = 'Selecciona una empresa cliente para este contacto.';
        redirectUserEdit($id);
    }

    $selectedCompany = updateUserFetchCompanyById($pdo, $postedCompanyId);
    if (!$selectedCompany) {
        $_SESSION['user_error'] = 'La empresa cliente seleccionada no existe o está inactiva.';
        redirectUserEdit($id);
    }

    $setParts[] = 'company_id = :company_id';
    $params['company_id'] = (int) $selectedCompany['id'];
    $params['company'] = updateUserCompanyDisplayName($selectedCompany);
} elseif ($role !== 'CLIENT') {
    if ($hasCompanyId) {
        $setParts[] = 'company_id = NULL';
    }

    $params['company'] = null;
}

if ($hasCanViewCompanyTickets) {
    $setParts[] = 'can_view_company_tickets = :can_view_company_tickets';
    $params['can_view_company_tickets'] = $role === 'CLIENT' && isset($_POST['can_view_company_tickets']) ? 1 : 0;
}

$newProfilePhoto = null;
$removeProfilePhoto = isset($_POST['remove_profile_photo']);

if ($hasProfilePhoto) {
    try {
        $newProfilePhoto = updateUserHandleProfilePhoto($id);
    } catch (Throwable $e) {
        $_SESSION['user_error'] = $e->getMessage();
        redirectUserEdit($id);
    }

    if ($newProfilePhoto !== null) {
        $setParts[] = 'profile_photo = :profile_photo';
        $params['profile_photo'] = $newProfilePhoto;
    } elseif ($removeProfilePhoto) {
        $setParts[] = 'profile_photo = NULL';
    }
}

$sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($hasProfilePhoto && ($newProfilePhoto !== null || $removeProfilePhoto)) {
        updateUserDeleteLocalPhoto($targetUser['profile_photo'] ?? null);
    }

    updateUserRefreshCurrentSession($pdo, $id, $currentUser, [
        'tech_level' => $hasTechLevel,
        'company_id' => $hasCompanyId,
        'can_view_company_tickets' => $hasCanViewCompanyTickets,
        'profile_photo' => $hasProfilePhoto,
    ]);

    $_SESSION['user_success'] = 'Usuario actualizado correctamente.';
} catch (Throwable $e) {
    if ($newProfilePhoto !== null) {
        updateUserDeleteLocalPhoto($newProfilePhoto);
    }

    $_SESSION['user_error'] = 'No se pudo actualizar el usuario. Revisa los datos e inténtalo nuevamente.';
    redirectUserEdit($id);
}

header('Location: /helpdesk-php/admin-users.php');
exit;
