<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';

$businessHoursHelper = __DIR__ . '/app/helpers/business_hours.php';
if (file_exists($businessHoursHelper)) {
    require_once $businessHoursHelper;
}

$db = $pdo;

function fetchCount(PDO $db, string $sql): int
{
    $value = $db->query($sql)->fetchColumn();
    return (int)($value ?? 0);
}

function fetchGroupedRows(PDO $db, string $sql): array
{
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function formatDecimalHoursDashboard(float $hours): string
{
    if (function_exists('formatDecimalHoursToClock')) {
        return formatDecimalHoursToClock($hours);
    }

    return (string)round($hours, 1);
}

function calculateTicketHoursDashboard(?string $start, ?string $end): ?float
{
    if (empty($start) || empty($end)) {
        return null;
    }

    if (function_exists('calculateBusinessHours')) {
        return (float)calculateBusinessHours($start, $end);
    }

    $startDate = new DateTime($start);
    $endDate = new DateTime($end);

    if ($endDate <= $startDate) {
        return 0.0;
    }

    return round(($endDate->getTimestamp() - $startDate->getTimestamp()) / 3600, 2);
}

function averageDashboard(array $values): float
{
    $values = array_values(array_filter($values, static fn($value) => $value !== null));

    if (count($values) === 0) {
        return 0.0;
    }

    return round(array_sum($values) / count($values), 2);
}

$totalTickets = fetchCount($db, "SELECT COUNT(*) FROM tickets");

$openTickets = fetchCount($db, "SELECT COUNT(*) FROM tickets WHERE status = 'ABIERTO'");
$inProgressTickets = fetchCount($db, "SELECT COUNT(*) FROM tickets WHERE status = 'EN_PROCESO'");
$closedTickets = fetchCount($db, "SELECT COUNT(*) FROM tickets WHERE status = 'CERRADO'");

$ticketsForTime = fetchGroupedRows($db, "
    SELECT created_at, first_response_at, closed_at
    FROM tickets
");

$ttaValues = [];
$ttrValues = [];

foreach ($ticketsForTime as $ticket) {
    $tta = calculateTicketHoursDashboard($ticket['created_at'] ?? null, $ticket['first_response_at'] ?? null);
    $ttr = calculateTicketHoursDashboard($ticket['created_at'] ?? null, $ticket['closed_at'] ?? null);

    if ($tta !== null) {
        $ttaValues[] = $tta;
    }

    if ($ttr !== null) {
        $ttrValues[] = $ttr;
    }
}

$avgTTA = formatDecimalHoursDashboard(averageDashboard($ttaValues));
$avgTTR = formatDecimalHoursDashboard(averageDashboard($ttrValues));

$slaPercent = $db->query("
    SELECT ROUND(
        COALESCE(
            (SUM(CASE WHEN sla_met = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100,
            0
        ),
        1
    )
    FROM tickets
    WHERE status = 'CERRADO'
")->fetchColumn();

$slaPercent = $slaPercent ?? 0;

$ticketsByPriority = fetchGroupedRows($db, "
    SELECT 
        COALESCE(priority, 'Sin prioridad') AS priority,
        COUNT(*) AS total
    FROM tickets
    GROUP BY COALESCE(priority, 'Sin prioridad')
    ORDER BY FIELD(priority, 'ALTA', 'MEDIA', 'BAJA'), total DESC
");

$ticketsByCategory = fetchGroupedRows($db, "
    SELECT 
        COALESCE(category, 'Sin categoría') AS category,
        COUNT(*) AS total
    FROM tickets
    GROUP BY COALESCE(category, 'Sin categoría')
    ORDER BY total DESC, category ASC
");

$ticketsByTechnician = fetchGroupedRows($db, "
    SELECT 
        COALESCE(u.name, 'Sin asignar') AS technician_name,
        COUNT(t.id) AS total
    FROM tickets t
    LEFT JOIN users u ON u.id = t.assigned_to
    GROUP BY COALESCE(u.name, 'Sin asignar')
    ORDER BY total DESC, technician_name ASC
");

require_once __DIR__ . '/app/views/admin/dashboard-analytics.php';
