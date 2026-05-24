<?php
if (!function_exists('user')) {
    require_once __DIR__ . '/../../helpers/session.php';
}

require_once __DIR__ . '/../../helpers/business_hours.php';

$ticket = $ticket ?? null;
$messages = $messages ?? [];
$activities = $activities ?? [];
$clientTickets = $clientTickets ?? [];
$clientInfo = $clientInfo ?? [];
$clientStats = $clientStats ?? [];
$feedback = $feedback ?? null;

if (empty($ticket) || empty($ticket['id'])) {
    $_SESSION['ticket_error'] = 'No se encontró información del ticket.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

$title = 'Detalle del Ticket';

$isAdminView = (user()['role'] ?? '') === 'ADMIN';

/*
|--------------------------------------------------------------------------
| Variables para layouts admin
|--------------------------------------------------------------------------
| Solo se usan cuando el detalle se abre como ADMIN.
*/
$activePage = 'tickets';
$pageTitle = 'Detalle del Ticket';
$pageSubtitle = 'Consulta la información completa, tiempos operativos, conversación y trazabilidad del caso.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-tickets.php',
        'class' => 'btn-secondary',
        'text' => 'Volver a tickets'
    ],
    [
        'href' => '/helpdesk-php/home.php',
        'class' => 'btn-secondary',
        'text' => 'Ir al inicio'
    ]
];

require_once __DIR__ . '/../layouts/header.php';
?>

