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

$ruc = preg_replace('/\D+/', '', trim($_POST['ruc'] ?? ''));
$businessName = trim($_POST['business_name'] ?? '');
$tradeName = trim($_POST['trade_name'] ?? '');
$fiscalAddress = trim($_POST['fiscal_address'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$slaContractType = trim($_POST['sla_contract_type'] ?? '8_5');

try {
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

    if ($ruc !== '') {
        $stmtDuplicate = $pdo->prepare('SELECT id FROM client_companies WHERE ruc = :ruc LIMIT 1');
        $stmtDuplicate->execute(['ruc' => $ruc]);
        if ($stmtDuplicate->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Ya existe una empresa cliente con ese RUC.');
        }
    }

    $stmt = $pdo->prepare('INSERT INTO client_companies
        (ruc, business_name, trade_name, fiscal_address, phone, email, sla_contract_type, status, created_at)
        VALUES
        (:ruc, :business_name, :trade_name, :fiscal_address, :phone, :email, :sla_contract_type, 1, NOW())');

    $stmt->execute([
        'ruc' => $ruc !== '' ? $ruc : null,
        'business_name' => $businessName,
        'trade_name' => $tradeName !== '' ? $tradeName : null,
        'fiscal_address' => $fiscalAddress !== '' ? $fiscalAddress : null,
        'phone' => $phone !== '' ? $phone : null,
        'email' => $email !== '' ? $email : null,
        'sla_contract_type' => $slaContractType,
    ]);

    $_SESSION['client_success'] = 'Empresa cliente registrada correctamente.';
    redirectClients();
} catch (Throwable $e) {
    $_SESSION['client_error'] = $e->getMessage();
    redirectClients();
}
