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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['user_error'] = 'Selecciona un usuario válido para editar.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

function editUserControllerTableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
        $stmt->execute(['table_name' => $table]);
        return (bool) $stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $e) {
        return false;
    }
}

function editUserControllerColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
        $stmt->execute(['column' => $column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function editUserControllerInferPhoneCountry(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    $length = strlen($digits);

    return match ($length) {
        8 => 'BO',
        9 => 'PE',
        10 => 'CO',
        default => 'PE',
    };
}

$hasClientCompanies = editUserControllerTableExists($pdo, 'client_companies');
$hasCompanyIdColumn = editUserControllerColumnExists($pdo, 'users', 'company_id');
$hasCanViewCompanyTicketsColumn = editUserControllerColumnExists($pdo, 'users', 'can_view_company_tickets');
$hasTechLevelColumn = editUserControllerColumnExists($pdo, 'users', 'tech_level');
$hasProfilePhotoColumn = editUserControllerColumnExists($pdo, 'users', 'profile_photo');
$companyModuleReady = $hasClientCompanies && $hasCompanyIdColumn;

$selectCompanyColumns = $companyModuleReady
    ? "u.company_id,
       cc.ruc AS company_ruc,
       cc.business_name AS company_business_name,
       cc.trade_name AS company_trade_name,
       cc.sla_contract_type AS sla_contract_type,"
    : "NULL AS company_id,
       NULL AS company_ruc,
       NULL AS company_business_name,
       NULL AS company_trade_name,
       NULL AS sla_contract_type,";

$sql = "SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            u.status,
            u.phone,
            u.position,
            u.company,
            " . ($hasProfilePhotoColumn ? "u.profile_photo" : "NULL AS profile_photo") . ",
            " . ($hasTechLevelColumn ? "u.tech_level" : "NULL AS tech_level") . ",
            " . ($hasCanViewCompanyTicketsColumn ? "u.can_view_company_tickets" : "0 AS can_view_company_tickets") . ",
            u.created_at,
            $selectCompanyColumns
            1 AS row_marker
        FROM users u";

if ($companyModuleReady) {
    $sql .= " LEFT JOIN client_companies cc ON cc.id = u.company_id";
}

$sql .= " WHERE u.id = :id LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$userItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userItem) {
    $_SESSION['user_error'] = 'El usuario seleccionado no existe.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

if ($currentRole === 'TECH' && ($userItem['role'] ?? '') !== 'CLIENT') {
    $_SESSION['user_error'] = 'No puedes editar usuarios administradores o técnicos.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$companyOptions = [];
if ($hasClientCompanies) {
    try {
        $stmtCompanies = $pdo->query("SELECT id, ruc, business_name, trade_name, sla_contract_type
                                      FROM client_companies
                                      WHERE status = 1
                                      ORDER BY COALESCE(NULLIF(trade_name, ''), business_name) ASC");
        $companyOptions = $stmtCompanies->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $companyOptions = [];
    }
}

$allowedRoles = $currentRole === 'ADMIN'
    ? ['CLIENT', 'TECH', 'ADMIN']
    : ['CLIENT'];

$selectedPhoneCountry = editUserControllerInferPhoneCountry($userItem['phone'] ?? '');

require __DIR__ . '/app/views/admin/edit-user.php';
