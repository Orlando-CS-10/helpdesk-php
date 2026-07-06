<?php

require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/sla_helper.php';
require_once __DIR__ . '/app/helpers/ticket_message_helper.php';

requireLogin();

$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticketId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$currentUser = (array)user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));
$canUseInternalConversation = in_array($currentRole, ['ADMIN', 'TECH'], true);

$currentUserCompanyId = null;
$currentUserCanViewCompanyTickets = false;

if ($currentRole === 'CLIENT' && $currentUserId > 0) {
    $currentAccessStatement = $pdo->prepare(
        'SELECT company_id, can_view_company_tickets
         FROM users
         WHERE id = :user_id
         LIMIT 1'
    );
    $currentAccessStatement->execute(['user_id' => $currentUserId]);
    $currentAccess = $currentAccessStatement->fetch(PDO::FETCH_ASSOC) ?: [];

    $currentUserCompanyId = !empty($currentAccess['company_id'])
        ? (int)$currentAccess['company_id']
        : null;
    $currentUserCanViewCompanyTickets = (int)($currentAccess['can_view_company_tickets'] ?? 0) === 1;
}

$ticketSelect = "
    SELECT
        t.*,

        requester.name AS requester_name,
        requester.email AS requester_email,
        requester.phone AS requester_phone,
        requester.position AS requester_position,
        requester.company AS requester_company_legacy,
        requester.company_id AS requester_company_id,
        requester.profile_photo AS requester_profile_photo,

        assigned.name AS assigned_name,
        assigned.email AS assigned_email,
        assigned.tech_level AS assigned_level,
        assigned.profile_photo AS assigned_profile_photo,

        company.id AS client_company_id,
        company.ruc AS company_ruc,
        company.business_name AS company_business_name,
        company.trade_name AS company_trade_name,
        company.fiscal_address AS company_fiscal_address,
        company.phone AS company_phone,
        company.email AS company_email,
        company.sla_contract_type AS sla_contract_type,

        CASE
            WHEN t.first_response_at IS NOT NULL
            THEN TIMESTAMPDIFF(SECOND, t.created_at, t.first_response_at)
            ELSE NULL
        END AS tta_seconds_calendar,

        CASE
            WHEN t.closed_at IS NOT NULL
            THEN TIMESTAMPDIFF(SECOND, t.created_at, t.closed_at)
            ELSE NULL
        END AS ttr_seconds_calendar

    FROM tickets t
    INNER JOIN users requester ON requester.id = t.requester_id
    LEFT JOIN users assigned ON assigned.id = t.assigned_to
    LEFT JOIN client_companies company
        ON company.id = COALESCE(t.company_id, requester.company_id)
";

$params = ['ticket_id' => $ticketId];

if ($currentRole === 'CLIENT') {
    $accessWhere = 't.requester_id = :current_user_id';
    $params['current_user_id'] = $currentUserId;

    if ($currentUserCanViewCompanyTickets && $currentUserCompanyId !== null) {
        $accessWhere = "(
            t.requester_id = :current_user_id
            OR COALESCE(t.company_id, requester.company_id) = :current_company_id
        )";
        $params['current_company_id'] = $currentUserCompanyId;
    }

    $ticketSelect .= "
        WHERE t.id = :ticket_id
          AND $accessWhere
        LIMIT 1
    ";
} else {
    $ticketSelect .= "
        WHERE t.id = :ticket_id
        LIMIT 1
    ";
}

$ticketStatement = $pdo->prepare($ticketSelect);
$ticketStatement->execute($params);
$ticket = $ticketStatement->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['ticket_error'] = 'No se encontró el ticket o no tienes permiso para visualizarlo.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Mensajes públicos
|--------------------------------------------------------------------------
*/
$publicFormatSelect = ticketColumnExists($pdo, 'ticket_messages', 'message_format')
    ? 'tm.message_format'
    : "'plain' AS message_format";

$publicUpdatedSelect = ticketColumnExists($pdo, 'ticket_messages', 'updated_at')
    ? 'tm.updated_at'
    : 'NULL AS updated_at';

