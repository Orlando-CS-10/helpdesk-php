<?php
// ======================================================
// 1. CARGAR SESIÓN Y CONEXIÓN A BASE DE DATOS
// ======================================================
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';

// Obliga a que el usuario haya iniciado sesión
requireLogin();


// ======================================================
// 2. OBTENER ID DEL TICKET DESDE LA URL
//    Ejemplo: ticket-detail.php?id=11
// ======================================================
$ticketId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Si no llega un id válido, lo mandamos al home
if ($ticketId <= 0) {
    header('Location: /helpdesk-php/home.php');
    exit;
}


// ======================================================
// 3. DATOS DEL USUARIO ACTUAL
// ======================================================
$currentUser = user();
$currentRole = $currentUser['role'] ?? '';


// ======================================================
// 4. CONSULTAR EL TICKET SEGÚN EL ROL
//    - Si es CLIENT: solo puede ver sus propios tickets
//    - Si es ADMIN o TECH: puede ver cualquier ticket
//
//    Aquí también calculamos:
//    - tta_hours = tiempo entre apertura y primera respuesta
//    - ttr_hours = tiempo entre apertura y cierre
// ======================================================
if ($currentRole === 'CLIENT') {
    $sql = "SELECT
                t.*,
                u.name AS requester_name,
                a.name AS assigned_name,

                CASE
                    WHEN t.first_response_at IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, t.created_at, t.first_response_at)
                    ELSE NULL
                END AS tta_hours,

                CASE
                    WHEN t.closed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)
                    ELSE NULL
                END AS ttr_hours

            FROM tickets t
            INNER JOIN users u ON u.id = t.requester_id
            LEFT JOIN users a ON a.id = t.assigned_to
            WHERE t.id = :ticket_id
              AND t.requester_id = :requester_id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'ticket_id' => $ticketId,
        'requester_id' => (int)$currentUser['id']
    ]);
} else {
    $sql = "SELECT
                t.*,
                u.name AS requester_name,
                a.name AS assigned_name,

                CASE
                    WHEN t.first_response_at IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, t.created_at, t.first_response_at)
                    ELSE NULL
                END AS tta_hours,

                CASE
                    WHEN t.closed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, t.created_at, t.closed_at)
                    ELSE NULL
                END AS ttr_hours

            FROM tickets t
            INNER JOIN users u ON u.id = t.requester_id
            LEFT JOIN users a ON a.id = t.assigned_to
            WHERE t.id = :ticket_id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'ticket_id' => $ticketId
    ]);
}

// Guardamos el ticket encontrado
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe o no tiene permiso, lo mandamos al home
if (!$ticket) {
    header('Location: /helpdesk-php/home.php');
    exit;
}


// ======================================================
// 5. CARGAR MENSAJES DEL TICKET
//    Esto se usa en la pestaña "Conversación"
// ======================================================
$sqlMessages = "SELECT
                    tm.*,
                    u.name,
                    u.role
                FROM ticket_messages tm
                INNER JOIN users u ON u.id = tm.user_id
                WHERE tm.ticket_id = :ticket_id
                ORDER BY tm.created_at ASC";

$stmtMessages = $pdo->prepare($sqlMessages);
$stmtMessages->execute([
    'ticket_id' => $ticketId
]);

$messages = $stmtMessages->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 6. CARGAR FEEDBACK DEL TICKET
//    - El CLIENT puede ver el feedback de su ticket
//    - El ADMIN también puede verlo
// ======================================================
$feedback = null;

if (
    $currentRole === 'CLIENT' &&
    (int)$ticket['requester_id'] === (int)$currentUser['id']
) {
    $sqlFeedback = "SELECT *
                    FROM ticket_feedback
                    WHERE ticket_id = :ticket_id
                    LIMIT 1";

    $stmtFeedback = $pdo->prepare($sqlFeedback);
    $stmtFeedback->execute([
        'ticket_id' => $ticketId
    ]);

    $feedback = $stmtFeedback->fetch(PDO::FETCH_ASSOC);
}

