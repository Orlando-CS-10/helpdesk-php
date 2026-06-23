<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
if (($currentUser['role'] ?? '') !== 'ADMIN') {
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

function redirectClients(): void
{
    header('Location: /helpdesk-php/admin-clients.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$ruc = preg_replace('/\D+/', '', trim($_POST['ruc'] ?? ''));
$businessName = trim($_POST['business_name'] ?? '');
$tradeName = trim($_POST['trade_name'] ?? '');
$fiscalAddress = trim($_POST['fiscal_address'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$slaContractType = trim($_POST['sla_contract_type'] ?? '8_5');

try {
    if ($id <= 0) {
        throw new RuntimeException('Empresa cliente no válida.');
    }

    if ($businessName === '') {
        throw new RuntimeException('Ingresa la razón social de la empresa cliente.');
    }

    if ($ruc !== '' && strlen($ruc) !== 11) {
        throw new RuntimeException('El RUC debe tener 11 dígitos.');
    }

    if (!in_array($slaContractType, ['24_7', '8_5'], true)) {
        throw new RuntimeException('Selecciona un tipo de contrato SLA válido.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo corporativo no es válido.');
    }

    $stmtCompany = $pdo->prepare('SELECT id FROM client_companies WHERE id = :id LIMIT 1');
    $stmtCompany->execute(['id' => $id]);
    if (!$stmtCompany->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('La empresa cliente no existe.');
    }

    if ($ruc !== '') {
        $stmtDuplicate = $pdo->prepare('SELECT id FROM client_companies WHERE ruc = :ruc AND id <> :id LIMIT 1');
        $stmtDuplicate->execute(['ruc' => $ruc, 'id' => $id]);
        if ($stmtDuplicate->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Ya existe otra empresa cliente con ese RUC.');
        }
    }

    $stmt = $pdo->prepare('UPDATE client_companies
        SET ruc = :ruc,
            business_name = :business_name,
            trade_name = :trade_name,
            fiscal_address = :fiscal_address,
            phone = :phone,
            email = :email,
            sla_contract_type = :sla_contract_type,
            updated_at = NOW()
        WHERE id = :id');

    $stmt->execute([
        'id' => $id,
        'ruc' => $ruc !== '' ? $ruc : null,
        'business_name' => $businessName,
        'trade_name' => $tradeName !== '' ? $tradeName : null,
        'fiscal_address' => $fiscalAddress !== '' ? $fiscalAddress : null,
        'phone' => $phone !== '' ? $phone : null,
        'email' => $email !== '' ? $email : null,
        'sla_contract_type' => $slaContractType,
    ]);

    $_SESSION['client_success'] = 'Empresa cliente actualizada correctamente.';
    redirectClients();
} catch (Throwable $e) {
    $_SESSION['client_error'] = $e->getMessage();
    redirectClients();
}