$userPhotoSelect = ticketColumnExists($pdo, 'users', 'profile_photo')
    ? 'u.profile_photo'
    : 'NULL AS profile_photo';

$messageStatement = $pdo->prepare(
    "SELECT
        tm.*,
        $publicFormatSelect,
        $publicUpdatedSelect,
        u.name,
        u.role,
        $userPhotoSelect
     FROM ticket_messages tm
     INNER JOIN users u ON u.id = tm.user_id
     WHERE tm.ticket_id = :ticket_id
     ORDER BY tm.created_at ASC, tm.id ASC"
);
$messageStatement->execute(['ticket_id' => $ticketId]);
$messages = $messageStatement->fetchAll(PDO::FETCH_ASSOC);

$messageAttachments = ticketLoadAttachmentsMap(
    $pdo,
    'PUBLIC',
    array_column($messages, 'id')
);

/*
|--------------------------------------------------------------------------
| Evaluación
|--------------------------------------------------------------------------
*/
$feedback = null;
$canSeeFeedback = in_array($currentRole, ['ADMIN', 'TECH'], true)
    || (
        $currentRole === 'CLIENT'
        && (int)$ticket['requester_id'] === $currentUserId
    );

if ($canSeeFeedback && ticketTableExists($pdo, 'ticket_feedback')) {
    $feedbackStatement = $pdo->prepare(
        'SELECT *
         FROM ticket_feedback
         WHERE ticket_id = :ticket_id
         LIMIT 1'
    );
    $feedbackStatement->execute(['ticket_id' => $ticketId]);
    $feedback = $feedbackStatement->fetch(PDO::FETCH_ASSOC) ?: null;
}

/*
|--------------------------------------------------------------------------
| Actividad
|--------------------------------------------------------------------------
*/
$activities = [];
$lastActivity = null;

if (ticketTableExists($pdo, 'ticket_activity')) {
    $activityStatement = $pdo->prepare(
        'SELECT
            ta.id,
            ta.ticket_id,
            ta.user_id,
            ta.actor_name,
            ta.actor_role,
            ta.activity_type,
            ta.activity_type AS action_type,
            ta.description,
            ta.old_value,
            ta.new_value,
            ta.created_at
         FROM ticket_activity ta
         WHERE ta.ticket_id = :ticket_id
         ORDER BY ta.created_at ASC, ta.id ASC'
    );
    $activityStatement->execute(['ticket_id' => $ticketId]);
    $activities = $activityStatement->fetchAll(PDO::FETCH_ASSOC);
$lastActivity = !empty($activities)
    ? $activities[array_key_last($activities)]
    : null;
}

/*
|--------------------------------------------------------------------------
| Conversación interna
|--------------------------------------------------------------------------
*/
$internalMessages = [];
$internalMessageAttachments = [];

if ($canUseInternalConversation && ticketTableExists($pdo, 'ticket_internal_messages')) {
    $internalFormatSelect = ticketColumnExists(
        $pdo,
        'ticket_internal_messages',
        'message_format'
    )
        ? 'tim.message_format'
        : "'plain' AS message_format";

    $internalStatement = $pdo->prepare(
        "SELECT
            tim.*,
            $internalFormatSelect,
            u.name,
            u.role,
            $userPhotoSelect
         FROM ticket_internal_messages tim
         INNER JOIN users u ON u.id = tim.user_id
         WHERE tim.ticket_id = :ticket_id
           AND (tim.deleted_at IS NULL OR tim.deleted_at = '0000-00-00 00:00:00')
         ORDER BY tim.created_at ASC, tim.id ASC"
    );
    $internalStatement->execute(['ticket_id' => $ticketId]);
    $internalMessages = $internalStatement->fetchAll(PDO::FETCH_ASSOC);

    $internalMessageAttachments = ticketLoadAttachmentsMap(
        $pdo,
        'INTERNAL',
        array_column($internalMessages, 'id')
    );
}

