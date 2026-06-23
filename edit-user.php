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

function editUserTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
    $stmt->execute(['table_name' => $table]);
    return (bool) $stmt->fetch(PDO::FETCH_NUM);
}

function editUserColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function editUserSlaContractLabel(?string $contract): string
{
    return match ($contract) {
        '24_7' => '24/7',
        '8_5' => '8/5',
        default => '-',
    };
}

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($userId <= 0) {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$hasClientCompanies = editUserTableExists($pdo, 'client_companies');
$hasCompanyIdColumn = editUserColumnExists($pdo, 'users', 'company_id');
$hasCanViewCompanyTicketsColumn = editUserColumnExists($pdo, 'users', 'can_view_company_tickets');
$hasTechLevelColumn = editUserColumnExists($pdo, 'users', 'tech_level');
$hasProfilePhotoColumn = editUserColumnExists($pdo, 'users', 'profile_photo');
$companyModuleReady = $hasClientCompanies && $hasCompanyIdColumn;

$companyOptions = [];
if ($hasClientCompanies) {
    $stmtCompanies = $pdo->query("SELECT id, ruc, business_name, trade_name, sla_contract_type
                                  FROM client_companies
                                  WHERE status = 1
                                  ORDER BY business_name ASC");
    $companyOptions = $stmtCompanies->fetchAll(PDO::FETCH_ASSOC);
}

if ($companyModuleReady) {
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
                u.company_id,
                " . ($hasCanViewCompanyTicketsColumn ? "u.can_view_company_tickets" : "0 AS can_view_company_tickets") . ",
                cc.ruc AS company_ruc,
                cc.business_name AS company_business_name,
                cc.trade_name AS company_trade_name,
                cc.sla_contract_type AS sla_contract_type
            FROM users u
            LEFT JOIN client_companies cc ON cc.id = u.company_id
            WHERE u.id = :id
            LIMIT 1";
} else {
    $sql = "SELECT
                id,
                name,
                email,
                role,
                status,
                phone,
                position,
                company,
                " . ($hasProfilePhotoColumn ? "profile_photo" : "NULL AS profile_photo") . ",
                " . ($hasTechLevelColumn ? "tech_level" : "NULL AS tech_level") . ",
                NULL AS company_id,
                0 AS can_view_company_tickets,
                NULL AS company_ruc,
                NULL AS company_business_name,
                NULL AS company_trade_name,
                NULL AS sla_contract_type
            FROM users
            WHERE id = :id
            LIMIT 1";
}

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $userId]);
$userItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userItem) {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

if ($currentRole === 'TECH' && ($userItem['role'] ?? '') !== 'CLIENT') {
    $_SESSION['user_error'] = 'No puedes editar usuarios administradores o técnicos.';
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$allowedRoles = $currentRole === 'ADMIN'
    ? ['CLIENT', 'TECH', 'ADMIN']
    : ['CLIENT'];

require __DIR__ . '/app/views/admin/edit-user.php';
