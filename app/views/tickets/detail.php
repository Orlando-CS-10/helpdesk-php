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


$internalMessages = $internalMessages ?? [];
$canUseInternalConversation = $canUseInternalConversation ?? in_array((user()['role'] ?? ''), ['ADMIN', 'TECH'], true);

if (!function_exists('ticketUserInitials')) {
    function ticketUserInitials(?string $name): string
    {
        $name = trim((string)$name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name);
        $first = mb_substr($parts[0] ?? 'U', 0, 1, 'UTF-8');
        $second = count($parts) > 1 ? mb_substr($parts[1], 0, 1, 'UTF-8') : '';

        return mb_strtoupper($first . $second, 'UTF-8');
    }
}

if (!function_exists('ticketRoleLabel')) {
    function ticketRoleLabel(?string $role): string
    {
        return match ($role) {
            'ADMIN' => 'Administrador',
            'TECH' => 'Técnico',
            'CLIENT' => 'Cliente',
            default => (string)$role,
        };
    }
}

if (empty($ticket) || empty($ticket['id'])) {
    $_SESSION['ticket_error'] = 'No se encontró información del ticket.';
    header('Location: /helpdesk-php/home.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Cronómetro SLA del detalle
|--------------------------------------------------------------------------
| Calcula el avance del SLA con base en horas laborales. En tickets abiertos
| usa la fecha actual; en tickets cerrados usa la fecha de cierre/actualización.
*/
if (!function_exists('detailIsBusinessTimeNow')) {
    function detailIsBusinessTimeNow(): bool
    {
        try {
            $now = new DateTime();
        } catch (Exception $e) {
            return false;
        }

        $dayOfWeek = (int)$now->format('N'); // 1 lunes, 7 domingo

        if ($dayOfWeek > 6) {
            return false;
        }

        $currentMinutes = ((int)$now->format('H') * 60) + (int)$now->format('i');
        $startMinutes = 8 * 60;          // 08:00
        $endMinutes = (17 * 60) + 50;    // 17:50

        return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
    }
}

if (!function_exists('detailFormatClockFromHours')) {
    function detailFormatClockFromHours(float|int|string|null $hours): string
    {
        if ($hours === null || $hours === '' || !is_numeric($hours)) {
            return '00:00:00';
        }

        $totalSeconds = max(0, (int)round(((float)$hours) * 3600));
        $h = intdiv($totalSeconds, 3600);
        $m = intdiv($totalSeconds % 3600, 60);
        $s = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

if (!function_exists('detailBuildSlaTimerData')) {
    function detailBuildSlaTimerData(array $ticket): array
    {
        $slaHours = (float)($ticket['sla_hours'] ?? 0);
        $createdAt = $ticket['created_at'] ?? null;
        $status = $ticket['status'] ?? '';
        $isClosed = $status === 'CERRADO';
        $isPaused = !$isClosed && !detailIsBusinessTimeNow();
        $endAt = date('Y-m-d H:i:s');

        if ($isClosed) {
            $endAt = $ticket['closed_at'] ?? $ticket['updated_at'] ?? $endAt;
        }

        $elapsedHours = 0.0;

        if (!empty($createdAt) && $slaHours > 0 && function_exists('calculateBusinessHours')) {
            $elapsedHours = (float)calculateBusinessHours($createdAt, $endAt);
        }

        $remainingHours = max(0, $slaHours - $elapsedHours);
        $progressPercent = $slaHours > 0 ? min(100, max(0, ($elapsedHours / $slaHours) * 100)) : 0;

        $phaseClass = 'sla-phase-green';
        $phaseLabel = 'Dentro del tiempo';
        $tooltip = 'El SLA se está contabilizando dentro del horario laboral.';

        if ($progressPercent >= 100) {
            $phaseClass = 'sla-phase-red';
            $phaseLabel = 'SLA vencido';
            $tooltip = 'El tiempo objetivo del SLA fue consumido.';
        } elseif ($progressPercent >= 85) {
            $phaseClass = 'sla-phase-red';
            $phaseLabel = 'Próximo a vencer';
            $tooltip = 'El SLA está cerca de llegar a su límite.';
        } elseif ($progressPercent >= 50) {
            $phaseClass = 'sla-phase-yellow';
            $phaseLabel = 'En seguimiento';
            $tooltip = 'El ticket ya consumió más de la mitad del SLA.';
        }

        if ($isPaused) {
            $phaseClass = 'sla-phase-paused';
            $phaseLabel = 'Tiempo pausado';
            $tooltip = 'Tiempo pausado por tiempo no laboral';
        }

        if ($isClosed) {
            if (($ticket['sla_met'] ?? null) !== null && (int)$ticket['sla_met'] === 1) {
                $phaseClass = 'sla-phase-green';
                $phaseLabel = 'SLA cumplido';
                $tooltip = 'El ticket fue cerrado dentro del tiempo objetivo.';
            } elseif (($ticket['sla_met'] ?? null) !== null && (int)$ticket['sla_met'] === 0) {
                $phaseClass = 'sla-phase-red';
                $phaseLabel = 'SLA no cumplido';
                $tooltip = 'El ticket fue cerrado fuera del tiempo objetivo.';
            } else {
                $phaseClass = $progressPercent >= 100 ? 'sla-phase-red' : 'sla-phase-green';
                $phaseLabel = 'Ticket cerrado';
                $tooltip = 'El ticket ya fue finalizado.';
            }
        }

        if ($slaHours <= 0 || empty($createdAt)) {
            $phaseClass = 'sla-phase-paused';
            $phaseLabel = 'SLA no definido';
            $tooltip = 'Este ticket no tiene un SLA objetivo válido.';
        }

        return [
            'sla_hours' => $slaHours,
            'elapsed_hours' => $elapsedHours,
            'remaining_hours' => $remainingHours,
            'progress_percent' => $progressPercent,
            'phase_class' => $phaseClass,
            'phase_label' => $phaseLabel,
            'tooltip' => $tooltip,
            'is_paused' => $isPaused,
            'is_closed' => $isClosed,
        ];
    }
}

$slaTimer = detailBuildSlaTimerData($ticket);

$title = 'Detalle del Ticket';

$currentUserForView = user();
$currentRoleForView = $currentUserForView['role'] ?? '';
$canExportTicketPdf = in_array($currentRoleForView, ['ADMIN', 'TECH'], true);
$isAdminView = $currentRoleForView === 'ADMIN';

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

<style>
/* Cronómetro SLA compacto del detalle */
.sla-timer-card {
    margin-bottom: 16px;
    padding: 16px;
    border: 1px solid #e7edf4;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 10px 26px rgba(15, 61, 46, 0.05);
}

.sla-timer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}

.sla-timer-header h3 {
    margin: 0;
    color: #0f172a;
    font-size: 18px;
    line-height: 1.2;
}

.sla-timer-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.sla-timer-bar {
    width: 100%;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: #edf2f7;
}

.sla-timer-bar-fill {
    height: 100%;
    width: 0;
    border-radius: 999px;
    transition: width 0.35s ease, background 0.35s ease;
}

.sla-timer-percent {
    margin-top: 7px;
    text-align: right;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
}

.sla-timer-times {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 14px;
}

.sla-time-box {
    padding: 11px 12px;
    border: 1px solid #e7edf4;
    border-radius: 14px;
    background: #f8fafc;
}

.sla-time-box span {
    display: block;
    margin-bottom: 5px;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
}

.sla-time-box strong {
    display: block;
    color: #0f172a;
    font-size: 16px;
    letter-spacing: -0.2px;
}

.sla-timer-note {
    margin: 12px 0 0;
    color: #475569;
    font-size: 12px;
    line-height: 1.45;
}

.sla-phase-green {
    background: #dcfce7;
    color: #166534;
    border-color: #86efac;
}

.sla-phase-yellow {
    background: #fef3c7;
    color: #92400e;
    border-color: #fcd34d;
}

.sla-phase-red {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #fca5a5;
}

.sla-phase-paused {
    background: #e5e7eb;
    color: #4b5563;
    border-color: #d1d5db;
}

.sla-timer-bar-fill.sla-phase-green {
    background: linear-gradient(90deg, #22c55e, #16a34a);
}

.sla-timer-bar-fill.sla-phase-yellow {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.sla-timer-bar-fill.sla-phase-red {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

.sla-timer-bar-fill.sla-phase-paused {
    background: linear-gradient(90deg, #9ca3af, #6b7280);
}

@media (max-width: 900px) {
    .sla-timer-times {
        grid-template-columns: 1fr;
    }
}
</style>


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

                                    <?php if (!empty($canExportTicketPdf)): ?>
                                        <a
                                            href="/helpdesk-php/export-ticket-pdf.php?id=<?= (int)$ticket['id'] ?>"
                                            class="ticket-detail-export-btn"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <i class="fa-solid fa-file-pdf"></i>
                                            Exportar PDF
                                        </a>
                                    <?php endif; ?>
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

                                <?php if ($canUseInternalConversation): ?>
                                    <button class="ticket-tab-btn internal-tab-btn" type="button" onclick="showTicketTab('internalConversationTab', this)">
                                        Conversación interna (<?= count($internalMessages ?? []) ?>)
                                    </button>
                                <?php endif; ?>
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
                                                    <div class="ticket-message-author">
                                                        <div class="ticket-message-avatar">
                                                            <?= htmlspecialchars(ticketUserInitials($message['name'] ?? 'Usuario')) ?>
                                                        </div>
                                                        <div class="ticket-message-author-info">
                                                            <strong><?= htmlspecialchars($message['name']) ?></strong>
                                                            <span class="message-role"><?= htmlspecialchars(ticketRoleLabel($message['role'] ?? '')) ?></span>
                                                        </div>
                                                    </div>

                                                    <div class="message-right">
                                                        <span class="message-date">
                                                            <?= !empty($message['created_at']) ? date('d/m/Y H:i', strtotime($message['created_at'])) : '' ?>
                                                        </span>

                                                        <?php if (($ticket['status'] ?? '') !== 'CERRADO'): ?>
                                                            <div class="message-actions-inline">
                                                                <button
                                                                    type="button"
                                                                    class="message-edit-btn"
                                                                    title="Editar mensaje"
                                                                    data-message-id="<?= (int)$message['id'] ?>"
                                                                    data-message-text="<?= htmlspecialchars($message['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                    onclick="openEditMessageModalFromButton(this)"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                                        <path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l8.06-8.06.92.92L5.92 19.58zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.13 1.13 3.75 3.75 1.14-1.13z"/>
                                                                    </svg>
                                                                </button>

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

                            <?php if ($canUseInternalConversation): ?>
                                <!-- TAB CONVERSACIÓN INTERNA -->
                                <div class="ticket-tab-panel" id="internalConversationTab">
                                    <div class="ticket-section-title internal-section-title">
                                        <h3>Conversación interna</h3>
                                        <p>Espacio privado solo para administradores y técnicos. El cliente no puede ver estos mensajes.</p>
                                    </div>

                                    <div class="internal-chat-warning">
                                        <strong>Nota interna</strong>
                                        <span>Usa esta sección para coordinar diagnóstico, escalamiento o acciones técnicas sin mostrarlo al cliente.</span>
                                    </div>

                                    <?php if (!empty($_SESSION['internal_message_error'])): ?>
                                        <div class="alert error internal-alert">
                                            <?= htmlspecialchars($_SESSION['internal_message_error']) ?>
                                        </div>
                                        <?php unset($_SESSION['internal_message_error']); ?>
                                    <?php endif; ?>

                                    <?php if (!empty($_SESSION['internal_message_success'])): ?>
                                        <div class="alert success internal-alert">
                                            <?= htmlspecialchars($_SESSION['internal_message_success']) ?>
                                        </div>
                                        <?php unset($_SESSION['internal_message_success']); ?>
                                    <?php endif; ?>


                                    <?php if (!empty($internalMessages)): ?>
                                        <div class="ticket-messages-list internal-messages-list">
                                            <?php foreach ($internalMessages as $internalMessage): ?>
                                                <div class="ticket-message-item internal-message-item">
                                                    <div class="ticket-message-top">
                                                        <div class="ticket-message-author">
                                                            <div class="ticket-message-avatar internal-avatar">
                                                                <?= htmlspecialchars(ticketUserInitials($internalMessage['name'] ?? 'Usuario')) ?>
                                                            </div>
                                                            <div class="ticket-message-author-info">
                                                                <strong><?= htmlspecialchars($internalMessage['name'] ?? 'Usuario') ?></strong>
                                                                <span class="message-role internal-role"><?= htmlspecialchars(ticketRoleLabel($internalMessage['role'] ?? '')) ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="message-right">
                                                            <span class="message-date">
                                                                <?= !empty($internalMessage['created_at']) ? date('d/m/Y H:i', strtotime($internalMessage['created_at'])) : '' ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="ticket-message-body">
                                                        <?= nl2br(htmlspecialchars($internalMessage['message'])) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-ticket-box internal-empty-box">
                                            <h4>Aún no hay mensajes internos</h4>
                                            <p>Los administradores y técnicos pueden coordinar aquí sin que el cliente visualice la conversación.</p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (($ticket['status'] ?? '') !== 'CERRADO'): ?>
                                        <form action="/helpdesk-php/save-internal-message.php" method="POST" class="ticket-form internal-message-form">
                                            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                                            <div class="form-group">
                                                <label for="internal_message_admin">Mensaje interno</label>
                                                <textarea
                                                    id="internal_message_admin"
                                                    name="message"
                                                    rows="4"
                                                    placeholder="Escribe una nota interna para el equipo técnico..."
                                                    required
                                                ></textarea>
                                            </div>

                                            <div class="ticket-form-actions">
                                                <button type="submit" class="btn-primary">Enviar mensaje interno</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="internal-closed-note">El ticket está cerrado. La conversación interna queda en modo lectura.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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

                    <div
                        class="card sla-timer-card"
                        id="slaTimerCard"
                        data-sla-seconds="<?= (int)round(($slaTimer['sla_hours'] ?? 0) * 3600) ?>"
                        data-elapsed-seconds="<?= (int)round(($slaTimer['elapsed_hours'] ?? 0) * 3600) ?>"
                        data-is-closed="<?= !empty($slaTimer['is_closed']) ? '1' : '0' ?>"
                        title="<?= htmlspecialchars($slaTimer['tooltip'] ?? '') ?>"
                    >
                        <div class="sla-timer-header">
                            <h3>Cronómetro SLA</h3>
                            <span class="sla-timer-badge <?= htmlspecialchars($slaTimer['phase_class'] ?? 'sla-phase-paused') ?>" id="slaTimerBadge">
                                <?= htmlspecialchars($slaTimer['phase_label'] ?? 'Tiempo pausado') ?>
                            </span>
                        </div>

                        <div class="sla-timer-bar">
                            <div
                                class="sla-timer-bar-fill <?= htmlspecialchars($slaTimer['phase_class'] ?? 'sla-phase-paused') ?>"
                                id="slaTimerBarFill"
                                style="width: <?= number_format((float)($slaTimer['progress_percent'] ?? 0), 2, '.', '') ?>%;"
                            ></div>
                        </div>

                        <div class="sla-timer-percent" id="slaTimerPercent">
                            <?= number_format((float)($slaTimer['progress_percent'] ?? 0), 1) ?>%
                        </div>

                        <div class="sla-timer-times">
                            <div class="sla-time-box">
                                <span>Tiempo consumido</span>
                                <strong id="slaTimerElapsed"><?= htmlspecialchars(detailFormatClockFromHours($slaTimer['elapsed_hours'] ?? 0)) ?></strong>
                            </div>

                            <div class="sla-time-box">
                                <span>Tiempo restante</span>
                                <strong id="slaTimerRemaining"><?= htmlspecialchars(detailFormatClockFromHours($slaTimer['remaining_hours'] ?? 0)) ?></strong>
                            </div>
                        </div>

                        <p class="sla-timer-note" id="slaTimerNote">
                            <?= !empty($slaTimer['is_closed'])
                                ? 'El ticket está cerrado, por lo que el conteo del SLA ya finalizó.'
                                : (!empty($slaTimer['is_paused'])
                                    ? 'El conteo del SLA está pausado porque ahora no es horario laboral.'
                                    : 'El SLA se está contabilizando dentro del horario laboral.')
                            ?>
                        </p>
                    </div>

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

                    <?php if (!empty($canExportTicketPdf)): ?>
                        <a
                            href="/helpdesk-php/export-ticket-pdf.php?id=<?= (int)$ticket['id'] ?>"
                            class="ticket-detail-export-btn"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="fa-solid fa-file-pdf"></i>
                            Exportar PDF
                        </a>
                    <?php endif; ?>
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

                <?php if ($canUseInternalConversation): ?>
                    <button class="ticket-tab-btn internal-tab-btn" type="button" onclick="showTicketTab('internalConversationTab', this)">
                        Conversación interna (<?= count($internalMessages ?? []) ?>)
                    </button>
                <?php endif; ?>
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
                                    <div class="ticket-message-author">
                                        <div class="ticket-message-avatar">
                                            <?= htmlspecialchars(ticketUserInitials($message['name'] ?? 'Usuario')) ?>
                                        </div>
                                        <div class="ticket-message-author-info">
                                            <strong><?= htmlspecialchars($message['name']) ?></strong>
                                            <span class="message-role"><?= htmlspecialchars(ticketRoleLabel($message['role'] ?? '')) ?></span>
                                        </div>
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
                                                                <button
                                                                    type="button"
                                                                    class="message-edit-btn"
                                                                    title="Editar mensaje"
                                                                    data-message-id="<?= (int)$message['id'] ?>"
                                                                    data-message-text="<?= htmlspecialchars($message['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                    onclick="openEditMessageModalFromButton(this)"
                                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l8.06-8.06.92.92L5.92 19.58zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.13 1.13 3.75 3.75 1.14-1.13z"/>
                                                    </svg>
                                                                </button>

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

            <?php if ($canUseInternalConversation): ?>
                <div class="ticket-tab-panel" id="internalConversationTab">
                    <div class="ticket-section-title internal-section-title">
                        <h3>Conversación interna</h3>
                        <p>Espacio privado solo para administradores y técnicos. El cliente no puede ver estos mensajes.</p>
                    </div>

                    <div class="internal-chat-warning">
                        <strong>Nota interna</strong>
                        <span>Usa esta sección para coordinar diagnóstico, escalamiento o acciones técnicas sin mostrarlo al cliente.</span>
                    </div>

                    <?php if (!empty($internalMessages)): ?>
                        <div class="ticket-messages-list internal-messages-list">
                            <?php foreach ($internalMessages as $internalMessage): ?>
                                <div class="ticket-message-item internal-message-item">
                                    <div class="ticket-message-top">
                                        <div class="ticket-message-author">
                                            <div class="ticket-message-avatar internal-avatar">
                                                <?= htmlspecialchars(ticketUserInitials($internalMessage['name'] ?? 'Usuario')) ?>
                                            </div>
                                            <div class="ticket-message-author-info">
                                                <strong><?= htmlspecialchars($internalMessage['name'] ?? 'Usuario') ?></strong>
                                                <span class="message-role internal-role"><?= htmlspecialchars(ticketRoleLabel($internalMessage['role'] ?? '')) ?></span>
                                            </div>
                                        </div>

                                        <div class="message-right">
                                            <span class="message-date">
                                                <?= !empty($internalMessage['created_at']) ? date('d/m/Y H:i', strtotime($internalMessage['created_at'])) : '' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="ticket-message-body">
                                        <?= nl2br(htmlspecialchars($internalMessage['message'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-ticket-box internal-empty-box">
                            <h4>Aún no hay mensajes internos</h4>
                            <p>Los administradores y técnicos pueden coordinar aquí sin que el cliente visualice la conversación.</p>
                        </div>
                    <?php endif; ?>

                    <?php if (($ticket['status'] ?? '') !== 'CERRADO'): ?>
                        <form action="/helpdesk-php/save-internal-message.php" method="POST" class="ticket-form internal-message-form">
                            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                            <div class="form-group">
                                <label for="internal_message_tech">Mensaje interno</label>
                                <textarea
                                    id="internal_message_tech"
                                    name="message"
                                    rows="4"
                                    placeholder="Escribe una nota interna para el equipo técnico..."
                                    required
                                ></textarea>
                            </div>

                            <div class="ticket-form-actions">
                                <button type="submit" class="btn-primary">Enviar mensaje interno</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="internal-closed-note">El ticket está cerrado. La conversación interna queda en modo lectura.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
     MODAL EDITAR MENSAJE
     ========================================================== -->
<div class="modal-overlay" id="editMessageModal">
    <div class="custom-modal edit-message-compact-modal">
        <div class="custom-modal-header">
            <h3>Editar respuesta</h3>
            <button type="button" class="modal-close-btn" onclick="closeEditMessageModal()">×</button>
        </div>

        <form action="/helpdesk-php/edit-message.php" method="POST" id="editMessageForm">
            <div class="custom-modal-body">
                <input type="hidden" name="message_id" id="editMessageId" value="">

                <p class="edit-message-modal-help">
                    Actualiza el contenido del mensaje sin salir del detalle del ticket.
                </p>

                <label class="edit-message-modal-label" for="editMessageText">Mensaje</label>
                <textarea
                    name="message"
                    id="editMessageText"
                    class="edit-message-modal-textarea"
                    rows="5"
                    required
                ></textarea>
            </div>

            <div class="custom-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditMessageModal()">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

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

/**
 * Cronómetro SLA visual.
 * Incrementa solo si el ticket está abierto y el navegador está dentro del horario laboral.
 */
function formatSlaSeconds(totalSeconds) {
    totalSeconds = Math.max(0, Math.round(Number(totalSeconds) || 0));
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;

    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

function isBrowserBusinessTime() {
    const now = new Date();
    const day = now.getDay(); // 0 domingo, 6 sábado

    if (day === 0) {
        return false;
    }

    const minutes = now.getHours() * 60 + now.getMinutes();
    const start = 8 * 60;
    const end = 17 * 60 + 50;

    return minutes >= start && minutes <= end;
}

function updateSlaTimerVisual() {
    const card = document.getElementById('slaTimerCard');
    const badge = document.getElementById('slaTimerBadge');
    const bar = document.getElementById('slaTimerBarFill');
    const percentText = document.getElementById('slaTimerPercent');
    const elapsedText = document.getElementById('slaTimerElapsed');
    const remainingText = document.getElementById('slaTimerRemaining');
    const note = document.getElementById('slaTimerNote');

    if (!card || !badge || !bar || !percentText || !elapsedText || !remainingText || !note) {
        return;
    }

    const isClosed = card.dataset.isClosed === '1';
    const slaSeconds = Math.max(0, Number(card.dataset.slaSeconds || 0));
    let elapsedSeconds = Math.max(0, Number(card.dataset.elapsedSeconds || 0));

    if (!isClosed && isBrowserBusinessTime() && slaSeconds > 0) {
        elapsedSeconds += 1;
        card.dataset.elapsedSeconds = String(elapsedSeconds);
    }

    const remainingSeconds = Math.max(0, slaSeconds - elapsedSeconds);
    const percent = slaSeconds > 0 ? Math.min(100, Math.max(0, (elapsedSeconds / slaSeconds) * 100)) : 0;

    let phaseClass = 'sla-phase-green';
    let label = 'Dentro del tiempo';
    let message = 'El SLA se está contabilizando dentro del horario laboral.';
    let tooltip = message;

    if (!isClosed && !isBrowserBusinessTime()) {
        phaseClass = 'sla-phase-paused';
        label = 'Tiempo pausado';
        message = 'El conteo del SLA está pausado porque ahora no es horario laboral.';
        tooltip = 'Tiempo pausado por tiempo no laboral';
    } else if (percent >= 100) {
        phaseClass = 'sla-phase-red';
        label = 'SLA vencido';
        message = isClosed ? 'El ticket está cerrado, por lo que el conteo del SLA ya finalizó.' : 'El SLA llegó a su límite de tiempo.';
        tooltip = 'El tiempo objetivo del SLA fue consumido.';
    } else if (percent >= 85) {
        phaseClass = 'sla-phase-red';
        label = 'Próximo a vencer';
        message = isClosed ? 'El ticket está cerrado, por lo que el conteo del SLA ya finalizó.' : 'El ticket está cerca de consumir el SLA.';
        tooltip = 'El SLA está cerca de llegar a su límite.';
    } else if (percent >= 50) {
        phaseClass = 'sla-phase-yellow';
        label = 'En seguimiento';
        message = isClosed ? 'El ticket está cerrado, por lo que el conteo del SLA ya finalizó.' : 'El ticket ya consumió más de la mitad del SLA.';
        tooltip = 'El ticket ya consumió más de la mitad del SLA.';
    }

    if (isClosed) {
        message = 'El ticket está cerrado, por lo que el conteo del SLA ya finalizó.';
    }

    badge.className = 'sla-timer-badge ' + phaseClass;
    bar.className = 'sla-timer-bar-fill ' + phaseClass;
    badge.textContent = isClosed ? badge.textContent : label;
    bar.style.width = percent.toFixed(2) + '%';
    percentText.textContent = percent.toFixed(1) + '%';
    elapsedText.textContent = formatSlaSeconds(elapsedSeconds);
    remainingText.textContent = formatSlaSeconds(remainingSeconds);
    note.textContent = message;
    card.setAttribute('title', tooltip);
}

updateSlaTimerVisual();
setInterval(updateSlaTimerVisual, 1000);

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

document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);

    if (params.get('tab') === 'internal' || window.location.hash === '#internalConversationTab') {
        const internalButton = document.querySelector('.ticket-tab-btn[onclick*="internalConversationTab"]');
        showTicketTab('internalConversationTab', internalButton);
    }
});

/**
 * Modal editar mensaje
 */
function openEditMessageModalFromButton(button) {
    if (!button) return;

    const messageId = button.getAttribute('data-message-id') || '';
    const messageText = button.getAttribute('data-message-text') || '';

    openEditMessageModal(messageId, messageText);
}

function openEditMessageModal(messageId, messageText) {
    const modal = document.getElementById('editMessageModal');
    const input = document.getElementById('editMessageId');
    const textarea = document.getElementById('editMessageText');

    if (!modal || !input || !textarea) return;

    input.value = messageId;
    textarea.value = messageText;
    modal.classList.add('show');

    setTimeout(function () {
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
    }, 80);
}

function closeEditMessageModal() {
    const modal = document.getElementById('editMessageModal');
    const input = document.getElementById('editMessageId');
    const textarea = document.getElementById('editMessageText');

    if (modal) modal.classList.remove('show');
    if (input) input.value = '';
    if (textarea) textarea.value = '';
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
    const editModal = document.getElementById('editMessageModal');
    const deleteModal = document.getElementById('deleteModal');
    const closeModal = document.getElementById('closeTicketModal');
    const allTicketsModal = document.getElementById('allClientTicketsModal');

    if (editModal && editModal.classList.contains('show') && e.target === editModal) {
        closeEditMessageModal();
    }

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

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;

    closeEditMessageModal();
    closeDeleteModal();
    closeCloseTicketModal();
    closeAllClientTicketsModal();
});

</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