if ($currentRole === 'ADMIN') {
    $sqlFeedback = "SELECT *
                    FROM ticket_feedback
                    WHERE ticket_id = :ticket_id
                    LIMIT 1";

    $stmtFeedback = $pdo->prepare($sqlFeedback);
    $stmtFeedback->execute([
        'ticket_id' => $ticketId
    ]);

    $feedback = $stmtFeedback->fetch(PDO::FETCH_ASSOC);
}


// ======================================================
// 7. CARGAR ACTIVIDAD DEL TICKET
//    Esto se usa en la pestaña "Actividad de ticket"
// ======================================================
$sqlActivities = "SELECT
                    ta.id,
                    ta.ticket_id,
                    ta.user_id,
                    ta.actor_name,
                    ta.actor_role,
                    ta.activity_type,
                    ta.description,
                    ta.old_value,
                    ta.new_value,
                    ta.created_at
                  FROM ticket_activity ta
                  WHERE ta.ticket_id = :ticket_id
                  ORDER BY ta.created_at DESC, ta.id DESC";

$stmtActivities = $pdo->prepare($sqlActivities);
$stmtActivities->execute([
    'ticket_id' => $ticketId
]);

$activities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 8. CARGAR INFORMACIÓN DEL CLIENTE
//    Esto se usa en el panel derecho del admin
// ======================================================
$clientInfo = null;

$sqlClient = "SELECT
                id,
                name,
                email,
                role,
                phone,
                position,
                company,
                created_at
              FROM users
              WHERE id = :client_id
              LIMIT 1";

$stmtClient = $pdo->prepare($sqlClient);
$stmtClient->execute([
    'client_id' => $ticket['requester_id']
]);

$clientInfo = $stmtClient->fetch(PDO::FETCH_ASSOC);


// ======================================================
// 9. ESTADÍSTICAS DEL CLIENTE
//    - total_tickets
//    - open_tickets
//    - closed_tickets
// ======================================================
$clientStats = [
    'total_tickets' => 0,
    'open_tickets' => 0,
    'closed_tickets' => 0
];

$sqlClientStats = "SELECT
                    COUNT(*) AS total_tickets,
                    SUM(CASE WHEN status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO') THEN 1 ELSE 0 END) AS open_tickets,
                    SUM(CASE WHEN status = 'CERRADO' THEN 1 ELSE 0 END) AS closed_tickets
                  FROM tickets
                  WHERE requester_id = :client_id";

$stmtClientStats = $pdo->prepare($sqlClientStats);
$stmtClientStats->execute([
    'client_id' => $ticket['requester_id']
]);

$clientStatsRaw = $stmtClientStats->fetch(PDO::FETCH_ASSOC);

if ($clientStatsRaw) {
    $clientStats = [
        'total_tickets' => (int)($clientStatsRaw['total_tickets'] ?? 0),
        'open_tickets' => (int)($clientStatsRaw['open_tickets'] ?? 0),
        'closed_tickets' => (int)($clientStatsRaw['closed_tickets'] ?? 0),
    ];
}


// ======================================================
// 10. CARGAR TODOS LOS TICKETS DEL CLIENTE
//     Esto se usa en el desplegable / modal del panel derecho
// ======================================================
$clientTickets = [];

$sqlClientTickets = "SELECT
                        id,
                        subject,
                        status,
                        priority,
                        created_at
                     FROM tickets
                     WHERE requester_id = :client_id
                     ORDER BY created_at DESC";

$stmtClientTickets = $pdo->prepare($sqlClientTickets);
$stmtClientTickets->execute([
    'client_id' => $ticket['requester_id']
]);

$clientTickets = $stmtClientTickets->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 11. CARGAR LA VISTA FINAL
//     Aquí enviamos todas las variables a:
//     app/views/tickets/detail.php
// ======================================================
require __DIR__ . '/app/views/tickets/detail.php';