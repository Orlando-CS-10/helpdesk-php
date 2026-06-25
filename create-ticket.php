<?php
// El ticket y su SLA se registran con hora de Perú.
if (date_default_timezone_get() !== 'America/Lima') {
    date_default_timezone_set('America/Lima');
}
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/notifications.php';
require_once __DIR__ . '/app/helpers/ticket_activity.php';
require_once __DIR__ . '/app/helpers/technician_assignment.php';
require_once __DIR__ . '/app/helpers/system_sla.php';

requireLogin();

$currentUser = user();

if (($currentUser['role'] ?? '') !== 'CLIENT') {
    header('Location: home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: home.php');
    exit;
}

$subject = trim($_POST['subject'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority = trim($_POST['priority'] ?? '');
$category = trim($_POST['category'] ?? '');

$allowedPriorities = ['BAJA', 'MEDIA', 'ALTA'];
$allowedCategories = ['ACCESO', 'SISTEMA', 'HARDWARE', 'SOFTWARE', 'RED', 'OTROS'];

if ($subject === '' || $description === '' || $priority === '' || $category === '') {
    $_SESSION['ticket_error'] = 'Todos los campos son obligatorios.';
    header('Location: /helpdesk-php/app/views/client/create-ticket.php');
    exit;
}

if (!in_array($priority, $allowedPriorities, true)) {
    $_SESSION['ticket_error'] = 'La prioridad seleccionada no es válida.';
    header('Location: /helpdesk-php/app/views/client/create-ticket.php');
    exit;
}

if (!in_array($category, $allowedCategories, true)) {
    $_SESSION['ticket_error'] = 'La categoría seleccionada no es válida.';
    header('Location: /helpdesk-php/app/views/client/create-ticket.php');
    exit;
}

// Resolver el perfil SLA de la empresa y tomar una fotografía de sus reglas.
$createdAt = (new DateTime('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
$slaData = systemSlaResolveForRequester(
    $pdo,
    (int)$currentUser['id'],
    $priority,
    $createdAt
);
$slaHours = max(1, (int)ceil(((int)$slaData['ttr_minutes']) / 60));
$companyId = $slaData['company_id'];

/*
|--------------------------------------------------------------------------
| Asignación automática por nivel
|--------------------------------------------------------------------------
| Al crear el ticket, el sistema busca automáticamente un técnico de nivel 1
| con menor cantidad de tickets activos.
*/
$assignedTech = getSmartTechnicianAssignment($pdo, 1);

$assignedTo = $assignedTech ? (int)$assignedTech['id'] : null;
$supportLevel = $assignedTech ? (int)$assignedTech['tech_level'] : 1;
$initialStatus = $assignedTo !== null ? 'EN_PROCESO' : 'ABIERTO';

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO tickets (
                requester_id,
                company_id,
                assigned_to,
                subject,
                description,
                status,
                priority,
                category,
                client_closed,
                sla_hours,
                support_level,
                level_started_at,
                level_first_response_at,
                created_at,
                updated_at
            ) VALUES (
                :requester_id,
                :company_id,
                :assigned_to,
                :subject,
                :description,
                :status,
                :priority,
                :category,
                0,
                :sla_hours,
                :support_level,
                :level_started_at,
                NULL,
                :created_at,
                :updated_at
            )";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':requester_id', (int)$currentUser['id'], PDO::PARAM_INT);

    if ($companyId === null) {
        $stmt->bindValue(':company_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':company_id', (int)$companyId, PDO::PARAM_INT);
    }

    if ($assignedTo === null) {
        $stmt->bindValue(':assigned_to', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':assigned_to', $assignedTo, PDO::PARAM_INT);
    }

    $stmt->bindValue(':subject', $subject);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':status', $initialStatus);
    $stmt->bindValue(':priority', $priority);
    $stmt->bindValue(':category', $category);
    $stmt->bindValue(':sla_hours', $slaHours, PDO::PARAM_INT);
    $stmt->bindValue(':support_level', $supportLevel, PDO::PARAM_INT);
    $stmt->bindValue(':level_started_at', $createdAt);
    $stmt->bindValue(':created_at', $createdAt);
    $stmt->bindValue(':updated_at', $createdAt);

    $stmt->execute();

    $createdTicketId = (int)$pdo->lastInsertId();

    if (systemSlaColumnExists($pdo, 'tickets', 'sla_profile_id')) {
        $slaUpdate = $pdo->prepare(
            'UPDATE tickets SET
                sla_profile_id = :profile_id,
                sla_profile_name = :profile_name,
                sla_schedule_type = :schedule_type,
                sla_work_start = :work_start,
                sla_work_end = :work_end,
                sla_work_days = :work_days,
                sla_warning_percent = :warning_percent,
                sla_critical_percent = :critical_percent,
                sla_tta_minutes = :tta_minutes,
                sla_ttr_minutes = :ttr_minutes,
                sla_tta_due_at = :tta_due_at,
                sla_ttr_due_at = :ttr_due_at
             WHERE id = :ticket_id'
        );
        $slaUpdate->execute([
            'profile_id' => $slaData['profile_id'],
            'profile_name' => $slaData['profile_name'],
            'schedule_type' => $slaData['schedule_type'],
            'work_start' => $slaData['work_start'],
            'work_end' => $slaData['work_end'],
            'work_days' => $slaData['work_days'],
            'warning_percent' => $slaData['warning_percent'],
            'critical_percent' => $slaData['critical_percent'],
            'tta_minutes' => $slaData['tta_minutes'],
            'ttr_minutes' => $slaData['ttr_minutes'],
            'tta_due_at' => $slaData['tta_due_at'],
            'ttr_due_at' => $slaData['ttr_due_at'],
            'ticket_id' => $createdTicketId,
        ]);
    }

    // Registrar actividad de creación
    createTicketActivity(
        $pdo,
        $createdTicketId,
        (int)$currentUser['id'],
        $currentUser['name'],
        $currentUser['role'],
        'CREATED',
        'El cliente creó el ticket.'
    );

    // Registrar actividad de asignación automática
    if ($assignedTo !== null) {
        createTicketActivity(
            $pdo,
            $createdTicketId,
            (int)$currentUser['id'],
            $currentUser['name'],
            $currentUser['role'],
            'AUTO_ASSIGNED',
            'El sistema asignó automáticamente el ticket al técnico de nivel ' . $supportLevel . ': ' . $assignedTech['name'] . ', considerando la menor carga de tickets activos.',
            null,
            (string)$assignedTo
        );

        createNotification(
            $pdo,
            $assignedTo,
            'Nuevo ticket asignado',
            'Se te asignó automáticamente el ticket #' . $createdTicketId . '.',
            'info',
            $createdTicketId
        );
    }

    // Notificar a administradores
    notifyAdmins(
        $pdo,
        'Nuevo ticket creado',
        'Se registró el ticket #' . $createdTicketId . ' por el cliente ' . $currentUser['name'] . '.',
        'info',
        $createdTicketId
    );

    $pdo->commit();

    if ($assignedTo !== null) {
        $_SESSION['ticket_success'] = 'El ticket fue creado y asignado automáticamente a un técnico de nivel 1.';
    } else {
        $_SESSION['ticket_success'] = 'El ticket fue creado correctamente, pero no hay técnicos de nivel 1 disponibles.';
    }

    header('Location: /helpdesk-php/home.php');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['ticket_error'] = 'Ocurrió un error al crear el ticket.';
    header('Location: /helpdesk-php/app/views/client/create-ticket.php');
    exit;
}