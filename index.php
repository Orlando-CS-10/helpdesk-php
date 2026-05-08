<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';

if ($currentRole !== 'ADMIN') {
    header('Location: /helpdesk-php/home.php');
    exit;
}

// Tickets abiertos
$stmtOpen = $pdo->query("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE status = 'ABIERTO'
");
$openTickets = (int)($stmtOpen->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Tickets en proceso
$stmtProcess = $pdo->query("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE status = 'EN_PROCESO'
");
$inProgressTickets = (int)($stmtProcess->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Tickets cerrados
$stmtClosed = $pdo->query("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE status = 'CERRADO'
");
$closedTickets = (int)($stmtClosed->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Calificación promedio
$stmtRating = $pdo->query("
    SELECT AVG(rating) AS promedio
    FROM ticket_feedback
");
$avgRatingRaw = $stmtRating->fetch(PDO::FETCH_ASSOC)['promedio'] ?? null;
$avgRating = $avgRatingRaw !== null ? number_format((float)$avgRatingRaw, 1) : '0.0';

// TTA promedio en horas
$stmtTTA = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) AS avg_tta
    FROM tickets
    WHERE first_response_at IS NOT NULL
");
$avgTTARaw = $stmtTTA->fetch(PDO::FETCH_ASSOC)['avg_tta'] ?? null;
$avgTTA = $avgTTARaw !== null ? number_format((float)$avgTTARaw, 1) : '0.0';

// TTR promedio en horas
$stmtTTR = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) AS avg_ttr
    FROM tickets
    WHERE closed_at IS NOT NULL
");
$avgTTRRaw = $stmtTTR->fetch(PDO::FETCH_ASSOC)['avg_ttr'] ?? null;
$avgTTR = $avgTTRRaw !== null ? number_format((float)$avgTTRRaw, 1) : '0.0';

// % SLA cumplido
$stmtSLA = $pdo->query("
    SELECT 
        COUNT(*) AS total_closed,
        SUM(CASE WHEN sla_met = 1 THEN 1 ELSE 0 END) AS total_sla_ok
    FROM tickets
    WHERE closed_at IS NOT NULL
");
$slaData = $stmtSLA->fetch(PDO::FETCH_ASSOC);

$totalClosedForSLA = (int)($slaData['total_closed'] ?? 0);
$totalSlaOk = (int)($slaData['total_sla_ok'] ?? 0);

$slaPercent = $totalClosedForSLA > 0
    ? number_format(($totalSlaOk / $totalClosedForSLA) * 100, 1)
    : '0.0';

require __DIR__ . '/app/views/admin/dashboard.php';