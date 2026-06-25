<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_sla.php';

requireLogin();

$currentUser = user();
if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

function redirectClients(): void
{
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

function clientCompanyColumnExists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `client_companies` LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveCompanyLogo(array $file): ?string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo cargar el logo de la empresa. Inténtalo nuevamente.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('El archivo del logo no es válido.');
    }

    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('El logo debe pesar como máximo 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException('El logo debe ser una imagen JPG, PNG o WebP.');
    }

    $uploadDirectory = __DIR__ . '/public/uploads/companies';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('No se pudo crear la carpeta para logos de empresas.');
    }

    $extension = $allowedTypes[$mime];
    $storedName = 'company_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $uploadDirectory . '/' . $storedName;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('No se pudo guardar el logo de la empresa.');
    }

    return 'public/uploads/companies/' . $storedName;
}

function deleteCompanyLogoFile(?string $relativePath): void
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '' || !str_starts_with($relativePath, 'public/uploads/companies/')) {
        return;
    }

    $fullPath = __DIR__ . '/' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$ruc = preg_replace('/\D+/', '', trim($_POST['ruc'] ?? ''));
$businessName = trim($_POST['business_name'] ?? '');
$tradeName = trim($_POST['trade_name'] ?? '');
$fiscalAddress = trim($_POST['fiscal_address'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$slaContractType = trim($_POST['sla_contract_type'] ?? '8_5');
$slaProfileId = isset($_POST['sla_profile_id']) && $_POST['sla_profile_id'] !== '' ? (int) $_POST['sla_profile_id'] : null;
$removeLogo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';
$newLogoPath = null;
$oldLogoPath = null;

try {
    if ($id <= 0) {
        throw new RuntimeException('Empresa cliente no válida.');
    }

    if ($businessName === '') {
        throw new RuntimeException('Ingresa la razón social de la empresa cliente.');
    }

    if ($ruc !== '' && strlen($ruc) !== 11) {
        throw new RuntimeException('El RUC debe tener 11 dígitos.');
    }

    $slaModuleReady = systemSlaModuleReady($pdo)
        && clientCompanyColumnExists($pdo, 'sla_profile_id');

    if ($slaModuleReady) {
        if ($slaProfileId === null || $slaProfileId <= 0) {
            $defaultProfile = systemSlaGetProfile($pdo, null);
            $slaProfileId = !empty($defaultProfile['id']) ? (int) $defaultProfile['id'] : null;
        }

        if ($slaProfileId !== null) {
            $profile = systemSlaGetProfile($pdo, $slaProfileId);
            if (empty($profile['id']) || (int)$profile['id'] !== (int)$slaProfileId || empty($profile['is_active'])) {
                throw new RuntimeException('Selecciona un perfil SLA activo.');
            }
            $slaContractType = ($profile['schedule_type'] ?? 'BUSINESS') === '24_7' ? '24_7' : '8_5';
        }
    }

    if (!in_array($slaContractType, ['24_7', '8_5'], true)) {
        throw new RuntimeException('Selecciona un tipo de contrato SLA válido.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo corporativo no es válido.');
    }

    $logoColumnReady = clientCompanyColumnExists($pdo, 'logo_path');
    $companySelect = $logoColumnReady
        ? 'SELECT id, logo_path FROM client_companies WHERE id = :id LIMIT 1'
        : 'SELECT id, NULL AS logo_path FROM client_companies WHERE id = :id LIMIT 1';

    $stmtCompany = $pdo->prepare($companySelect);
    $stmtCompany->execute(['id' => $id]);
    $existingCompany = $stmtCompany->fetch(PDO::FETCH_ASSOC);

    if (!$existingCompany) {
        throw new RuntimeException('La empresa cliente no existe.');
    }

    $oldLogoPath = trim((string)($existingCompany['logo_path'] ?? '')) ?: null;

    if ($ruc !== '') {
        $stmtDuplicate = $pdo->prepare('SELECT id FROM client_companies WHERE ruc = :ruc AND id <> :id LIMIT 1');
        $stmtDuplicate->execute(['ruc' => $ruc, 'id' => $id]);
        if ($stmtDuplicate->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Ya existe otra empresa cliente con ese RUC.');
        }
    }

    $newLogoPath = saveCompanyLogo($_FILES['company_logo'] ?? []);

    if (($newLogoPath !== null || $removeLogo) && !$logoColumnReady) {
        throw new RuntimeException('Primero ejecuta database/client_company_logo.sql para habilitar los logos.');
    }

    $finalLogoPath = $oldLogoPath;
    if ($newLogoPath !== null) {
        $finalLogoPath = $newLogoPath;
    } elseif ($removeLogo) {
        $finalLogoPath = null;
    }

    $assignments = [
        'ruc = :ruc',
        'business_name = :business_name',
        'trade_name = :trade_name',
        'fiscal_address = :fiscal_address',
        'phone = :phone',
        'email = :email',
        'sla_contract_type = :sla_contract_type',
        'updated_at = NOW()',
    ];
    $params = [
        'id' => $id,
        'ruc' => $ruc !== '' ? $ruc : null,
        'business_name' => $businessName,
        'trade_name' => $tradeName !== '' ? $tradeName : null,
        'fiscal_address' => $fiscalAddress !== '' ? $fiscalAddress : null,
        'phone' => $phone !== '' ? $phone : null,
        'email' => $email !== '' ? $email : null,
        'sla_contract_type' => $slaContractType,
    ];

    if ($logoColumnReady) {
        $assignments[] = 'logo_path = :logo_path';
        $params['logo_path'] = $finalLogoPath;
    }

    if ($slaModuleReady) {
        $assignments[] = 'sla_profile_id = :sla_profile_id';
        $params['sla_profile_id'] = $slaProfileId;
    }

    $stmt = $pdo->prepare(
        'UPDATE client_companies SET ' . implode(', ', $assignments) . ' WHERE id = :id'
    );
    $stmt->execute($params);

    if ($logoColumnReady && $oldLogoPath !== null && $oldLogoPath !== $finalLogoPath) {
        deleteCompanyLogoFile($oldLogoPath);
    }

    $_SESSION['client_success'] = 'Empresa cliente actualizada correctamente.';
    redirectClients();
} catch (Throwable $e) {
    if ($newLogoPath !== null && $newLogoPath !== $oldLogoPath) {
        deleteCompanyLogoFile($newLogoPath);
    }

    $_SESSION['client_error'] = $e->getMessage();
    redirectClients();
}
