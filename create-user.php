<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

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

if (!systemSecurityVerifyCsrf($_POST['csrf_token'] ?? null, 'create_user')) {
    $_SESSION['user_error'] = 'El formulario venció. Recarga la página e inténtalo nuevamente.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$securitySettings = getSystemSecuritySettings($pdo);

function redirectUsers(): void
{
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
    $stmt->execute(['table_name' => $table]);
    return (bool) $stmt->fetch(PDO::FETCH_NUM);
}

function tableColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchCompanyById(PDO $pdo, int $companyId): ?array
{
    $stmt = $pdo->prepare("SELECT id, business_name, trade_name FROM client_companies WHERE id = :id AND status = 1 LIMIT 1");
    $stmt->execute(['id' => $companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    return $company ?: null;
}

function fetchCompanyByRuc(PDO $pdo, string $ruc): ?array
{
    $stmt = $pdo->prepare("SELECT id, business_name, trade_name FROM client_companies WHERE ruc = :ruc LIMIT 1");
    $stmt->execute(['ruc' => $ruc]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    return $company ?: null;
}

function companyDisplayName(array $company): string
{
    return trim((string)($company['trade_name'] ?? '')) !== ''
        ? trim((string)$company['trade_name'])
        : trim((string)($company['business_name'] ?? ''));
}

function userPhoneCountryRules(): array
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

function normalizeUserPhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function validateUserPhoneByCountry(string $phone, string $countryCode): ?string
{
    $phone = trim($phone);

    if ($phone === '') {
        return null;
    }

    $rules = userPhoneCountryRules();
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


function normalizeUserPlainText(string $value): string
{
    $value = strip_tags($value);
    $collapsed = preg_replace('/\s+/u', ' ', $value);
    return trim($collapsed ?? $value);
}

function userTextLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function validateUserFullName(string $name): ?string
{
    $length = userTextLength($name);

    if ($length < 3 || $length > 80) {
        return 'El nombre completo debe tener entre 3 y 80 caracteres.';
    }

    if (!preg_match("/^[\\p{L}]+(?:[\\s'.-]+[\\p{L}]+)*$/u", $name)) {
        return 'El nombre completo solo debe contener letras, espacios, tildes, ñ, apóstrofes o guiones.';
    }

    return null;
}

function validateUserEmailAddress(string $email): ?string
{
    if (userTextLength($email) > 120) {
        return 'El correo no debe superar los 120 caracteres.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El correo no es válido.';
    }

    return null;
}

function validateUserPositionField(string $position): ?string
{
    if ($position === '') {
        return null;
    }

    $length = userTextLength($position);
    if ($length < 2 || $length > 80) {
        return 'El cargo debe tener entre 2 y 80 caracteres.';
    }

    if (!preg_match("/^[\\p{L}\\p{N}\\s().,\\/#&+_-]+$/u", $position)) {
        return 'El cargo contiene caracteres no permitidos.';
    }

    return null;
}

function validateUserBusinessNameField(string $value, string $label, bool $required = false): ?string
{
    if ($value === '') {
        return $required ? 'Ingresa ' . strtolower($label) . '.' : null;
    }

    $length = userTextLength($value);
    if ($length < 2 || $length > 150) {
        return $label . ' debe tener entre 2 y 150 caracteres.';
    }

    if (!preg_match("/^[\\p{L}\\p{N}\\s.,&'()\\/#_+-]+$/u", $value)) {
        return $label . ' contiene caracteres no permitidos.';
    }

    return null;
}

function validateUserAddressField(string $address): ?string
{
    if ($address === '') {
        return null;
    }

    $length = userTextLength($address);
    if ($length < 5 || $length > 180) {
        return 'La dirección fiscal debe tener entre 5 y 180 caracteres.';
    }

    if (!preg_match("/^[\\p{L}\\p{N}\\s.,;:º°ª'()\\/#_+-]+$/u", $address)) {
        return 'La dirección fiscal contiene caracteres no permitidos.';
    }

    return null;
}

function validateStrictDigitsField(string $rawValue, string $label, int $digits, bool $required = true): ?string
{
    $rawValue = trim($rawValue);

    if ($rawValue === '') {
        return $required ? $label . ' es obligatorio.' : null;
    }

    if (!preg_match('/^[0-9]+$/', $rawValue)) {
        return $label . ' solo debe contener números.';
    }

    if (strlen($rawValue) !== $digits) {
        return $label . ' debe tener exactamente ' . $digits . ' dígitos.';
    }

    return null;
}

$name = normalizeUserPlainText((string)($_POST['name'] ?? ''));
$email = strtolower(normalizeUserPlainText((string)($_POST['email'] ?? '')));
$role = trim((string)($_POST['role'] ?? ''));
$phoneCountry = trim((string)($_POST['phone_country'] ?? 'PE'));
$rawPhone = trim((string)($_POST['phone'] ?? ''));
$phone = normalizeUserPhone($rawPhone);
$position = normalizeUserPlainText((string)($_POST['position'] ?? ''));
$company = normalizeUserPlainText((string)($_POST['company'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
$techLevel = isset($_POST['tech_level']) ? (int) $_POST['tech_level'] : 1;

$companyMode = 'existing'; // Las empresas se crean desde admin-clients.php
$companyId = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;
$canViewCompanyTickets = isset($_POST['can_view_company_tickets']) ? 1 : 0;

$allowedRoles = $currentRole === 'ADMIN'
    ? ['CLIENT', 'TECH', 'ADMIN']
    : ['CLIENT'];

if ($name === '' || $email === '' || !in_array($role, $allowedRoles, true)) {
    $_SESSION['user_error'] = $currentRole === 'TECH'
        ? 'Completa correctamente los campos. Un técnico solo puede crear usuarios clientes.'
        : 'Completa correctamente los campos obligatorios.';
    redirectUsers();
}

$nameError = validateUserFullName($name);
if ($nameError !== null) {
    $_SESSION['user_error'] = $nameError;
    redirectUsers();
}

$emailError = validateUserEmailAddress($email);
if ($emailError !== null) {
    $_SESSION['user_error'] = $emailError;
    redirectUsers();
}

$positionError = validateUserPositionField($position);
if ($positionError !== null) {
    $_SESSION['user_error'] = $positionError;
    redirectUsers();
}

$legacyCompanyError = validateUserBusinessNameField($company, 'La empresa');
if ($legacyCompanyError !== null) {
    $_SESSION['user_error'] = $legacyCompanyError;
    redirectUsers();
}

$phoneError = validateUserPhoneByCountry($rawPhone, $phoneCountry);
if ($phoneError !== null) {
    $_SESSION['user_error'] = $phoneError;
    redirectUsers();
}

$passwordErrors = systemSecurityPasswordErrors($password, $securitySettings, [
    'name' => $name,
    'email' => $email,
]);

if ($passwordErrors) {
    $_SESSION['user_error'] = implode(' ', $passwordErrors);
    redirectUsers();
}

if ($password !== $passwordConfirmation) {
    $_SESSION['user_error'] = 'Las contraseñas no coinciden.';
    redirectUsers();
}

if ($role === 'TECH' && !in_array($techLevel, [1, 2, 3], true)) {
    $_SESSION['user_error'] = 'Selecciona un nivel técnico válido.';
    redirectUsers();
}

$stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$stmtCheck->execute(['email' => $email]);

if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
    $_SESSION['user_error'] = 'Ya existe un usuario con ese correo.';
    redirectUsers();
}

$passwordColumn = null;
foreach (['password', 'password_hash'] as $candidate) {
    if (tableColumnExists($pdo, 'users', $candidate)) {
        $passwordColumn = $candidate;
        break;
    }
}

if ($passwordColumn === null) {
    $_SESSION['user_error'] = 'No se encontró una columna de contraseña en la tabla users.';
    redirectUsers();
}

$hasClientCompanies = tableExists($pdo, 'client_companies');
$hasCompanyId = tableColumnExists($pdo, 'users', 'company_id');
$hasCanViewCompanyTickets = tableColumnExists($pdo, 'users', 'can_view_company_tickets');
$companyModuleReady = $hasClientCompanies && $hasCompanyId;

$finalCompanyId = null;
$finalCompanyName = $company !== '' ? $company : null;
$finalCanViewCompanyTickets = 0;

try {
    $pdo->beginTransaction();

    if ($role === 'CLIENT') {
        if ($companyModuleReady) {
            if ($companyMode === 'new') {
                if ($currentRole !== 'ADMIN') {
                    throw new RuntimeException('Solo un administrador puede registrar nuevas empresas cliente.');
                }

                $rawNewCompanyRuc = trim((string)($_POST['new_company_ruc'] ?? ''));
                $newCompanyRuc = preg_replace('/\D+/', '', $rawNewCompanyRuc) ?? '';
                $newCompanyBusinessName = normalizeUserPlainText((string)($_POST['new_company_business_name'] ?? ''));
                $newCompanyTradeName = normalizeUserPlainText((string)($_POST['new_company_trade_name'] ?? ''));
                $newCompanyFiscalAddress = normalizeUserPlainText((string)($_POST['new_company_fiscal_address'] ?? ''));
                $newCompanyPhoneCountry = trim((string)($_POST['new_company_phone_country'] ?? 'PE'));
                $rawNewCompanyPhone = trim((string)($_POST['new_company_phone'] ?? ''));
                $newCompanyPhone = normalizeUserPhone($rawNewCompanyPhone);
                $newCompanyEmail = strtolower(normalizeUserPlainText((string)($_POST['new_company_email'] ?? '')));
                $newCompanySlaContractType = trim((string)($_POST['new_company_sla_contract_type'] ?? '8_5'));

                $rucError = validateStrictDigitsField($rawNewCompanyRuc, 'El RUC de la empresa', 11, true);
                if ($rucError !== null) {
                    throw new RuntimeException($rucError);
                }

                $businessNameError = validateUserBusinessNameField($newCompanyBusinessName, 'La razón social', true);
                if ($businessNameError !== null) {
                    throw new RuntimeException($businessNameError);
                }

                $tradeNameError = validateUserBusinessNameField($newCompanyTradeName, 'El nombre comercial');
                if ($tradeNameError !== null) {
                    throw new RuntimeException($tradeNameError);
                }

                $addressError = validateUserAddressField($newCompanyFiscalAddress);
                if ($addressError !== null) {
                    throw new RuntimeException($addressError);
                }

                if (!in_array($newCompanySlaContractType, ['24_7', '8_5'], true)) {
                    throw new RuntimeException('Selecciona un tipo de contrato SLA válido.');
                }

                if ($newCompanyEmail !== '') {
                    $companyEmailError = validateUserEmailAddress($newCompanyEmail);
                    if ($companyEmailError !== null) {
                        throw new RuntimeException('Correo corporativo: ' . $companyEmailError);
                    }
                }

                $newCompanyPhoneError = validateUserPhoneByCountry($rawNewCompanyPhone, $newCompanyPhoneCountry);
                if ($newCompanyPhoneError !== null) {
                    throw new RuntimeException('Teléfono de empresa: ' . $newCompanyPhoneError);
                }

                $existingCompany = fetchCompanyByRuc($pdo, $newCompanyRuc);

                if ($existingCompany) {
                    $finalCompanyId = (int) $existingCompany['id'];
                    $finalCompanyName = companyDisplayName($existingCompany);
                } else {
                    $stmtCompany = $pdo->prepare("INSERT INTO client_companies
                        (ruc, business_name, trade_name, fiscal_address, phone, email, sla_contract_type, status, created_at)
                        VALUES
                        (:ruc, :business_name, :trade_name, :fiscal_address, :phone, :email, :sla_contract_type, 1, NOW())");

                    $stmtCompany->execute([
                        'ruc' => $newCompanyRuc,
                        'business_name' => $newCompanyBusinessName,
                        'trade_name' => $newCompanyTradeName !== '' ? $newCompanyTradeName : null,
                        'fiscal_address' => $newCompanyFiscalAddress !== '' ? $newCompanyFiscalAddress : null,
                        'phone' => $newCompanyPhone !== '' ? $newCompanyPhone : null,
                        'email' => $newCompanyEmail !== '' ? $newCompanyEmail : null,
                        'sla_contract_type' => $newCompanySlaContractType,
                    ]);

                    $finalCompanyId = (int) $pdo->lastInsertId();
                    $finalCompanyName = $newCompanyTradeName !== '' ? $newCompanyTradeName : $newCompanyBusinessName;
                }
            } else {
                if ($companyId <= 0) {
                    throw new RuntimeException('Selecciona una empresa cliente para este contacto.');
                }

                $selectedCompany = fetchCompanyById($pdo, $companyId);
                if (!$selectedCompany) {
                    throw new RuntimeException('La empresa cliente seleccionada no existe o está inactiva.');
                }

                $finalCompanyId = (int) $selectedCompany['id'];
                $finalCompanyName = companyDisplayName($selectedCompany);
            }

            $finalCanViewCompanyTickets = $canViewCompanyTickets;
        } else {
            if ($finalCompanyName === null) {
                throw new RuntimeException('Ingresa la empresa del cliente.');
            }
        }
    } else {
        $finalCompanyId = null;
        $finalCompanyName = null;
        $finalCanViewCompanyTickets = 0;
    }

    $hasStatus = tableColumnExists($pdo, 'users', 'status');
    $hasTechLevel = tableColumnExists($pdo, 'users', 'tech_level');
    $hasCreatedAt = tableColumnExists($pdo, 'users', 'created_at');
    $hasForcePasswordChange = tableColumnExists($pdo, 'users', 'force_password_change');
    $hasPasswordChangedAt = tableColumnExists($pdo, 'users', 'password_changed_at');

    $columns = ['name', 'email', 'role', $passwordColumn, 'phone', 'position', 'company'];
    $placeholders = [':name', ':email', ':role', ':password', ':phone', ':position', ':company'];
    $params = [
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $phone !== '' ? $phone : null,
        'position' => $position !== '' ? $position : null,
        'company' => $finalCompanyName,
    ];

    if ($hasCompanyId) {
        $columns[] = 'company_id';
        $placeholders[] = ':company_id';
        $params['company_id'] = $finalCompanyId;
    }

    if ($hasCanViewCompanyTickets) {
        $columns[] = 'can_view_company_tickets';
        $placeholders[] = ':can_view_company_tickets';
        $params['can_view_company_tickets'] = $finalCanViewCompanyTickets;
    }

    if ($hasStatus) {
        $columns[] = 'status';
        $placeholders[] = ':status';
        $params['status'] = 1;
    }

    if ($hasTechLevel) {
        $columns[] = 'tech_level';
        $placeholders[] = ':tech_level';
        $params['tech_level'] = $role === 'TECH' ? $techLevel : null;
    }

    if ($hasForcePasswordChange) {
        $columns[] = 'force_password_change';
        $placeholders[] = ':force_password_change';
        $params['force_password_change'] = !empty($securitySettings['force_change_on_create']) ? 1 : 0;
    }

    if ($hasPasswordChangedAt) {
        $columns[] = 'password_changed_at';
        $placeholders[] = 'NOW()';
    }

    if ($hasCreatedAt) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }

    $sql = 'INSERT INTO users (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $createdUserId = (int) $pdo->lastInsertId();

    systemSecurityAudit(
        $pdo,
        'USER_CREATED',
        'Se creó una nueva cuenta de usuario.',
        $createdUserId,
        (int) ($currentUser['id'] ?? 0),
        'info',
        ['role' => $role, 'force_password_change' => !empty($securitySettings['force_change_on_create'])]
    );

    $pdo->commit();

    $_SESSION['user_success'] = $role === 'CLIENT'
        ? 'Contacto cliente creado y vinculado a la empresa correctamente.'
        : 'Usuario creado correctamente.';
    redirectUsers();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['user_error'] = $e->getMessage();
    redirectUsers();
}
