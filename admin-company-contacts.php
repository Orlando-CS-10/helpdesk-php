<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';

if (!in_array($currentRole, ['ADMIN', 'TECH'], true)) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

function companyContactsTableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $table]);
        return (bool) $stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $e) {
        return false;
    }
}

function companyContactsColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column_name");
        $stmt->execute(['column_name' => $column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

$companyIdRaw = trim((string) ($_GET['company_id'] ?? ''));
if ($companyIdRaw === '' || !ctype_digit($companyIdRaw) || (int) $companyIdRaw < 1) {
    $_SESSION['client_error'] = 'La empresa seleccionada no es válida.';
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

$companyId = (int) $companyIdRaw;
$moduleReady = companyContactsTableExists($pdo, 'client_companies')
    && companyContactsTableExists($pdo, 'users')
    && companyContactsTableExists($pdo, 'tickets');

if (!$moduleReady) {
    $_SESSION['client_error'] = 'No se encontraron las tablas necesarias para consultar los contactos.';
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

$companyLogoColumnReady = companyContactsColumnExists($pdo, 'client_companies', 'logo_path');
$companyLogoSelect = $companyLogoColumnReady ? 'logo_path' : 'NULL AS logo_path';

$stmtCompany = $pdo->prepare(
    "SELECT id, ruc, business_name, trade_name, fiscal_address, phone, email,
            $companyLogoSelect,
            sla_contract_type, status, created_at, updated_at
     FROM client_companies
     WHERE id = :company_id
     LIMIT 1"
);
$stmtCompany->execute(['company_id' => $companyId]);
$company = $stmtCompany->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    $_SESSION['client_error'] = 'La empresa solicitada no existe.';
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$where = "u.role = 'CLIENT' AND u.company_id = :company_id";
$params = ['company_id' => $companyId];

if ($search !== '') {
    $where .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR u.position LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (in_array($status, ['1', '0'], true)) {
    $where .= ' AND u.status = :status';
    $params['status'] = (int) $status;
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$stmtTotal->execute($params);
$totalContacts = (int) $stmtTotal->fetchColumn();
$totalPages = max(1, (int) ceil($totalContacts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sqlContacts = "SELECT
        u.id,
        u.name,
        u.email,
        u.phone,
        u.position,
        u.status,
        u.created_at,
        u.profile_photo,
        u.can_view_company_tickets,
        (SELECT COUNT(*) FROM tickets t WHERE t.requester_id = u.id) AS tickets_count,
        (SELECT COUNT(*) FROM tickets t WHERE t.requester_id = u.id AND t.status <> 'CERRADO') AS open_tickets_count,
        (SELECT COUNT(*) FROM tickets t WHERE t.requester_id = u.id AND t.status = 'CERRADO') AS closed_tickets_count,
        (SELECT MAX(COALESCE(t.updated_at, t.created_at)) FROM tickets t WHERE t.requester_id = u.id) AS last_activity_at
    FROM users u
    WHERE $where
    ORDER BY
        CASE WHEN last_activity_at IS NULL THEN 1 ELSE 0 END,
        last_activity_at DESC,
        u.name ASC
    LIMIT :limit OFFSET :offset";

$stmtContacts = $pdo->prepare($sqlContacts);
foreach ($params as $key => $value) {
    $stmtContacts->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtContacts->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtContacts->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtContacts->execute();
$contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

$stmtSummary = $pdo->prepare(
    "SELECT
        COUNT(DISTINCT u.id) AS total_contacts,
        COUNT(DISTINCT CASE WHEN u.status = 1 THEN u.id END) AS active_contacts,
        COUNT(DISTINCT t.id) AS total_tickets,
        COUNT(DISTINCT CASE WHEN t.status <> 'CERRADO' THEN t.id END) AS open_tickets,
        MAX(COALESCE(t.updated_at, t.created_at)) AS last_activity_at
     FROM users u
     LEFT JOIN tickets t ON t.requester_id = u.id
     WHERE u.role = 'CLIENT' AND u.company_id = :company_id"
);
$stmtSummary->execute(['company_id' => $companyId]);
$summary = $stmtSummary->fetch(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/app/views/admin/company-contacts.php';