<?php if ($isAdminView): ?>
<div class="admin-shell">

    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">

        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content">
            <div class="ticket-admin-layout">

                <!-- ============ COLUMNA PRINCIPAL ============ -->
                <div class="ticket-admin-main">
                    <div class="ticket-detail-layout">

                        <!-- INFORMACIÓN GENERAL DEL TICKET -->
                        <section class="card ticket-detail-card">
                            <div class="ticket-detail-header">
                                <div>
                                    <div class="ticket-detail-code">Ticket #<?= (int)$ticket['id'] ?></div>
                                    <h2><?= htmlspecialchars($ticket['subject'] ?? 'Sin asunto') ?></h2>
                                    <p class="ticket-detail-description">
                                        <?= nl2br(htmlspecialchars($ticket['description'] ?? '')) ?>
                                    </p>
                                </div>

                                <div class="ticket-detail-badges">
                                    <span class="ticket-badge status-badge"><?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $ticket['status'])))) ?></span>
                                    <span class="ticket-badge priority-badge">Prioridad: <?= htmlspecialchars($ticket['priority'] ?? 'N/D') ?></span>
                                </div>
                            </div>

                            <!-- GRID DE DATOS -->
                            <div class="ticket-info-grid">

                                <div class="ticket-info-item">
                                    <span class="label">Asignado a</span>
                                    <strong><?= !empty($ticket['assigned_name']) ? htmlspecialchars($ticket['assigned_name']) : 'Sin asignar' ?></strong>
                                </div>

                                <div class="ticket-info-item">
                                    <span class="label">Categoría</span>
                                    <strong><?= htmlspecialchars($ticket['category'] ?? 'No definida') ?></strong>
                                </div>

                                <div class="ticket-info-item">
                                    <span class="label">Fecha de creación</span>
                                    <strong><?= !empty($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : 'No disponible' ?></strong>
                                </div>

                                <div class="ticket-info-item">
                                    <span class="label">Última actualización</span>
                                    <strong><?= !empty($ticket['updated_at']) ? date('d/m/Y H:i', strtotime($ticket['updated_at'])) : 'No disponible' ?></strong>
                                </div>

                                <div class="ticket-info-item">
                                    <span class="label">Cerrado por cliente</span>
                                    <strong><?= ((int)($ticket['client_closed'] ?? 0) === 1) ? 'Sí' : 'No' ?></strong>
                                </div>

                                <!-- INDICADORES ATERRIZADOS -->
                                <div class="ticket-info-item">
                                    <span class="label">SLA objetivo</span>
                                    <strong><?= (int)($ticket['sla_hours'] ?? 0) ?> horas</strong>
                                </div>

                                <div class="ticket-info-item">
                                    <span class="label">Tiempo de respuesta (TTA)</span>
                                    <?php
                                    $firstResponseAt = $ticket['level_first_response_at']
                                        ?? $ticket['first_response_at']
                                        ?? null;

                                    $ttaDetailLabel = formatBusinessTimeStatus(
                                        $ticket['created_at'] ?? null,
                                        $firstResponseAt,
                                        empty($firstResponseAt)
                                    );

                                    $ttaDetailClass = match ($ttaDetailLabel) {
                                        'Pendiente', 'Fuera de horario' => 'pending',
                                        default => 'success',
                                    };
                                    ?>
                                    <strong class="<?= $ttaDetailClass ?>">
                                        <?= htmlspecialchars($ttaDetailLabel) ?>
                                    </strong>
                                </div>

                                <div class="ticket-info-item">
                                    <span class="label">Tiempo de resolución (TTR)</span>
                                    <?php
                                    $closedAt = $ticket['closed_at'] ?? null;

                                    if (empty($closedAt) && ($ticket['status'] ?? '') === 'CERRADO') {
                                        $closedAt = $ticket['updated_at'] ?? null;
                                    }

                                    $ttrDetailLabel = formatBusinessTimeStatus(
                                        $ticket['created_at'] ?? null,
                                        $closedAt,
                                        ($ticket['status'] ?? '') !== 'CERRADO'
                                    );

                                    $ttrDetailClass = match ($ttrDetailLabel) {
                                        'Pendiente', 'Fuera de horario' => 'pending',
                                        default => 'success',
                                    };
                                    ?>
                                    <strong class="<?= $ttrDetailClass ?>">
                                        <?= htmlspecialchars($ttrDetailLabel) ?>
                                    </strong>
                                </div>

                                <div class="ticket-info-item">
                                    <span class="label">Cumplimiento SLA</span>
                                    <strong class="<?=
                                        ($ticket['sla_met'] ?? null) === null ? 'pending' :
                                        ((int)$ticket['sla_met'] === 1 ? 'success' : 'danger')
                                    ?>">
                                        <?php if (($ticket['sla_met'] ?? null) === null): ?>
                                            Pendiente
                                        <?php elseif ((int)$ticket['sla_met'] === 1): ?>
                                            Cumplido
                                        <?php else: ?>
                                            No cumplido
                                        <?php endif; ?>
                                    </strong>
                                </div>
                            </div>

                            <!-- FEEDBACK DEL CLIENTE -->
                            <?php if (!empty($feedback)): ?>
                                <div class="ticket-section-title" style="margin-top:22px;">
                                    <h3>Evaluación del cliente</h3>
                                    <p>Retroalimentación registrada después del cierre del ticket.</p>
                                </div>

                                <div class="feedback-summary">
                                    <div class="feedback-item">
                                        <span class="label">Calificación</span>
                                        <strong><?= (int)$feedback['rating'] ?>/5</strong>
                                    </div>

                                    <div class="feedback-item">
                                        <span class="label">¿Se resolvió?</span>
                                        <strong><?= htmlspecialchars($feedback['resolved']) ?></strong>
                                    </div>

                                    <div class="feedback-item full">
                                        <span class="label">Comentario</span>
                                        <strong>
                                            <?= !empty($feedback['comment']) ? nl2br(htmlspecialchars($feedback['comment'])) : 'Sin comentario.' ?>
                                        </strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>

                        <!-- TABS: CONVERSACIÓN / ACTIVIDAD -->
                        <section class="card ticket-tabs-card">
                            <div class="ticket-tabs-header">
                                <button class="ticket-tab-btn active" type="button" onclick="showTicketTab('conversationTab', this)">
                                    Conversación (<?= count($messages ?? []) ?>)
                                </button>

                                <button class="ticket-tab-btn" type="button" onclick="showTicketTab('activityTab', this)">
                                    Actividad de ticket (<?= count($activities ?? []) ?>)
                                </button>
                            </div>

                            <!-- TAB CONVERSACIÓN -->
                            <div class="ticket-tab-panel active" id="conversationTab">
                                <div class="ticket-section-title">
                                    <h3>Conversación del ticket</h3>
                                    <p>Historial de respuestas y seguimiento del caso.</p>
                                </div>

                                <?php if (!empty($messages)): ?>
                                    <div class="ticket-messages-list">
                                        <?php foreach ($messages as $message): ?>
                                            <div class="ticket-message-item">
                                                <div class="ticket-message-top">
                                                    <div>
                                                        <strong><?= htmlspecialchars($message['name']) ?></strong>
                                                        <span class="message-role"><?= htmlspecialchars($message['role']) ?></span>
                                                    </div>

                                                    <div class="message-right">
                                                        <span class="message-date">
                                                            <?= !empty($message['created_at']) ? date('d/m/Y H:i', strtotime($message['created_at'])) : '' ?>
                                                        </span>

                                                        <?php if (($ticket['status'] ?? '') !== 'CERRADO'): ?>
                                                            <div class="message-actions-inline">
                                                                <a
                                                                    href="/helpdesk-php/edit-message.php?id=<?= (int)$message['id'] ?>"
                                                                    class="message-edit-btn"
                                                                    title="Editar mensaje"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l8.06-8.06.92.92L5.92 19.58zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.13 1.13 3.75 3.75 1.14-1.13z"/>
                                                                    </svg>
                                                                </a>

                                                                <a
                                                                    href="#"
                                                                    class="message-delete-btn"
                                                                    title="Eliminar mensaje"
                                                                    onclick="openDeleteModal(<?= (int)$message['id'] ?>); return false;"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                                                                    </svg>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="ticket-message-body">
                                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-ticket-box">
                                        <h4>No hay mensajes todavía</h4>
                                        <p>Este ticket aún no tiene respuestas registradas.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- TAB ACTIVIDAD -->
                            <div class="ticket-tab-panel" id="activityTab">
                                <div class="ticket-section-title">
                                    <h3>Actividad del ticket</h3>
                                    <p>Seguimiento de acciones, cambios y eventos registrados.</p>
                                </div>

                                <?php if (!empty($activities)): ?>
                                    <div class="ticket-activity-list">
                                        <?php foreach ($activities as $activity): ?>
                                            <div class="ticket-activity-item">
                                                <div class="ticket-activity-icon">•</div>

                                                <div class="ticket-activity-content">
                                                    <div class="ticket-activity-top">
                                                        <strong><?= htmlspecialchars($activity['description']) ?></strong>
                                                        <span><?= !empty($activity['created_at']) ? date('d/m/Y H:i', strtotime($activity['created_at'])) : '' ?></span>
                                                    </div>

                                                    <p>
                                                        <?= !empty($activity['actor_name']) ? htmlspecialchars($activity['actor_name']) : 'Sistema' ?>
                                                        <?php if (!empty($activity['actor_role'])): ?>
                                                            <span class="activity-role"><?= htmlspecialchars($activity['actor_role']) ?></span>
                                                        <?php endif; ?>
                                                    </p>

                                                    <?php if (!empty($activity['old_value']) || !empty($activity['new_value'])): ?>
                                                        <div class="activity-change-meta">
                                                            <?php if (!empty($activity['old_value'])): ?>
                                                                <span><strong>Antes:</strong> <?= htmlspecialchars($activity['old_value']) ?></span>
                                                            <?php endif; ?>

                                                            <?php if (!empty($activity['new_value'])): ?>
                                                                <span><strong>Después:</strong> <?= htmlspecialchars($activity['new_value']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-ticket-box">
                                        <h4>No hay actividad registrada</h4>
                                        <p>Aún no se registraron eventos en la bitácora del ticket.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- FORMULARIO DE RESPUESTA -->
                        <?php if (($ticket['status'] ?? '') !== 'CERRADO'): ?>
                            <section class="card ticket-reply-card">
                                <div class="ticket-section-title">
                                    <h3>Responder ticket</h3>
                                    <p>Escribe un mensaje para dar seguimiento a esta incidencia.</p>
                                </div>

                                <?php if (!empty($_SESSION['ticket_error'])): ?>
                                    <div class="alert error">
                                        <?= htmlspecialchars($_SESSION['ticket_error']) ?>
                                    </div>
                                    <?php unset($_SESSION['ticket_error']); ?>
                                <?php endif; ?>

                                <?php if (!empty($_SESSION['ticket_success'])): ?>
                                    <div class="alert success">
                                        <?= htmlspecialchars($_SESSION['ticket_success']) ?>
                                    </div>
                                    <?php unset($_SESSION['ticket_success']); ?>
                                <?php endif; ?>

                                <form action="/helpdesk-php/reply-ticket.php" method="POST" class="ticket-form">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                                    <div class="form-group">
                                        <label for="message">Mensaje</label>
                                        <textarea
                                            id="message"
                                            name="message"
                                            rows="6"
                                            placeholder="Escribe tu respuesta aquí..."
                                            required
                                        ></textarea>
                                    </div>

                                    <div class="ticket-form-actions">
                                        <a href="/helpdesk-php/admin-tickets.php" class="btn-secondary">Atrás</a>
                                        <button type="submit" class="btn-primary">Enviar respuesta</button>
                                    </div>
                                </form>
                            </section>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- ============ SIDEBAR DERECHO CLIENTE ============ -->
                <aside class="ticket-client-sidebar">
                    <div class="card ticket-client-card">
                        <div class="ticket-client-card-header">
                            <h3>Información del cliente</h3>
                            <p>Datos relacionados al solicitante del ticket.</p>
                        </div>

                        <?php if (!empty($clientInfo)): ?>
                            <div class="ticket-client-profile">
                                <div class="ticket-client-avatar">
                                    <?= strtoupper(substr($clientInfo['name'] ?? 'C', 0, 1)) ?>
                                </div>

                                <div>
                                    <strong><?= htmlspecialchars($clientInfo['name'] ?? 'Cliente') ?></strong>
                                    <p><?= htmlspecialchars($clientInfo['role'] ?? 'CLIENT') ?></p>
                                </div>
                            </div>

                            <div class="ticket-client-info-list">
                                <div class="ticket-client-info-item">
                                    <span>Nombre</span>
                                    <strong><?= htmlspecialchars($clientInfo['name'] ?? 'No registrado') ?></strong>
                                </div>

                                <div class="ticket-client-info-item">
                                    <span>Rol</span>
                                    <strong><?= htmlspecialchars($clientInfo['role'] ?? 'No registrado') ?></strong>
                                </div>

                                <div class="ticket-client-info-item">
                                    <span>Correo</span>
                                    <strong><?= htmlspecialchars($clientInfo['email'] ?? 'No registrado') ?></strong>
                                </div>

                                <div class="ticket-client-info-item">
                                    <span>Teléfono</span>
                                    <strong><?= !empty($clientInfo['phone']) ? htmlspecialchars($clientInfo['phone']) : 'No registrado' ?></strong>
                                </div>

                                <div class="ticket-client-info-item">
                                    <span>Cargo</span>
                                    <strong><?= !empty($clientInfo['position']) ? htmlspecialchars($clientInfo['position']) : 'No registrado' ?></strong>
                                </div>

                                <div class="ticket-client-info-item">
                                    <span>Empresa</span>
                                    <strong><?= !empty($clientInfo['company']) ? htmlspecialchars($clientInfo['company']) : 'No registrado' ?></strong>
                                </div>
                            </div>

                            <!-- Acordeón de tickets del cliente -->
                            <div class="ticket-client-accordion">
                                <button type="button" class="ticket-client-accordion-btn" onclick="toggleClientTickets()">
                                    <span>Tickets del cliente (<?= (int)($clientStats['total_tickets'] ?? 0) ?>)</span>
                                    <span id="clientTicketsArrow" class="ticket-client-accordion-arrow">▾</span>
                                </button>

                                <div id="clientTicketsPanel" class="ticket-client-accordion-panel">
                                    <?php if (!empty($clientTickets)): ?>
                                        <div class="ticket-client-tickets-list">
                                            <?php foreach (array_slice($clientTickets, 0, 5) as $clientTicket): ?>
                                                <a
                                                    href="/helpdesk-php/ticket-detail.php?id=<?= (int)$clientTicket['id'] ?>"
                                                    class="ticket-client-ticket-item"
                                                >
                                                    <div class="ticket-client-ticket-top">
                                                        <strong>#<?= (int)$clientTicket['id'] ?> - <?= htmlspecialchars($clientTicket['subject']) ?></strong>
                                                    </div>

                                                    <div class="ticket-client-ticket-meta">
                                                        <span><?= htmlspecialchars($clientTicket['status']) ?></span>
                                                        <span><?= htmlspecialchars($clientTicket['priority']) ?></span>
                                                        <span><?= date('d/m/Y', strtotime($clientTicket['created_at'])) ?></span>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>

                                        <?php if (count($clientTickets) > 5): ?>
                                            <div class="ticket-client-more-wrap">
                                                <button type="button" class="ticket-client-more-btn" onclick="openAllClientTicketsModal()">
                                                    Mostrar más
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="empty-ticket-box">
                                            <h4>No tiene tickets registrados</h4>
                                            <p>Este cliente todavía no tiene historial de tickets.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-ticket-box">
                                <h4>No hay datos del cliente</h4>
                                <p>No se pudo cargar la información del solicitante.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</div>

<?php else: ?>
<!-- ==========================================================
     VISTA NORMAL PARA CLIENT / TECH
     ========================================================== -->
<div class="panel">
    <div class="topbar">
        <h1>Detalle del Ticket</h1>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="/helpdesk-php/home.php" class="btn-secondary">Ir al inicio</a>
            <a href="/helpdesk-php/logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="ticket-detail-layout">

        <section class="card ticket-detail-card">
            <div class="ticket-detail-header">
                <div>
                    <div class="ticket-detail-code">Ticket #<?= (int)$ticket['id'] ?></div>
                    <h2><?= htmlspecialchars($ticket['subject'] ?? 'Sin asunto') ?></h2>
                    <p class="ticket-detail-description">
                        <?= nl2br(htmlspecialchars($ticket['description'] ?? '')) ?>
                    </p>
                </div>

                <div class="ticket-detail-badges">
                    <span class="ticket-badge status-badge"><?= htmlspecialchars($ticket['status'] ?? 'N/D') ?></span>
                    <span class="ticket-badge priority-badge">Prioridad: <?= htmlspecialchars($ticket['priority'] ?? 'N/D') ?></span>
                </div>
            </div>

            <div class="ticket-info-grid">
                <div class="ticket-info-item">
                    <span class="label">Cliente</span>
                    <strong><?= htmlspecialchars($ticket['requester_name'] ?? 'No disponible') ?></strong>
                </div>

                <div class="ticket-info-item">
                    <span class="label">Asignado a</span>
                    <strong><?= !empty($ticket['assigned_name']) ? htmlspecialchars($ticket['assigned_name']) : 'Sin asignar' ?></strong>
                </div>

                <div class="ticket-info-item">
                    <span class="label">Categoría</span>
                    <strong><?= htmlspecialchars($ticket['category'] ?? 'No definida') ?></strong>
                </div>

                <div class="ticket-info-item">
                    <span class="label">SLA objetivo</span>
                    <strong><?= (int)($ticket['sla_hours'] ?? 0) ?> horas</strong>
                </div>

                <div class="ticket-info-item">
                    <span class="label">Tiempo de respuesta (TTA)</span>
                    <?php
                    $firstResponseAt = $ticket['level_first_response_at']
                        ?? $ticket['first_response_at']
                        ?? null;

                    $ttaDetailLabel = formatBusinessTimeStatus(
                        $ticket['created_at'] ?? null,
                        $firstResponseAt,
                        empty($firstResponseAt)
                    );

                    $ttaDetailClass = match ($ttaDetailLabel) {
                        'Pendiente', 'Fuera de horario' => 'pending',
                        default => 'success',
                    };
                    ?>
                    <strong class="<?= $ttaDetailClass ?>">
                        <?= htmlspecialchars($ttaDetailLabel) ?>
                    </strong>
                </div>

                <div class="ticket-info-item">
                    <span class="label">Tiempo de resolución (TTR)</span>
                    <?php
                    $closedAt = $ticket['closed_at'] ?? null;

                    if (empty($closedAt) && ($ticket['status'] ?? '') === 'CERRADO') {
                        $closedAt = $ticket['updated_at'] ?? null;
                    }

                    $ttrDetailLabel = formatBusinessTimeStatus(
                        $ticket['created_at'] ?? null,
                        $closedAt,
                        ($ticket['status'] ?? '') !== 'CERRADO'
                    );

                    $ttrDetailClass = match ($ttrDetailLabel) {
                        'Pendiente', 'Fuera de horario' => 'pending',
                        default => 'success',
                    };
                    ?>
                    <strong class="<?= $ttrDetailClass ?>">
                        <?= htmlspecialchars($ttrDetailLabel) ?>
                    </strong>
                </div>

                <div class="ticket-info-item">
                    <span class="label">Cumplimiento SLA</span>
                    <strong class="<?=
                        ($ticket['sla_met'] ?? null) === null ? 'pending' :
                        ((int)$ticket['sla_met'] === 1 ? 'success' : 'danger')
                    ?>">
                        <?php if (($ticket['sla_met'] ?? null) === null): ?>
                            Pendiente
                        <?php elseif ((int)$ticket['sla_met'] === 1): ?>
                            Cumplido
                        <?php else: ?>
                            No cumplido
                        <?php endif; ?>
                    </strong>
                </div>
            </div>

            <?php if (
                (user()['role'] ?? '') === 'CLIENT' &&
                (int)($ticket['requester_id'] ?? 0) === (int)(user()['id'] ?? 0) &&
                ($ticket['status'] ?? '') !== 'CERRADO'
            ): ?>
                <div class="ticket-form-actions" style="margin-top:18px;">
                    <a
                        href="#"
                        class="btn-primary"
                        onclick="openCloseTicketModal(<?= (int)$ticket['id'] ?>); return false;"
                    >
                        Cerrar ticket
                    </a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Tabs también para cliente/tech -->
        <section class="card ticket-tabs-card">
            <div class="ticket-tabs-header">
                <button class="ticket-tab-btn active" type="button" onclick="showTicketTab('conversationTab', this)">
                    Conversación (<?= count($messages ?? []) ?>)
                </button>

                <button class="ticket-tab-btn" type="button" onclick="showTicketTab('activityTab', this)">
                    Actividad de ticket (<?= count($activities ?? []) ?>)
                </button>
            </div>

            <div class="ticket-tab-panel active" id="conversationTab">
                <div class="ticket-section-title">
                    <h3>Conversación del ticket</h3>
                    <p>Historial de respuestas y seguimiento.</p>
                </div>

                <?php if (!empty($messages)): ?>
                    <div class="ticket-messages-list">
                        <?php foreach ($messages as $message): ?>
                            <div class="ticket-message-item">
                                <div class="ticket-message-top">
                                    <div>
                                        <strong><?= htmlspecialchars($message['name']) ?></strong>
                                        <span class="message-role"><?= htmlspecialchars($message['role']) ?></span>
                                    </div>

                                    <div class="message-right">
                                        <span class="message-date">
                                            <?= !empty($message['created_at']) ? date('d/m/Y H:i', strtotime($message['created_at'])) : '' ?>
                                        </span>

                                        <?php if (
                                            ($ticket['status'] ?? '') !== 'CERRADO' &&
                                            (
                                                (user()['role'] ?? '') !== 'CLIENT' ||
                                                (int)$message['user_id'] == (int)(user()['id'] ?? 0)
                                            )
                                        ): ?>
                                            <div class="message-actions-inline">
                                                <a
                                                    href="/helpdesk-php/edit-message.php?id=<?= (int)$message['id'] ?>"
                                                    class="message-edit-btn"
                                                    title="Editar mensaje"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l8.06-8.06.92.92L5.92 19.58zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.13 1.13 3.75 3.75 1.14-1.13z"/>
                                                    </svg>
                                                </a>

                                                <a
                                                    href="#"
                                                    class="message-delete-btn"
                                                    title="Eliminar mensaje"
                                                    onclick="openDeleteModal(<?= (int)$message['id'] ?>); return false;"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="ticket-message-body">
                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-ticket-box">
                        <h4>No hay mensajes todavía</h4>
                        <p>Este ticket aún no tiene respuestas registradas.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ticket-tab-panel" id="activityTab">
                <div class="ticket-section-title">
                    <h3>Actividad del ticket</h3>
                    <p>Seguimiento de acciones, cambios y eventos registrados.</p>
                </div>

                <?php if (!empty($activities)): ?>
                    <div class="ticket-activity-list">
                        <?php foreach ($activities as $activity): ?>
                            <div class="ticket-activity-item">
                                <div class="ticket-activity-icon">•</div>

                                <div class="ticket-activity-content">
                                    <div class="ticket-activity-top">
                                        <strong><?= htmlspecialchars($activity['description']) ?></strong>
                                        <span><?= !empty($activity['created_at']) ? date('d/m/Y H:i', strtotime($activity['created_at'])) : '' ?></span>
                                    </div>

                                    <p>
                                        <?= !empty($activity['actor_name']) ? htmlspecialchars($activity['actor_name']) : 'Sistema' ?>
                                        <?php if (!empty($activity['actor_role'])): ?>
                                            <span class="activity-role"><?= htmlspecialchars($activity['actor_role']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-ticket-box">
                        <h4>No hay actividad registrada</h4>
                        <p>Aún no se registraron eventos en la bitácora del ticket.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (($ticket['status'] ?? '') !== 'CERRADO'): ?>
            <section class="card ticket-reply-card">
                <div class="ticket-section-title">
                    <h3>Responder ticket</h3>
                    <p>Escribe un mensaje para dar seguimiento a esta incidencia.</p>
                </div>

                <form action="/helpdesk-php/reply-ticket.php" method="POST" class="ticket-form">
                    <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                    <div class="form-group">
                        <label for="message">Mensaje</label>
                        <textarea
                            id="message"
                            name="message"
                            rows="6"
                            placeholder="Escribe tu respuesta aquí..."
                            required
                        ></textarea>
                    </div>

                    <div class="ticket-form-actions">
                        <a href="/helpdesk-php/home.php" class="btn-secondary">Atrás</a>
                        <button type="submit" class="btn-primary">Enviar respuesta</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if (
            (user()['role'] ?? '') === 'CLIENT' &&
            (int)($ticket['requester_id'] ?? 0) === (int)(user()['id'] ?? 0) &&
            ($ticket['status'] ?? '') === 'CERRADO'
        ): ?>
            <section class="card ticket-reply-card">
                <div class="ticket-section-title">
                    <h3>Evaluación de la atención</h3>
                    <p>Tu opinión nos ayuda a mejorar el servicio brindado.</p>
                </div>

                <?php if (empty($feedback)): ?>
                    <form action="/helpdesk-php/save-feedback.php" method="POST" class="ticket-form">
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="rating">Calificación</label>
                                <select id="rating" name="rating" required>
                                    <option value="">Seleccione</option>
                                    <option value="1">1 - Muy mala</option>
                                    <option value="2">2 - Mala</option>
                                    <option value="3">3 - Regular</option>
                                    <option value="4">4 - Buena</option>
                                    <option value="5">5 - Excelente</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="resolved">¿Se resolvió tu problema?</label>
                                <select id="resolved" name="resolved" required>
                                    <option value="">Seleccione</option>
                                    <option value="SI">Sí</option>
                                    <option value="NO">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="comment">Comentario</label>
                            <textarea
                                id="comment"
                                name="comment"
                                rows="5"
                                placeholder="Puedes dejar una opinión, sugerencia o comentario sobre la atención recibida..."
                            ></textarea>
                        </div>

                        <div class="ticket-form-actions">
                            <button type="submit" class="btn-primary">Enviar evaluación</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="feedback-summary">
                        <div class="feedback-item">
                            <span class="label">Calificación</span>
                            <strong><?= (int)$feedback['rating'] ?>/5</strong>
                        </div>

                        <div class="feedback-item">
                            <span class="label">¿Se resolvió?</span>
                            <strong><?= htmlspecialchars($feedback['resolved']) ?></strong>
                        </div>

                        <div class="feedback-item full">
                            <span class="label">Comentario</span>
                            <strong><?= !empty($feedback['comment']) ? nl2br(htmlspecialchars($feedback['comment'])) : 'Sin comentario.' ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ==========================================================
     MODAL ELIMINAR MENSAJE
     ========================================================== -->
<div class="modal-overlay" id="deleteModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h3>Eliminar respuesta</h3>
            <button type="button" class="modal-close-btn" onclick="closeDeleteModal()">×</button>
        </div>

        <div class="custom-modal-body">
            <p>¿Está seguro de que desea eliminar esta respuesta?</p>

            <div class="custom-modal-warning">
                <label class="modal-checkbox-label">
                    <input type="checkbox" id="confirmDeleteCheckbox" onchange="toggleDeleteButton()">
                    <span>Confirmo que deseo eliminar esta respuesta de forma permanente.</span>
                </label>
            </div>
        </div>

        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
            <a href="#" id="confirmDeleteBtn" class="btn-primary disabled-delete-btn">Aceptar</a>
        </div>
    </div>
</div>

<!-- ==========================================================
     MODAL CERRAR TICKET
     ========================================================== -->
<div class="modal-overlay" id="closeTicketModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h3>Cerrar ticket</h3>
            <button type="button" class="modal-close-btn" onclick="closeCloseTicketModal()">×</button>
        </div>

        <div class="custom-modal-body">
            <p>¿Está seguro de que desea cerrar este ticket?</p>

            <div class="custom-modal-warning">
                <label class="modal-checkbox-label">
                    <input type="checkbox" id="confirmCloseTicketCheckbox" onchange="toggleCloseTicketButton()">
                    <span>Confirmo que deseo cerrar este ticket y finalizar la atención.</span>
                </label>
            </div>
        </div>

        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" onclick="closeCloseTicketModal()">Cancelar</button>
            <a href="#" id="confirmCloseTicketBtn" class="btn-primary disabled-delete-btn">Aceptar</a>
        </div>
    </div>
</div>

<!-- ==========================================================
     MODAL TODOS LOS TICKETS DEL CLIENTE
     ========================================================== -->
<div class="modal-overlay" id="allClientTicketsModal">
    <div class="custom-modal custom-modal-lg">
        <div class="custom-modal-header">
            <h3>Todos los tickets del cliente</h3>
            <button type="button" class="modal-close-btn" onclick="closeAllClientTicketsModal()">×</button>
        </div>

        <div class="custom-modal-body">
            <?php if (!empty($clientTickets)): ?>
                <div class="ticket-client-tickets-list modal-ticket-list">
                    <?php foreach ($clientTickets as $clientTicket): ?>
                        <a
                            href="/helpdesk-php/ticket-detail.php?id=<?= (int)$clientTicket['id'] ?>"
                            class="ticket-client-ticket-item"
                        >
                            <div class="ticket-client-ticket-top">
                                <strong>#<?= (int)$clientTicket['id'] ?> - <?= htmlspecialchars($clientTicket['subject']) ?></strong>
                            </div>

                            <div class="ticket-client-ticket-meta">
                                <span><?= htmlspecialchars($clientTicket['status']) ?></span>
                                <span><?= htmlspecialchars($clientTicket['priority']) ?></span>
                                <span><?= date('d/m/Y', strtotime($clientTicket['created_at'])) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-ticket-box">
                    <h4>No tiene tickets registrados</h4>
                    <p>Este cliente todavía no tiene historial de tickets.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let deleteMessageId = null;
let closeTicketId = null;

/**
 * Mostrar/ocultar menú del usuario admin
 */
function toggleAdminUserMenu() {
    const dropdown = document.getElementById('adminUserDropdown');
    if (dropdown) dropdown.classList.toggle('show');
}

/**
 * Mostrar tabs del detalle
 */
function showTicketTab(tabId, button) {
    const panels = document.querySelectorAll('.ticket-tab-panel');
    const buttons = document.querySelectorAll('.ticket-tab-btn');

    panels.forEach(panel => panel.classList.remove('active'));
    buttons.forEach(btn => btn.classList.remove('active'));

    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');

    if (button) button.classList.add('active');
}

/**
 * Modal eliminar mensaje
 */
function openDeleteModal(messageId) {
    deleteMessageId = messageId;

    const modal = document.getElementById('deleteModal');
    const checkbox = document.getElementById('confirmDeleteCheckbox');
    const confirmBtn = document.getElementById('confirmDeleteBtn');

    if (!modal || !checkbox || !confirmBtn) return;

    checkbox.checked = false;
    confirmBtn.classList.add('disabled-delete-btn');
    confirmBtn.removeAttribute('href');
    modal.classList.add('show');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) modal.classList.remove('show');
    deleteMessageId = null;
}

function toggleDeleteButton() {
    const checkbox = document.getElementById('confirmDeleteCheckbox');
    const confirmBtn = document.getElementById('confirmDeleteBtn');

    if (!checkbox || !confirmBtn) return;

    if (checkbox.checked && deleteMessageId) {
        confirmBtn.classList.remove('disabled-delete-btn');
        confirmBtn.setAttribute('href', '/helpdesk-php/delete-message.php?id=' + deleteMessageId);
    } else {
        confirmBtn.classList.add('disabled-delete-btn');
        confirmBtn.removeAttribute('href');
    }
}

/**
 * Modal cerrar ticket
 */
function openCloseTicketModal(ticketId) {
    closeTicketId = ticketId;

    const modal = document.getElementById('closeTicketModal');
    const checkbox = document.getElementById('confirmCloseTicketCheckbox');
    const confirmBtn = document.getElementById('confirmCloseTicketBtn');

    if (!modal || !checkbox || !confirmBtn) return;

    checkbox.checked = false;
    confirmBtn.classList.add('disabled-delete-btn');
    confirmBtn.removeAttribute('href');
    modal.classList.add('show');
}

function closeCloseTicketModal() {
    const modal = document.getElementById('closeTicketModal');
    if (modal) modal.classList.remove('show');
    closeTicketId = null;
}

function toggleCloseTicketButton() {
    const checkbox = document.getElementById('confirmCloseTicketCheckbox');
    const confirmBtn = document.getElementById('confirmCloseTicketBtn');

    if (!checkbox || !confirmBtn) return;

    if (checkbox.checked && closeTicketId) {
        confirmBtn.classList.remove('disabled-delete-btn');
        confirmBtn.setAttribute('href', '/helpdesk-php/close-ticket.php?id=' + closeTicketId);
    } else {
        confirmBtn.classList.add('disabled-delete-btn');
        confirmBtn.removeAttribute('href');
    }
}

/**
 * Acordeón de tickets del cliente
 */
function toggleClientTickets() {
    const panel = document.getElementById('clientTicketsPanel');
    const arrow = document.getElementById('clientTicketsArrow');

    if (!panel || !arrow) return;

    panel.classList.toggle('show');
    arrow.classList.toggle('rotate');
}

/**
 * Modal con todos los tickets del cliente
 */
function openAllClientTicketsModal() {
    const modal = document.getElementById('allClientTicketsModal');
    if (modal) modal.classList.add('show');
}

function closeAllClientTicketsModal() {
    const modal = document.getElementById('allClientTicketsModal');
    if (modal) modal.classList.remove('show');
}

/**
 * Cerrar modales/menu al hacer click fuera
 */
document.addEventListener('click', function (e) {
    const deleteModal = document.getElementById('deleteModal');
    const closeModal = document.getElementById('closeTicketModal');
    const allTicketsModal = document.getElementById('allClientTicketsModal');

    if (deleteModal && deleteModal.classList.contains('show') && e.target === deleteModal) {
        closeDeleteModal();
    }

    if (closeModal && closeModal.classList.contains('show') && e.target === closeModal) {
        closeCloseTicketModal();
    }

    if (allTicketsModal && allTicketsModal.classList.contains('show') && e.target === allTicketsModal) {
        closeAllClientTicketsModal();
    }

    const menu = document.querySelector('.admin-user-menu');
    const dropdown = document.getElementById('adminUserDropdown');

    if (menu && dropdown && !menu.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
