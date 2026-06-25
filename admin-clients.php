<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
<<<<<<< HEAD
=======
require_once __DIR__ . '/app/helpers/system_sla.php';
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';
$allowedRoles = ['ADMIN', 'TECH'];

if (!in_array($currentRole, $allowedRoles, true)) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

function clientCompaniesTableExists(PDO $pdo, string $table): bool
{
<<<<<<< HEAD
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
=======
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
    $stmt->execute(['table_name' => $table]);
    return (bool) $stmt->fetch(PDO::FETCH_NUM);
}

function clientCompaniesColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
    $stmt->execute(['column' => $column]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function clientSlaContractLabel(?string $contract): string
{
    return match ($contract) {
        '24_7' => '24/7',
        '8_5' => '8/5',
        default => '-',
    };
}

function clientSlaContractDescription(?string $contract): string
{
    return match ($contract) {
        '24_7' => 'Atención continua, todos los días y horas.',
        '8_5' => 'Horario laboral: lunes a viernes.',
        default => 'Contrato no definido.',
    };
}

$search = trim($_GET['search'] ?? '');
$sla = trim($_GET['sla'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$companyModuleReady = clientCompaniesTableExists($pdo, 'client_companies');
$hasUserCompanyId = clientCompaniesColumnExists($pdo, 'users', 'company_id');
<<<<<<< HEAD
$hasTicketCompanyId = clientCompaniesTableExists($pdo, 'tickets') && clientCompaniesColumnExists($pdo, 'tickets', 'company_id');
=======
$hasTicketCompanyId = clientCompaniesTableExists($pdo, 'tickets')
    && clientCompaniesColumnExists($pdo, 'tickets', 'company_id');
$companyLogoColumnReady = $companyModuleReady
    && clientCompaniesColumnExists($pdo, 'client_companies', 'logo_path');
$slaProfilesReady = $companyModuleReady
    && systemSlaModuleReady($pdo)
    && clientCompaniesColumnExists($pdo, 'client_companies', 'sla_profile_id');
$availableSlaProfiles = $slaProfilesReady ? systemSlaProfiles($pdo, true) : [];
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
$canManageClients = $currentRole === 'ADMIN';

$clients = [];
$summary = [
    'total' => 0,
    'active' => 0,
    'contract_24_7' => 0,
    'contract_8_5' => 0,
];

if ($companyModuleReady) {
    $contactsCountSql = $hasUserCompanyId
        ? "(SELECT COUNT(*) FROM users u WHERE u.company_id = cc.id AND u.role = 'CLIENT') AS contacts_count"
<<<<<<< HEAD
        : "0 AS contacts_count";

    $ticketsCountSql = $hasTicketCompanyId
        ? "(SELECT COUNT(*) FROM tickets t WHERE t.company_id = cc.id) AS tickets_count"
        : "0 AS tickets_count";

    $openTicketsCountSql = $hasTicketCompanyId
        ? "(SELECT COUNT(*) FROM tickets t WHERE t.company_id = cc.id AND t.status <> 'CERRADO') AS open_tickets_count"
        : "0 AS open_tickets_count";
=======
        : '0 AS contacts_count';

    $ticketsCountSql = $hasTicketCompanyId
        ? '(SELECT COUNT(*) FROM tickets t WHERE t.company_id = cc.id) AS tickets_count'
        : '0 AS tickets_count';

    $openTicketsCountSql = $hasTicketCompanyId
        ? "(SELECT COUNT(*) FROM tickets t WHERE t.company_id = cc.id AND t.status <> 'CERRADO') AS open_tickets_count"
        : '0 AS open_tickets_count';

    $logoSelectSql = $companyLogoColumnReady
        ? 'cc.logo_path'
        : 'NULL AS logo_path';

    $slaProfileSelectSql = $slaProfilesReady
        ? "cc.sla_profile_id, sp.name AS sla_profile_name, sp.schedule_type AS sla_profile_schedule_type, sp.work_start AS sla_profile_work_start, sp.work_end AS sla_profile_work_end, sp.work_days AS sla_profile_work_days"
        : "NULL AS sla_profile_id, NULL AS sla_profile_name, NULL AS sla_profile_schedule_type, NULL AS sla_profile_work_start, NULL AS sla_profile_work_end, NULL AS sla_profile_work_days";

    $slaProfileJoinSql = $slaProfilesReady ? 'LEFT JOIN sla_profiles sp ON sp.id = cc.sla_profile_id' : '';
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)

    $sql = "SELECT
                cc.id,
                cc.ruc,
                cc.business_name,
                cc.trade_name,
                cc.fiscal_address,
                cc.phone,
                cc.email,
                cc.sla_contract_type,
                cc.status,
                cc.created_at,
                cc.updated_at,
<<<<<<< HEAD
=======
                $logoSelectSql,
                $slaProfileSelectSql,
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
                $contactsCountSql,
                $ticketsCountSql,
                $openTicketsCountSql
            FROM client_companies cc
<<<<<<< HEAD
=======
            $slaProfileJoinSql
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
            WHERE 1=1";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (
                    cc.ruc LIKE :search
                    OR cc.business_name LIKE :search
                    OR cc.trade_name LIKE :search
                    OR cc.email LIKE :search
                    OR cc.phone LIKE :search
                  )";
        $params['search'] = '%' . $search . '%';
    }

    if (in_array($sla, ['24_7', '8_5'], true)) {
<<<<<<< HEAD
        $sql .= " AND cc.sla_contract_type = :sla";
=======
        $sql .= ' AND cc.sla_contract_type = :sla';
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)
        $params['sla'] = $sla;
    }

    if ($statusFilter !== '' && in_array($statusFilter, ['1', '0'], true)) {
<<<<<<< HEAD
        $sql .= " AND cc.status = :status";
        $params['status'] = (int) $statusFilter;
    }

    $sql .= " ORDER BY cc.created_at DESC, cc.business_name ASC";
=======
        $sql .= ' AND cc.status = :status';
        $params['status'] = (int) $statusFilter;
    }

    $sql .= ' ORDER BY cc.created_at DESC, cc.business_name ASC';
>>>>>>> fbc9f0c (Actualización de módulos y configuración del sistema)

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtSummary = $pdo->query("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN sla_contract_type = '24_7' THEN 1 ELSE 0 END) AS contract_24_7,
            SUM(CASE WHEN sla_contract_type = '8_5' THEN 1 ELSE 0 END) AS contract_8_5
        FROM client_companies");
    $summary = array_merge($summary, $stmtSummary->fetch(PDO::FETCH_ASSOC) ?: []);
}

ob_start();
require __DIR__ . '/app/views/admin/clients.php';
$pageContent = ob_get_clean();

echo $pageContent;
