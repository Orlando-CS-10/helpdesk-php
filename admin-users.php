<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$role = trim($_GET['role'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedRoles = ['CLIENT', 'TECH', 'ADMIN'];

$sql = "SELECT
            id,
            name,
            email,
            role,
            status,
            phone,
            position,
            company,
            created_at
        FROM users
        WHERE 1=1";

$params = [];

if ($role !== '' && in_array($role, $allowedRoles, true)) {
    $sql .= " AND role = :role";
    $params['role'] = $role;
}

if ($search !== '') {
    $sql .= " AND (name LIKE :search OR email LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/app/views/admin/users.php';