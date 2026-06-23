<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';

$businessHoursHelper = __DIR__ . '/app/helpers/business_hours.php';
if (file_exists($businessHoursHelper)) {
    require_once $businessHoursHelper;
}

$db = $pdo;

if (!function_exists('dashboardTableExists')) {
    function dashboardTableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

function dashboardColumnExists(PDO $db, string $tableName, string $columnName): bool
{
    $stmt = $db->prepare("SHOW COLUMNS FROM `$tableName` LIKE :column_name");
    $stmt->execute(['column_name' => $columnName]);

    return (bool)$stmt->fetchColumn();
}

function fetchDashboardCount(PDO $db, string $sql): int
{
    $value = $db->query($sql)->fetchColumn();

    return (int)($value ?? 0);
}

function fetchDashboardScalar(PDO $db, string $sql): mixed
{
    return $db->query($sql)->fetchColumn();
}

function fetchDashboardRows(PDO $db, string $sql): array
{
    $stmt = $db->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function calculateDashboardHours(?string $start, ?string $end): ?float
{
    if (empty($start) || empty($end)) {
        return null;
    }

    if (function_exists('calculateBusinessHours')) {
        return (float)calculateBusinessHours($start, $end);
    }

    try {
        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
    } catch (Exception $e) {
        return null;
    }

    if ($endDate <= $startDate) {
        return 0.0;
    }

    return round(($endDate->getTimestamp() - $startDate->getTimestamp()) / 3600, 2);
}

function averageDashboardHours(array $values): float
{
    $values = array_values(array_filter($values, static fn($value) => $value !== null));

    if (count($values) === 0) {
        return 0.0;
    }

    return round(array_sum($values) / count($values), 2);
}

function formatDashboardHours(float $hours): string
{
    if (function_exists('formatDecimalHoursToClock')) {
        return formatDecimalHoursToClock($hours);
    }

    return number_format($hours, 2);
}

$hasSupportLevel = dashboardColumnExists($db, 'tickets', 'support_level');
$hasLevelFirstResponse = dashboardColumnExists($db, 'tickets', 'level_first_response_at');
$hasLevelStartedAt = dashboardColumnExists($db, 'tickets', 'level_started_at');
$hasTicketLevelHistory = dashboardTableExists($db, 'ticket_level_history');

$totalTickets = fetchDashboardCount($db, "SELECT COUNT(*) FROM tickets");
$openTickets = fetchDashboardCount($db, "SELECT COUNT(*) FROM tickets WHERE status = 'ABIERTO'");
$inProgressTickets = fetchDashboardCount($db, "SELECT COUNT(*) FROM tickets WHERE status = 'EN_PROCESO'");
$answeredTickets = fetchDashboardCount($db, "SELECT COUNT(*) FROM tickets WHERE status = 'RESPONDIDO'");
$closedTickets = fetchDashboardCount($db, "SELECT COUNT(*) FROM tickets WHERE status = 'CERRADO'");
$activeTickets = fetchDashboardCount($db, "SELECT COUNT(*) FROM tickets WHERE status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO')");

$escalatedTickets = $hasTicketLevelHistory
    ? fetchDashboardCount($db, "SELECT COUNT(DISTINCT ticket_id) FROM ticket_level_history WHERE result = 'ESCALADO'")
    : 0;

$closedWithinSla = fetchDashboardCount($db, "
    SELECT COUNT(*)
    FROM tickets
    WHERE status = 'CERRADO'
      AND sla_met = 1
");

$closedOutSla = fetchDashboardCount($db, "
    SELECT COUNT(*)
    FROM tickets
    WHERE status = 'CERRADO'
      AND (sla_met = 0 OR sla_met IS NULL)
");

$slaPercent = 0;
if ($closedTickets > 0) {
    $slaPercent = round(($closedWithinSla / $closedTickets) * 100, 1);
}

$levelFirstResponseSelect = $hasLevelFirstResponse
    ? "COALESCE(level_first_response_at, first_response_at) AS dashboard_first_response_at"
    : "first_response_at AS dashboard_first_response_at";

$ticketsForTime = fetchDashboardRows($db, "
    SELECT 
        created_at,
        $levelFirstResponseSelect,
        CASE
            WHEN status = 'CERRADO' THEN COALESCE(closed_at, updated_at)
            ELSE closed_at
        END AS dashboard_closed_at
    FROM tickets
");

$ttaValues = [];
$ttrValues = [];

foreach ($ticketsForTime as $ticket) {
    $tta = calculateDashboardHours(
        $ticket['created_at'] ?? null,
        $ticket['dashboard_first_response_at'] ?? null
    );

    $ttr = calculateDashboardHours(
        $ticket['created_at'] ?? null,
        $ticket['dashboard_closed_at'] ?? null
    );

    if ($tta !== null) {
        $ttaValues[] = $tta;
    }

    if ($ttr !== null) {
        $ttrValues[] = $ttr;
    }
}

$avgTTAFloat = averageDashboardHours($ttaValues);
$avgTTRFloat = averageDashboardHours($ttrValues);

$avgTTA = formatDashboardHours($avgTTAFloat);
$avgTTR = formatDashboardHours($avgTTRFloat);

$ticketsByStatus = [
    ['status_label' => 'Abiertos', 'total' => $openTickets],
    ['status_label' => 'En proceso', 'total' => $inProgressTickets],
    ['status_label' => 'Respondidos', 'total' => $answeredTickets],
    ['status_label' => 'Cerrados', 'total' => $closedTickets],
];

$ticketsByPriority = fetchDashboardRows($db, "
    SELECT 
        COALESCE(priority, 'Sin prioridad') AS priority,
        COUNT(*) AS total
    FROM tickets
    GROUP BY COALESCE(priority, 'Sin prioridad')
    ORDER BY FIELD(priority, 'ALTA', 'MEDIA', 'BAJA'), total DESC
");

$ticketsByCategory = fetchDashboardRows($db, "
    SELECT 
        COALESCE(category, 'Sin categoría') AS category,
        COUNT(*) AS total
    FROM tickets
    GROUP BY COALESCE(category, 'Sin categoría')
    ORDER BY total DESC, category ASC
");

$ticketsByTechnician = fetchDashboardRows($db, "
    SELECT 
        COALESCE(u.name, 'Sin asignar') AS technician_name,
        COUNT(t.id) AS total
    FROM tickets t
    LEFT JOIN users u ON u.id = t.assigned_to
    GROUP BY COALESCE(u.name, 'Sin asignar')
    ORDER BY total DESC, technician_name ASC
");

if ($hasSupportLevel) {
    $ticketsByLevel = fetchDashboardRows($db, "
        SELECT 
            COALESCE(support_level, 1) AS support_level,
            COUNT(*) AS total
        FROM tickets
        GROUP BY COALESCE(support_level, 1)
        ORDER BY support_level ASC
    ");
} else {
    $ticketsByLevel = [];
}

$technicianEscalatedSubquery = $hasTicketLevelHistory
    ? "(SELECT COUNT(*) FROM ticket_level_history h WHERE h.technician_id = u.id AND h.result = 'ESCALADO')"
    : "0";

$technicianSummary = fetchDashboardRows($db, "
    SELECT 
        u.id,
        u.name,
        COALESCE(u.tech_level, 1) AS tech_level,
        (
            SELECT COUNT(*)
            FROM tickets t
            WHERE t.assigned_to = u.id
              AND t.status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO')
        ) AS active_tickets,
        (
            SELECT COUNT(*)
            FROM tickets t
            WHERE t.assigned_to = u.id
              AND t.status = 'CERRADO'
        ) AS closed_tickets,
        $technicianEscalatedSubquery AS escalated_tickets
    FROM users u
    WHERE u.role = 'TECH'
      AND u.status = 1
    ORDER BY COALESCE(u.tech_level, 1) ASC, active_tickets ASC, u.name ASC
");

$levelSummary = [];

for ($level = 1; $level <= 3; $level++) {
    if ($hasSupportLevel) {
        $currentTotal = fetchDashboardCount($db, "
            SELECT COUNT(*)
            FROM tickets
            WHERE COALESCE(support_level, 1) = $level
        ");

        $activeTotal = fetchDashboardCount($db, "
            SELECT COUNT(*)
            FROM tickets
            WHERE COALESCE(support_level, 1) = $level
              AND status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO')
        ");

        $closedTotal = fetchDashboardCount($db, "
            SELECT COUNT(*)
            FROM tickets
            WHERE COALESCE(support_level, 1) = $level
              AND status = 'CERRADO'
        ");
    } else {
        $currentTotal = 0;
        $activeTotal = 0;
        $closedTotal = 0;
    }

    $escalationsFromLevel = $hasTicketLevelHistory
        ? fetchDashboardCount($db, "
            SELECT COUNT(*)
            FROM ticket_level_history
            WHERE support_level = $level
              AND result = 'ESCALADO'
        ")
        : 0;

    $levelSummary[] = [
        'level' => $level,
        'current_total' => $currentTotal,
        'active_total' => $activeTotal,
        'closed_total' => $closedTotal,
        'escalated_total' => $escalationsFromLevel,
    ];
}

$recentTickets = fetchDashboardRows($db, "
    SELECT 
        t.id,
        t.subject,
        t.status,
        t.priority,
        t.category,
        COALESCE(u.name, 'Sin asignar') AS technician_name,
        t.created_at
    FROM tickets t
    LEFT JOIN users u ON u.id = t.assigned_to
    ORDER BY t.created_at DESC
    LIMIT 6
");

require_once __DIR__ . '/app/views/admin/dashboard-analytics.php';
