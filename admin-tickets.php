<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

if (!function_exists('normalizeAdminTicketDate')) {
    function normalizeAdminTicketDate(string $date): string
    {
        $date = trim($date);

        if ($date === '') {
            return '';
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }
}

$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');
$ticketCode = trim($_GET['ticket_code'] ?? '');
$assignedTo = trim($_GET['assigned_to'] ?? '');
$techLevel = trim($_GET['tech_level'] ?? '');
$assignmentStatus = trim($_GET['assignment_status'] ?? '');
$slaStatus = trim($_GET['sla_status'] ?? '');
$dateFrom = normalizeAdminTicketDate($_GET['date_from'] ?? '');
$dateTo = normalizeAdminTicketDate($_GET['date_to'] ?? '');

$allowedStatus = ['ABIERTO', 'EN_PROCESO', 'RESPONDIDO', 'CERRADO'];
$allowedPriority = ['BAJA', 'MEDIA', 'ALTA'];
$allowedCategory = ['ACCESO', 'SISTEMA', 'HARDWARE', 'SOFTWARE', 'RED', 'OTROS'];
$allowedTechLevels = ['1', '2', '3'];
$allowedAssignmentStatus = ['ASIGNADO', 'SIN_ASIGNAR'];
$allowedSlaStatus = ['CUMPLIDO', 'NO_CUMPLIDO', 'PENDIENTE'];

// técnicos disponibles con carga actual de tickets
$sqlTechs = "SELECT 
                u.id,
                u.name,
                u.tech_level,
                COUNT(t.id) AS active_tickets
             FROM users u
             LEFT JOIN tickets t 
                ON t.assigned_to = u.id
                AND t.status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO')
             WHERE u.role = 'TECH'
               AND u.status = 1
             GROUP BY u.id, u.name, u.tech_level
             ORDER BY u.tech_level ASC, active_tickets ASC, u.name ASC";

$stmtTechs = $pdo->prepare($sqlTechs);
$stmtTechs->execute();
$techUsers = $stmtTechs->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT
            t.id,
            t.subject,
            t.description,
            t.status,
            t.priority,
            t.category,
            t.assigned_to,
            t.sla_hours,
            t.sla_met,
            t.created_at,
            t.updated_at,
            t.first_response_at,
            t.closed_at,
            u.name AS requester_name,
            a.name AS assigned_name,
            a.tech_level AS assigned_tech_level,
            CASE
                WHEN t.first_response_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, t.created_at, t.first_response_at)
                ELSE NULL
            END AS tta_hours,
            CASE
                WHEN t.closed_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)
                ELSE NULL
            END AS ttr_hours
        FROM tickets t
        INNER JOIN users u ON u.id = t.requester_id
        LEFT JOIN users a ON a.id = t.assigned_to
        WHERE 1=1";

$params = [];

if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $sql .= " AND t.status = :status";
    $params['status'] = $status;
}

if ($priority !== '' && in_array($priority, $allowedPriority, true)) {
    $sql .= " AND t.priority = :priority";
    $params['priority'] = $priority;
}

if ($category !== '' && in_array($category, $allowedCategory, true)) {
    $sql .= " AND t.category = :category";
    $params['category'] = $category;
}

$ticketCodeDigits = preg_replace('/[^0-9]/', '', $ticketCode);
if ($ticketCodeDigits !== '') {
    $sql .= " AND t.id = :ticket_code";
    $params['ticket_code'] = (int)$ticketCodeDigits;
}

if ($assignedTo !== '' && ctype_digit($assignedTo) && (int)$assignedTo > 0) {
    $sql .= " AND t.assigned_to = :assigned_to";
    $params['assigned_to'] = (int)$assignedTo;
}

if ($techLevel !== '' && in_array($techLevel, $allowedTechLevels, true)) {
    $sql .= " AND a.tech_level = :tech_level";
    $params['tech_level'] = (int)$techLevel;
}

if ($assignmentStatus !== '' && in_array($assignmentStatus, $allowedAssignmentStatus, true)) {
    if ($assignmentStatus === 'ASIGNADO') {
        $sql .= " AND t.assigned_to IS NOT NULL AND t.assigned_to <> 0";
    } elseif ($assignmentStatus === 'SIN_ASIGNAR') {
        $sql .= " AND (t.assigned_to IS NULL OR t.assigned_to = 0)";
    }
}

if ($slaStatus !== '' && in_array($slaStatus, $allowedSlaStatus, true)) {
    if ($slaStatus === 'CUMPLIDO') {
        $sql .= " AND t.sla_met = 1";
    } elseif ($slaStatus === 'NO_CUMPLIDO') {
        $sql .= " AND t.sla_met = 0";
    } elseif ($slaStatus === 'PENDIENTE') {
        $sql .= " AND t.sla_met IS NULL";
    }
}

if ($dateFrom !== '') {
    $sql .= " AND DATE(t.created_at) >= :date_from";
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $sql .= " AND DATE(t.created_at) <= :date_to";
    $params['date_to'] = $dateTo;
}

if ($search !== '') {
    $sql .= " AND (
                t.subject LIKE :search
                OR t.description LIKE :search
                OR u.name LIKE :search
                OR a.name LIKE :search
                OR CAST(t.id AS CHAR) LIKE :search
              )";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/app/views/admin/tickets.php';
