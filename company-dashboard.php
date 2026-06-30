<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/company_portal.php';

$sessionResult = companyPortalRequireLogin($pdo);
$account = (array) ($sessionResult['account'] ?? companyPortalAccount() ?? []);
$companyId = companyPortalCompanyId();

$summary = [
    'contacts' => 0,
    'active_contacts' => 0,
    'tickets' => 0,
    'open' => 0,
    'in_progress' => 0,
    'answered' => 0,
    'closed' => 0,
    'sla_met' => 0,
    'sla_measured' => 0,
];
$recentTickets = [];

try {
    $contactStatement = $pdo->prepare(
        "SELECT COUNT(*) AS contacts,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_contacts
         FROM users
         WHERE role = 'CLIENT' AND company_id = :company_id"
    );
    $contactStatement->execute(['company_id' => $companyId]);
    $contactRow = $contactStatement->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['contacts'] = (int) ($contactRow['contacts'] ?? 0);
    $summary['active_contacts'] = (int) ($contactRow['active_contacts'] ?? 0);

    $ticketStatement = $pdo->prepare(
        "SELECT
            COUNT(*) AS tickets,
            SUM(CASE WHEN status = 'ABIERTO' THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN status = 'EN_PROCESO' THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN status = 'RESPONDIDO' THEN 1 ELSE 0 END) AS answered_count,
            SUM(CASE WHEN status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_count,
            SUM(CASE WHEN sla_met = 1 THEN 1 ELSE 0 END) AS sla_met_count,
            SUM(CASE WHEN sla_met IS NOT NULL THEN 1 ELSE 0 END) AS sla_measured_count
         FROM tickets
         WHERE company_id = :company_id"
    );
    $ticketStatement->execute(['company_id' => $companyId]);
    $ticketRow = $ticketStatement->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['tickets'] = (int) ($ticketRow['tickets'] ?? 0);
    $summary['open'] = (int) ($ticketRow['open_count'] ?? 0);
    $summary['in_progress'] = (int) ($ticketRow['in_progress_count'] ?? 0);
    $summary['answered'] = (int) ($ticketRow['answered_count'] ?? 0);
    $summary['closed'] = (int) ($ticketRow['closed_count'] ?? 0);
    $summary['sla_met'] = (int) ($ticketRow['sla_met_count'] ?? 0);
    $summary['sla_measured'] = (int) ($ticketRow['sla_measured_count'] ?? 0);

    $recentStatement = $pdo->prepare(
        "SELECT
            t.id,
            t.subject,
            t.status,
            t.priority,
            t.category,
            t.created_at,
            t.sla_ttr_due_at,
            requester.name AS requester_name,
            technician.name AS technician_name
         FROM tickets t
         INNER JOIN users requester ON requester.id = t.requester_id
         LEFT JOIN users technician ON technician.id = t.assigned_to
         WHERE t.company_id = :company_id
         ORDER BY t.created_at DESC, t.id DESC
         LIMIT 6"
    );
    $recentStatement->execute(['company_id' => $companyId]);
    $recentTickets = $recentStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    companyPortalAudit(
        $pdo,
        'DASHBOARD_QUERY_WARNING',
        'No se pudieron cargar todos los indicadores del panel corporativo.',
        $companyId,
        (int) ($account['id'] ?? 0),
        'warning'
    );
}

$slaPercentage = $summary['sla_measured'] > 0
    ? (int) round(($summary['sla_met'] / $summary['sla_measured']) * 100)
    : null;

require __DIR__ . '/app/views/company-portal/dashboard.php';
