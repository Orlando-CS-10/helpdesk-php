<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    if ($id <= 0) {
        throw new RuntimeException('Empresa cliente no válida.');
    }

    $stmt = $pdo->prepare('SELECT id, status FROM client_companies WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        throw new RuntimeException('La empresa cliente no existe.');
    }

    $newStatus = (int)($company['status'] ?? 0) === 1 ? 0 : 1;
    $stmtUpdate = $pdo->prepare('UPDATE client_companies SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmtUpdate->execute([
        'status' => $newStatus,
        'id' => $id,
    ]);

    $_SESSION['client_success'] = $newStatus === 1
        ? 'Empresa cliente activada correctamente.'
        : 'Empresa cliente desactivada correctamente.';
} catch (Throwable $e) {
    $_SESSION['client_error'] = $e->getMessage();
}

header('Location: /helpdesk-php/admin-clients.php');
exit;
