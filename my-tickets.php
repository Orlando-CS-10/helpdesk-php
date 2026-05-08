<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();

if ($currentUser['role'] !== 'CLIENT') {
    header('Location: home.php');
    exit;
}

$sql = "SELECT 
            id,
            subject,
            description,
            status,
            priority,
            category,
            client_closed,
            created_at,
            updated_at
        FROM tickets
        WHERE requester_id = :requester_id
        ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'requester_id' => $currentUser['id']
]);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/app/views/client/my-tickets.php';