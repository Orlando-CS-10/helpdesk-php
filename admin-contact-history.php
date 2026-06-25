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

$contactIdRaw = trim((string) ($_GET['user_id'] ?? ''));
if ($contactIdRaw === '' || !ctype_digit($contactIdRaw) || (int) $contactIdRaw < 1) {
    $_SESSION['client_error'] = 'El contacto seleccionado no es válido.';
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

$contactId = (int) $contactIdRaw;

$stmtContact = $pdo->prepare(
    "SELECT
        u.id, u.name, u.email, u.phone, u.position, u.status, u.created_at,
        u.profile_photo, u.company_id, u.can_view_company_tickets,
        cc.ruc AS company_ruc,
        cc.business_name AS company_business_name,
        cc.trade_name AS company_trade_name,
        cc.sla_contract_type,
        cc.status AS company_status
     FROM users u
     LEFT JOIN client_companies cc ON cc.id = u.company_id
     WHERE u.id = :user_id AND u.role = 'CLIENT'
     LIMIT 1"
);
$stmtContact->execute(['user_id' => $contactId]);
$contact = $stmtContact->fetch(PDO::FETCH_ASSOC);

if (!$contact) {
    $_SESSION['client_error'] = 'El contacto solicitado no existe o no pertenece al rol cliente.';
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$allowedStatuses = ['ABIERTO', 'EN_PROCESO', 'RESPONDIDO', 'CERRADO'];
$allowedPriorities = ['BAJA', 'MEDIA', 'ALTA'];

$where = 't.requester_id = :requester_id';
$params = ['requester_id' => $contactId];

if ($search !== '') {
    $where .= " AND (CAST(t.id AS CHAR) LIKE :search OR t.subject LIKE :search OR t.description LIKE :search OR t.category LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (in_array($status, $allowedStatuses, true)) {
    $where .= ' AND t.status = :status';
    $params['status'] = $status;
}

if (in_array($priority, $allowedPriorities, true)) {
    $where .= ' AND t.priority = :priority';
    $params['priority'] = $priority;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where .= ' AND t.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where .= ' AND t.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE $where");
$stmtTotal->execute($params);
$totalTicketsFiltered = (int) $stmtTotal->fetchColumn();
$totalPages = max(1, (int) ceil($totalTicketsFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sqlTickets = "SELECT
        t.id,
        t.subject,
        t.description,
        t.status,
        t.priority,
        t.category,
        t.support_level,
        t.created_at,
        t.updated_at,
        t.first_response_at,
        t.closed_at,
        t.sla_hours,
        t.sla_met,
        tech.name AS assigned_technician,
        (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) AS public_messages_count,
        (SELECT COUNT(*) FROM ticket_activity ta WHERE ta.ticket_id = t.id) AS activities_count,
        (SELECT COUNT(*) FROM ticket_message_attachments tma WHERE tma.ticket_id = t.id) AS attachments_count
    FROM tickets t
    LEFT JOIN users tech ON tech.id = t.assigned_to
    WHERE $where
    ORDER BY t.created_at DESC, t.id DESC
    LIMIT :limit OFFSET :offset";

$stmtTickets = $pdo->prepare($sqlTickets);
foreach ($params as $key => $value) {
    $stmtTickets->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtTickets->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmtTickets->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtTickets->execute();
$tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

$stmtSummary = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status <> 'CERRADO' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_count,
        SUM(CASE WHEN sla_met = 1 THEN 1 ELSE 0 END) AS sla_met_count,
        MAX(COALESCE(updated_at, created_at)) AS last_activity_at
     FROM tickets
     WHERE requester_id = :requester_id"
);
$stmtSummary->execute(['requester_id' => $contactId]);
$summary = $stmtSummary->fetch(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/app/views/admin/contact-history.php';