/*
|--------------------------------------------------------------------------
| Cliente y empresa
|--------------------------------------------------------------------------
*/
$clientInfoStatement = $pdo->prepare(
    'SELECT
        u.id,
        u.name,
        u.email,
        u.role,
        u.phone,
        u.position,
        u.company,
        u.company_id,
        u.profile_photo,
        u.created_at,
        c.ruc,
        c.business_name,
        c.trade_name,
        c.fiscal_address,
        c.phone AS company_phone,
        c.email AS company_email,
        c.sla_contract_type
     FROM users u
     LEFT JOIN client_companies c ON c.id = u.company_id
     WHERE u.id = :client_id
     LIMIT 1'
);
$clientInfoStatement->execute(['client_id' => (int)$ticket['requester_id']]);
$clientInfo = $clientInfoStatement->fetch(PDO::FETCH_ASSOC) ?: [];

$clientStatsStatement = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO') THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_tickets
     FROM tickets
     WHERE requester_id = :client_id"
);
$clientStatsStatement->execute(['client_id' => (int)$ticket['requester_id']]);
$clientStatsRaw = $clientStatsStatement->fetch(PDO::FETCH_ASSOC) ?: [];

$clientStats = [
    'total_tickets' => (int)($clientStatsRaw['total_tickets'] ?? 0),
    'open_tickets' => (int)($clientStatsRaw['open_tickets'] ?? 0),
    'closed_tickets' => (int)($clientStatsRaw['closed_tickets'] ?? 0),
];

$clientTicketsStatement = $pdo->prepare(
    'SELECT id, subject, status, priority, created_at
     FROM tickets
     WHERE requester_id = :client_id
     ORDER BY created_at DESC, id DESC'
);
$clientTicketsStatement->execute(['client_id' => (int)$ticket['requester_id']]);
$clientTickets = $clientTicketsStatement->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Cierre estructurado del ticket
|--------------------------------------------------------------------------
| Los motivos provienen de Herramientas > Motivos de cierre. El registro
| del cierre se almacena en ticket_closures para conservar la trazabilidad.
*/
$closureReasons = [];
$ticketClosure = null;
$closureModuleReady = ticketTableExists($pdo, 'closure_reasons')
    && ticketTableExists($pdo, 'ticket_closures');

if (ticketTableExists($pdo, 'closure_reasons')) {
    $closureReasonStatement = $pdo->query(
        'SELECT id, code, name, description, requires_comment
         FROM closure_reasons
         WHERE is_active = 1
         ORDER BY name ASC, id ASC'
    );
    $closureReasons = $closureReasonStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (ticketTableExists($pdo, 'ticket_closures')) {
    $ticketClosureStatement = $pdo->prepare(
        'SELECT
            tc.id,
            tc.ticket_id,
            tc.closure_reason_id,
            tc.reason_code,
            tc.reason_name,
            tc.comment,
            tc.closed_by,
            tc.closed_by_name,
            tc.closed_by_role,
            tc.closed_at,
            tc.sla_met,
            cr.description AS reason_description
         FROM ticket_closures tc
         LEFT JOIN closure_reasons cr ON cr.id = tc.closure_reason_id
         WHERE tc.ticket_id = :ticket_id
         ORDER BY tc.closed_at DESC, tc.id DESC
         LIMIT 1'
    );
    $ticketClosureStatement->execute(['ticket_id' => $ticketId]);
    $ticketClosure = $ticketClosureStatement->fetch(PDO::FETCH_ASSOC) ?: null;
}

$canCloseTicket = false;

/*
|--------------------------------------------------------------------------
| Permiso visual para cerrar ticket
|--------------------------------------------------------------------------
| El botón debe mostrarse para administradores, técnicos y para el cliente
| solicitante mientras el ticket siga abierto. El modal se encarga de avisar
| si no existen motivos activos configurados.
*/
if (($ticket['status'] ?? '') !== 'CERRADO') {
    if ($currentRole === 'ADMIN' || $currentRole === 'TECH') {
        $canCloseTicket = true;
    } elseif ($currentRole === 'CLIENT') {
        $canCloseTicket = (int)($ticket['requester_id'] ?? 0) === $currentUserId;
    }
}

$slaTimer = getSlaTimerData($ticket);
$closeTicketCsrfToken = systemSlaCsrfToken();

require __DIR__ . '/app/views/tickets/detail.php';
