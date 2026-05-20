<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedStatus = ['ABIERTO', 'EN_PROCESO', 'RESPONDIDO', 'CERRADO'];
$allowedPriority = ['BAJA', 'MEDIA', 'ALTA'];
$allowedCategory = ['ACCESO', 'SISTEMA', 'HARDWARE', 'SOFTWARE', 'RED', 'OTROS'];

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

if ($search !== '') {
    $sql .= " AND (
                t.subject LIKE :search
                OR t.description LIKE :search
                OR u.name LIKE :search
              )";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

require __DIR__ . '/app/views/admin/tickets.php';