<?php
if (!function_exists('user')) {
    require_once __DIR__ . '/../../helpers/session.php';
}

require_once __DIR__ . '/../../helpers/business_hours.php';
require_once __DIR__ . '/../../helpers/sla_helper.php';
require_once __DIR__ . '/../../helpers/ticket_message_helper.php';

$ticket = $ticket ?? null;
$messages = $messages ?? [];
$activities = $activities ?? [];
$clientTickets = $clientTickets ?? [];
$clientInfo = $clientInfo ?? [];
$clientStats = $clientStats ?? [];
$feedback = $feedback ?? null;
$messageAttachments = $messageAttachments ?? [];
$internalMessageAttachments = $internalMessageAttachments ?? [];
$closureReasons = $closureReasons ?? [];
$ticketClosure = $ticketClosure ?? null;
$canCloseTicket = $canCloseTicket ?? false;
$closureModuleReady = $closureModuleReady ?? false;

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


if (!function_exists('ticketProfilePhotoUrl')) {
    function ticketProfilePhotoUrl(?string $photoPath): ?string
    {
        $photoPath = trim((string)$photoPath);

        if ($photoPath === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $photoPath)) {
            return $photoPath;
        }

        if (str_starts_with($photoPath, '/')) {
            return $photoPath;
        }

        $photoPath = ltrim($photoPath, '/');

        if (str_starts_with($photoPath, 'public/')) {
            return '/helpdesk-php/' . $photoPath;
        }

        return '/helpdesk-php/public/uploads/users/' . $photoPath;
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


if (!function_exists('ticketMessageEditor')) {
    function ticketMessageEditor(
        string $editorId,
        string $label,
        string $placeholder,
        bool $allowDocuments = true,
        string $context = 'public'
    ): void {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $editorId) ?: 'ticketEditor';
        $context = in_array($context, ['public', 'internal', 'edit'], true)
            ? $context
            : 'public';

        $contextMeta = match ($context) {
            'internal' => [
                'title' => $label,
                'subtitle' => 'Solo será visible para administradores y técnicos.',
                'badge' => 'Nota interna',
                'icon' => 'fa-lock',
            ],
            'edit' => [
                'title' => $label,
                'subtitle' => 'Actualiza el contenido conservando la trazabilidad.',
                'badge' => 'Edición',
                'icon' => 'fa-pen-to-square',
            ],
            default => [
                'title' => $label,
                'subtitle' => 'El solicitante recibirá esta actualización en el historial del ticket.',
                'badge' => 'Respuesta pública',
                'icon' => 'fa-message',
            ],
        };
        ?>
        <div
            class="form-group ticket-rich-editor-field ticket-composer-context-<?= htmlspecialchars($context) ?>"
            data-rich-editor="<?= htmlspecialchars($safeId) ?>"
            data-editor-context="<?= htmlspecialchars($context) ?>"
        >
            <div class="ticket-composer-heading">
                <span class="ticket-composer-heading-icon" aria-hidden="true">
                    <i class="fa-solid <?= htmlspecialchars($contextMeta['icon']) ?>"></i>
                </span>

                <div class="ticket-composer-heading-copy">
                    <strong><?= htmlspecialchars($contextMeta['title']) ?></strong>
                    <small><?= htmlspecialchars($contextMeta['subtitle']) ?></small>
                </div>

                <span class="ticket-composer-channel">
                    <?= htmlspecialchars($contextMeta['badge']) ?>
                </span>
            </div>

            <div class="ticket-rich-editor">
                <div class="ticket-rich-toolbar" role="toolbar" aria-label="Formato del mensaje">
                    <select data-rich-block title="Tipo de texto" aria-label="Tipo de texto">
                        <option value="p">Párrafo</option>
                        <option value="h2">Título</option>
                        <option value="h3">Subtítulo</option>
                        <option value="blockquote">Cita</option>
                        <option value="pre">Código</option>
                    </select>

                    <select data-rich-size title="Tamaño de texto" aria-label="Tamaño de texto">
                        <option value="14px">14 px</option>
                        <option value="12px">12 px</option>
                        <option value="16px">16 px</option>
                        <option value="18px">18 px</option>
                        <option value="20px">20 px</option>
                        <option value="24px">24 px</option>
                        <option value="28px">28 px</option>
                        <option value="32px">32 px</option>
                    </select>

                    <span class="ticket-toolbar-divider"></span>

                    <button type="button" data-rich-command="bold" title="Negrita" aria-label="Negrita"><strong>B</strong></button>
                    <button type="button" data-rich-command="italic" title="Cursiva" aria-label="Cursiva"><em>I</em></button>
                    <button type="button" data-rich-command="underline" title="Subrayado" aria-label="Subrayado"><u>U</u></button>
                    <button type="button" data-rich-command="strikeThrough" title="Tachado" aria-label="Tachado"><s>S</s></button>

                    <label class="ticket-color-tool" title="Color de texto">
                        <span>A</span>
                        <input type="color" value="#172033" data-rich-color>
                    </label>

                    <label class="ticket-color-tool highlight" title="Color de resaltado">
                        <span>▰</span>
                        <input type="color" value="#fff2b2" data-rich-highlight>
                    </label>

                    <span class="ticket-toolbar-divider"></span>

                    <button type="button" data-rich-command="insertUnorderedList" title="Viñetas">• Lista</button>
                    <button type="button" data-rich-command="insertOrderedList" title="Numeración">1. Lista</button>
                    <button type="button" data-rich-command="justifyLeft" title="Alinear a la izquierda">←</button>
                    <button type="button" data-rich-command="justifyCenter" title="Centrar">↔</button>
                    <button type="button" data-rich-command="justifyRight" title="Alinear a la derecha">→</button>
                    <button type="button" data-rich-command="justifyFull" title="Justificar">☰</button>

                    <button type="button" data-rich-action="link" title="Insertar enlace" aria-label="Insertar enlace">
                        <i class="fa-solid fa-link"></i>
                    </button>

                    <?php if ($allowDocuments): ?>
                        <button type="button" data-rich-action="image" title="Insertar imagen dentro del mensaje" aria-label="Insertar imagen">
                            <i class="fa-regular fa-image"></i>
                        </button>
                    <?php endif; ?>

                    <span class="ticket-toolbar-divider"></span>

                    <button type="button" data-rich-command="undo" title="Deshacer" aria-label="Deshacer">↶</button>
                    <button type="button" data-rich-command="redo" title="Rehacer" aria-label="Rehacer">↷</button>
                    <button type="button" data-rich-command="removeFormat" title="Limpiar formato" aria-label="Limpiar formato">Tx</button>
                </div>

                <div
                    id="<?= htmlspecialchars($safeId) ?>Editor"
                    class="ticket-rich-editor-area"
                    contenteditable="true"
                    data-placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                    spellcheck="true"
                    oninput="this.closest('[data-rich-editor]').querySelector('[data-rich-input]').value = this.innerHTML;"
                    onblur="this.closest('[data-rich-editor]').querySelector('[data-rich-input]').value = this.innerHTML;"></div>

                <div class="ticket-composer-meta">
                    <span>
                        <i class="fa-solid fa-spell-check" aria-hidden="true"></i>
                        Revisión ortográfica activa
                    </span>
                    <span data-rich-counter>0 caracteres</span>
                </div>

                <input
                    type="hidden"
                    name="message"
                    id="<?= htmlspecialchars($safeId) ?>Input"
                    data-rich-input>

                <?php if ($allowDocuments): ?>
                    <input
                        type="file"
                        name="inline_images[]"
                        id="<?= htmlspecialchars($safeId) ?>Images"
                        data-rich-images
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        multiple
                        hidden>
                <?php endif; ?>
            </div>

            <?php if ($allowDocuments): ?>
                <div class="ticket-document-dropzone" data-document-dropzone>
                    <button type="button" class="ticket-document-button" data-document-trigger>
                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                        Seleccionar documentos
                    </button>

                    <div class="ticket-document-dropzone-copy">
                        <strong>Adjunta archivos o arrástralos aquí</strong>
                        <small>PDF, Word, Excel, PowerPoint, TXT, CSV o ZIP. Hasta 8 archivos de 15 MB.</small>
                    </div>

                    <span class="ticket-document-count" data-document-count>Sin archivos</span>

                    <input
                        type="file"
                        name="attachments[]"
                        id="<?= htmlspecialchars($safeId) ?>Documents"
                        data-rich-documents
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip"
                        multiple
                        hidden>
                </div>

                <div class="ticket-document-list" data-document-list></div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('ticketMessageAvatarMarkup')) {
    function ticketMessageAvatarMarkup(array $message, bool $internal = false): string
    {
        $name = (string)($message['name'] ?? 'Usuario');
        $photo = ticketProfilePhotoUrl($message['profile_photo'] ?? null);
        $classes = 'ticket-message-avatar' . ($internal ? ' internal-avatar' : '');

        if ($photo) {
            return '<div class="' . $classes . ' has-photo">'
                . '<img src="' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') . '" alt="Foto de '
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">'
                . '</div>';
        }

        return '<div class="' . $classes . '">'
            . htmlspecialchars(ticketUserInitials($name), ENT_QUOTES, 'UTF-8')
            . '</div>';
    }
}


if (!function_exists('ticketMessageWasEdited')) {
    function ticketMessageWasEdited(array $message): bool
    {
        if (empty($message['created_at']) || empty($message['updated_at'])) {
            return false;
        }

        $createdAt = strtotime((string)$message['created_at']);
        $updatedAt = strtotime((string)$message['updated_at']);

        if ($createdAt === false || $updatedAt === false) {
            return false;
        }

        return ($updatedAt - $createdAt) > 30;
    }
}

if (!function_exists('ticketRenderConversationMessages')) {
    function ticketRenderConversationMessages(array $messages, array $messageAttachments, array $ticket, array $currentUserForView): void
    {
        $currentUserId = (int)($currentUserForView['id'] ?? 0);
        $currentGroupKey = null;
        $currentGroupOpen = false;

        echo '<div class="ticket-messages-list" data-ticket-message-scroll>';

        foreach ($messages as $message) {
            $messageUserId = (int)($message['user_id'] ?? 0);
            $messageRoleSlug = strtolower((string)($message['role'] ?? 'user'));
            $isOwnMessage = $messageUserId > 0 && $messageUserId === $currentUserId;
            $groupKey = $messageUserId . '|' . ($isOwnMessage ? 'own' : 'other') . '|' . $messageRoleSlug;
            $startsNewGroup = $groupKey !== $currentGroupKey;

            if ($startsNewGroup) {
                if ($currentGroupOpen) {
                    echo '</div></div></div>';
                }

                $groupClass = $isOwnMessage ? 'ticket-message-own-group' : 'ticket-message-other-group';
                echo '<div class="ticket-message-group ' . $groupClass . ' message-role-' . htmlspecialchars($messageRoleSlug, ENT_QUOTES, 'UTF-8') . '">';

                echo ticketMessageAvatarMarkup($message);

                echo '<div class="ticket-message-group-inner">';
                echo '<div class="ticket-message-group-header">';
                echo '<strong>' . htmlspecialchars((string)($message['name'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8') . '</strong>';
                echo '<span class="message-role message-role-badge-' . htmlspecialchars($messageRoleSlug, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars(ticketRoleLabel($message['role'] ?? ''), ENT_QUOTES, 'UTF-8')
                    . '</span>';
                echo '</div>';
                echo '<div class="ticket-message-stack">';

                $currentGroupKey = $groupKey;
                $currentGroupOpen = true;
            }

            $messageId = (int)($message['id'] ?? 0);
            $createdAtLabel = !empty($message['created_at']) ? date('d/m/Y H:i', strtotime((string)$message['created_at'])) : '';
            $wasEdited = ticketMessageWasEdited($message);
            $messageFormat = (string)($message['message_format'] ?? 'plain');
            $messageContent = (string)($message['message'] ?? '');

            echo '<div class="ticket-message-item ticket-message-bubble message-role-' . htmlspecialchars($messageRoleSlug, ENT_QUOTES, 'UTF-8') . ' ' . ($isOwnMessage ? 'ticket-message-own' : 'ticket-message-other') . '">';
            echo '<div class="ticket-message-bubble-tools">';
            echo '<span class="message-date">' . htmlspecialchars($createdAtLabel, ENT_QUOTES, 'UTF-8');

            if ($wasEdited) {
                echo '<small class="message-edited">Editado</small>';
            }

            echo '</span>';

            if (($ticket['status'] ?? '') !== 'CERRADO') {
                echo '<div class="message-actions-inline">';
                echo '<button type="button" class="message-edit-btn" title="Editar mensaje"'
                    . ' data-message-id="' . $messageId . '"'
                    . ' data-message-content="' . htmlspecialchars(base64_encode($messageContent), ENT_QUOTES, 'UTF-8') . '"'
                    . ' data-message-format="' . htmlspecialchars($messageFormat, ENT_QUOTES, 'UTF-8') . '"'
                    . ' onclick="openEditMessageModalFromButton(this)">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">'
                    . '<path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l8.06-8.06.92.92L5.92 19.58zM20.71 7.04a1.003 1.003 0 0 0 0-1.42L18.37 3.29a1.003 1.003 0 0 0-1.42 0l-1.13 1.13 3.75 3.75 1.14-1.13z"/>'
                    . '</svg></button>';
                echo '<a href="#" class="message-delete-btn" title="Eliminar mensaje" onclick="openDeleteModal(' . $messageId . '); return false;">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">'
                    . '<path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>'
                    . '</svg></a>';
                echo '</div>';
            }

            echo '</div>';
            echo '<div class="ticket-message-body ticket-rich-message is-collapsed" data-collapsible-message>';
            echo ticketRenderStoredMessage($messageContent, $messageFormat);
            echo '</div>';
            echo '<button type="button" class="ticket-message-toggle" data-message-toggle hidden>Ver más</button>';
            echo ticketRenderAttachmentList($messageAttachments[$messageId] ?? []);
            echo '</div>';
        }

        if ($currentGroupOpen) {
            echo '</div></div></div>';
        }

        echo '</div>';
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
| Usa el contrato 24/7 o 8/5 de la empresa del ticket.
*/
$slaTimer = $slaTimer ?? getSlaTimerData($ticket);

$slaStatusDisplayLabel = (string)($slaTimer['status_label'] ?? 'Pendiente');
$slaStatusDisplayClass = match (true) {
    str_contains(mb_strtolower($slaStatusDisplayLabel, 'UTF-8'), 'fuera'),
    str_contains(mb_strtolower($slaStatusDisplayLabel, 'UTF-8'), 'vencido'),
    str_contains(mb_strtolower($slaStatusDisplayLabel, 'UTF-8'), 'no cumplido') => 'danger',
    str_contains(mb_strtolower($slaStatusDisplayLabel, 'UTF-8'), 'cumplido'),
    $slaStatusDisplayLabel === 'Dentro del SLA' => 'success',
    default => 'pending',
};

$title = 'Detalle del Ticket';

$currentUserForView = user();
$currentRoleForView = $currentUserForView['role'] ?? '';
$canExportTicketPdf = in_array($currentRoleForView, ['ADMIN', 'TECH'], true);
$isAdminView = $currentRoleForView === 'ADMIN';

$ticketFlashError = trim((string)($_SESSION['ticket_error'] ?? ''));
$ticketFlashSuccess = trim((string)($_SESSION['ticket_success'] ?? ''));
unset($_SESSION['ticket_error'], $_SESSION['ticket_success']);

$ticketStatusCssClass = strtolower((string)preg_replace(
    '/[^a-z0-9_-]+/i',
    '-',
    str_replace('_', '-', (string)($ticket['status'] ?? 'sin-estado'))
));
$ticketPriorityCssClass = strtolower((string)preg_replace(
    '/[^a-z0-9_-]+/i',
    '-',
    (string)($ticket['priority'] ?? 'sin-prioridad')
));

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

                        <?php if ($ticketFlashError !== ''): ?>
                            <div class="alert error ticket-detail-feedback">
                                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($ticketFlashError) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($ticketFlashSuccess !== ''): ?>
                            <div class="alert success ticket-detail-feedback">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($ticketFlashSuccess) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- INFORMACIÓN GENERAL DEL TICKET -->
                        <section class="card ticket-detail-card ticket-detail-hero-card">
                            <div class="ticket-detail-header">
                                <div>
                                    <div class="ticket-detail-code">Ticket #<?= (int)$ticket['id'] ?></div>
                                    <h2><?= htmlspecialchars($ticket['subject'] ?? 'Sin asunto') ?></h2>
                                    <p class="ticket-detail-description">
                                        <?= nl2br(htmlspecialchars($ticket['description'] ?? '')) ?>
                                    </p>
                                </div>

                                <div class="ticket-detail-badges" aria-label="Estado operativo del ticket">
                                    <span class="ticket-badge status-badge status-<?= htmlspecialchars($ticketStatusCssClass) ?>">
                                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                        <?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $ticket['status'])))) ?>
                                    </span>

                                    <span class="ticket-badge priority-badge priority-<?= htmlspecialchars($ticketPriorityCssClass) ?>">
                                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                        Prioridad <?= htmlspecialchars(ucfirst(strtolower((string)($ticket['priority'] ?? 'N/D')))) ?>
                                    </span>

                                    <span class="ticket-badge sla-status-badge sla-status-<?= htmlspecialchars($slaStatusDisplayClass) ?>">
                                        <i class="fa-solid fa-stopwatch" aria-hidden="true"></i>
                                        <?= htmlspecialchars($slaStatusDisplayLabel) ?>
                                    </span>

                                    <?php if (!empty($canExportTicketPdf)): ?>
                                        <button
                                            type="button"
                                            class="ticket-detail-export-btn"
                                            onclick="openTicketPdfModal()"
                                        >
                                            <i class="fa-solid fa-file-pdf"></i>
                                            Exportar PDF
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!empty($canCloseTicket)): ?>
                                        <button
                                            type="button"
                                            class="ticket-close-action-btn"
                                            onclick="openCloseTicketModal(<?= (int)$ticket['id'] ?>)"
                                        >
                                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                            Cerrar ticket
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- RESUMEN OPERATIVO COMPACTO -->
                            <?php
                            $ttaDetailHours = getTicketTtaHours($ticket);
                            $ttaDetailLabel = $ttaDetailHours === null
                                ? 'Pendiente'
                                : formatSlaDuration($ttaDetailHours);
                            $ttaDetailClass = $ttaDetailHours === null ? 'pending' : 'success';

                            $ttrDetailHours = getTicketTtrHours($ticket);
                            $ttrDetailLabel = $ttrDetailHours === null
                                ? 'Pendiente'
                                : formatSlaDuration($ttrDetailHours);
                            $ttrDetailClass = $ttrDetailHours === null ? 'pending' : 'success';

                            $assignedLevelLabel = !empty($ticket['assigned_level'])
                                ? 'Nivel ' . (int)$ticket['assigned_level']
                                : 'Sin nivel asignado';

                            $companyLabel = $ticket['company_business_name']
                                ?? $ticket['requester_company_legacy']
                                ?? 'Sin empresa';
                            ?>

                            <div class="ticket-summary-grid">
                                <div class="ticket-summary-item ticket-summary-highlight">
                                    <span class="ticket-summary-label">Asignación</span>
                                    <strong><?= !empty($ticket['assigned_name']) ? htmlspecialchars($ticket['assigned_name']) : 'Sin asignar' ?></strong>
                                    <small><?= htmlspecialchars($assignedLevelLabel) ?></small>
                                </div>

                                <div class="ticket-summary-item ticket-summary-highlight">
                                    <span class="ticket-summary-label">Empresa y contrato</span>
                                    <strong><?= htmlspecialchars($companyLabel) ?></strong>
                                    <small><?= htmlspecialchars($slaTimer['contract_label'] ?? 'Contrato 8/5') ?></small>
                                </div>

                                <div class="ticket-summary-item">
                                    <span class="ticket-summary-label">Categoría</span>
                                    <strong><?= htmlspecialchars($ticket['category'] ?? 'No definida') ?></strong>
                                    <small>Clasificación de la incidencia</small>
                                </div>

                                <div class="ticket-summary-item">
                                    <span class="ticket-summary-label">Creación</span>
                                    <strong><?= !empty($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : 'No disponible' ?></strong>
                                    <small>Registro inicial del ticket</small>
                                </div>

                                <div class="ticket-summary-item">
                                    <span class="ticket-summary-label">Primera atención</span>
                                    <strong class="<?= empty($ticket['first_response_at']) ? 'pending' : 'success' ?>">
                                        <?= !empty($ticket['first_response_at'])
                                            ? date('d/m/Y H:i', strtotime($ticket['first_response_at']))
                                            : 'Pendiente' ?>
                                    </strong>
                                    <small>Primera respuesta técnica</small>
                                </div>

                                <div class="ticket-summary-item">
                                    <span class="ticket-summary-label">Última actualización</span>
                                    <strong><?= !empty($ticket['updated_at']) ? date('d/m/Y H:i', strtotime($ticket['updated_at'])) : 'No disponible' ?></strong>
                                    <small><?= htmlspecialchars($lastActivity['actor_name'] ?? $lastActivity['actor_role'] ?? 'Sistema') ?></small>
                                </div>

                                <div class="ticket-summary-item ticket-summary-metric ticket-summary-tta">
                                    <span class="ticket-summary-label">TTA</span>
                                    <strong class="<?= $ttaDetailClass ?>"><?= htmlspecialchars($ttaDetailLabel) ?></strong>
                                    <small>Tiempo de primera atención</small>
                                </div>

                                <div class="ticket-summary-item ticket-summary-metric ticket-summary-ttr">
                                    <span class="ticket-summary-label">TTR</span>
                                    <strong class="<?= $ttrDetailClass ?>"><?= htmlspecialchars($ttrDetailLabel) ?></strong>
                                    <small>Tiempo total de resolución</small>
                                </div>
                            </div>

                            <details class="ticket-extra-details">
                                <summary>
                                    <span>
                                        <strong>Más información operativa</strong>
                                        <small>Cierre, SLA y trazabilidad adicional</small>
                                    </span>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </summary>

                                <div class="ticket-extra-grid">
                                    <div>
                                        <span>Última acción por</span>
                                        <strong><?= htmlspecialchars($lastActivity['actor_name'] ?? $lastActivity['actor_role'] ?? 'Sistema') ?></strong>
                                    </div>

                                    <div>
                                        <span>Fecha de cierre</span>
                                        <strong>
                                            <?= !empty($ticket['closed_at'])
                                                ? date('d/m/Y H:i', strtotime($ticket['closed_at']))
                                                : 'Ticket abierto' ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Cerrado por cliente</span>
                                        <strong><?= ((int)($ticket['client_closed'] ?? 0) === 1) ? 'Sí' : 'No' ?></strong>
                                    </div>

                                    <?php if (!empty($ticketClosure)): ?>
                                                            <div class="ticket-closure-summary">
                                                                <span>Motivo de cierre</span>
                                                                <strong><?= htmlspecialchars((string)($ticketClosure['reason_name'] ?? 'No registrado')) ?></strong>
                                                            </div>

                                                            <div class="ticket-closure-summary">
                                                                <span>Cerrado por</span>
                                                                <strong>
                                                                    <?= htmlspecialchars((string)($ticketClosure['closed_by_name'] ?? 'Sistema')) ?>
                                                                    · <?= htmlspecialchars(ticketRoleLabel($ticketClosure['closed_by_role'] ?? '')) ?>
                                                                </strong>
                                                            </div>

                                                            <?php if (trim((string)($ticketClosure['comment'] ?? '')) !== ''): ?>
                                                                <div class="ticket-closure-summary ticket-closure-comment">
                                                                    <span>Comentario de cierre</span>
                                                                    <strong><?= nl2br(htmlspecialchars((string)$ticketClosure['comment'])) ?></strong>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                    <div>
                                        <span>SLA objetivo</span>
                                        <strong><?= htmlspecialchars(formatSlaDuration($ticket['sla_hours'] ?? 0)) ?></strong>
                                    </div>

                                    <div>
                                        <span>Cumplimiento SLA</span>
                                        <strong class="<?= htmlspecialchars($slaStatusDisplayClass) ?>">
                                            <?= htmlspecialchars($slaStatusDisplayLabel) ?>
                                        </strong>
                                    </div>
                                </div>
                            </details>

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
                                    <i class="fa-regular fa-comments" aria-hidden="true"></i>
                                    <span>Conversación</span>
                                    <strong class="ticket-tab-count"><?= count($messages ?? []) ?></strong>
                                </button>

                                <button class="ticket-tab-btn" type="button" onclick="showTicketTab('activityTab', this)">
                                    <i class="fa-solid fa-timeline" aria-hidden="true"></i>
                                    <span>Actividad</span>
                                    <strong class="ticket-tab-count"><?= count($activities ?? []) ?></strong>
                                </button>

                                <?php if ($canUseInternalConversation): ?>
                                    <button class="ticket-tab-btn internal-tab-btn" type="button" onclick="showTicketTab('internalConversationTab', this)">
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                        <span>Conversación interna</span>
                                        <strong class="ticket-tab-count"><?= count($internalMessages ?? []) ?></strong>
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
                                    <?php ticketRenderConversationMessages($messages, $messageAttachments, $ticket, $currentUserForView); ?>
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
                                                            <?= ticketMessageAvatarMarkup($internalMessage, true) ?>
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

                                                    <div class="ticket-message-body ticket-rich-message">
                                                        <?= ticketRenderStoredMessage(
                                                            $internalMessage['message'] ?? '',
                                                            $internalMessage['message_format'] ?? 'plain'
                                                        ) ?>
                                                    </div>

                                                    <?= ticketRenderAttachmentList(
                                                        $internalMessageAttachments[(int)$internalMessage['id']] ?? []
                                                    ) ?>
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
                                        <form
                                            action="/helpdesk-php/save-internal-message.php"
                                            method="POST"
                                            enctype="multipart/form-data"
                                            class="ticket-form internal-message-form ticket-rich-message-form">
                                            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                                            <?php ticketMessageEditor(
                                                'adminInternalReply',
                                                'Mensaje interno',
                                                'Escribe una nota privada para el equipo técnico.',
                                                true,
                                                'internal'
                                            ); ?>

                                            <div class="ticket-form-actions">
                                                <button type="submit" class="btn-primary ticket-send-button" data-loading-label="Enviando nota...">
                                                    <i class="fa-solid fa-lock"></i>
                                                    <span>Enviar mensaje interno</span>
                                                </button>
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

                                <form
                                    action="/helpdesk-php/reply-ticket.php"
                                    method="POST"
                                    enctype="multipart/form-data"
                                    class="ticket-form ticket-rich-message-form">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                                    <?php ticketMessageEditor(
                                        'adminPublicReply',
                                        'Mensaje',
                                        'Escribe una respuesta clara. Puedes insertar imágenes dentro del contenido.'
                                    ); ?>

                                    <div class="ticket-form-actions">
                                        <a href="/helpdesk-php/admin-tickets.php" class="btn-secondary">Atrás</a>
                                        <button type="submit" class="btn-primary ticket-send-button" data-loading-label="Enviando respuesta...">
                                            <i class="fa-solid fa-paper-plane"></i>
                                            <span>Enviar respuesta</span>
                                        </button>
                                    </div>
                                </form>
                            </section>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- ============ SIDEBAR DERECHO CLIENTE ============ -->
                <aside class="ticket-client-sidebar">

                    <div
                        class="card sla-timer-card sla-timer-card-enhanced"
                        data-sla-timer="1"
                        data-timezone-offset="<?= (int)(new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->getOffset() ?>"
                        data-sla-seconds="<?= (int)round(($slaTimer['sla_hours'] ?? 0) * 3600) ?>"
                        data-elapsed-seconds="<?= (int)round(($slaTimer['elapsed_hours'] ?? 0) * 3600) ?>"
                        data-is-closed="<?= !empty($slaTimer['is_closed']) ? '1' : '0' ?>"
                        data-contract="<?= htmlspecialchars($slaTimer['contract_type'] ?? '8_5') ?>"
                        data-work-start-minutes="<?= systemSlaTimeToMinutes((string)($ticket['sla_work_start'] ?? '08:00:00')) ?>"
                        data-work-end-minutes="<?= systemSlaTimeToMinutes((string)($ticket['sla_work_end'] ?? '17:00:00')) ?>"
                        data-work-days="<?= htmlspecialchars((string)($ticket['sla_work_days'] ?? '1,2,3,4,5')) ?>"
                        data-warning-percent="<?= (int)($slaTimer['warning_percent'] ?? 75) ?>"
                        data-critical-percent="<?= (int)($slaTimer['critical_percent'] ?? 90) ?>"
                        data-status-paused="<?= !empty($ticket['sla_pause_started_at']) ? '1' : '0' ?>"
                        data-is-running="<?= !empty($slaTimer['is_running']) ? '1' : '0' ?>"
                        title="<?= htmlspecialchars($slaTimer['tooltip'] ?? '') ?>"
                    >
                        <div class="sla-timer-header">
                            <div>
                                <span class="sla-timer-eyebrow">Control de tiempo</span>
                                <h3>Cronómetro SLA</h3>
                            </div>

                            <span
                                class="sla-timer-badge <?= htmlspecialchars($slaTimer['phase_class'] ?? 'sla-phase-paused') ?>"
                                data-sla-badge>
                                <?= htmlspecialchars($slaTimer['phase_label'] ?? 'SLA no definido') ?>
                            </span>
                        </div>

                        <div class="sla-contract-row">
                            <span>
                                <i class="fa-regular fa-clock"></i>
                                <?= htmlspecialchars($slaTimer['contract_label'] ?? 'Contrato 8/5') ?>
                            </span>

                            <strong>
                                Objetivo:
                                <?= htmlspecialchars(formatSlaDuration($slaTimer['sla_hours'] ?? 0)) ?>
                            </strong>
                        </div>

                        <div class="sla-timer-progress">
                            <div class="sla-timer-bar">
                                <div
                                    class="sla-timer-bar-fill <?= htmlspecialchars($slaTimer['phase_class'] ?? 'sla-phase-paused') ?>"
                                    data-sla-bar
                                    style="width: <?= number_format((float)($slaTimer['progress_percent'] ?? 0), 2, '.', '') ?>%;"
                                ></div>
                            </div>

                            <div class="sla-timer-percent" data-sla-percent>
                                <?= number_format((float)($slaTimer['progress_raw_percent'] ?? 0), 1) ?>%
                            </div>
                        </div>

                        <div class="sla-timer-times sla-timer-times-compact">
                            <div class="sla-time-box">
                                <span>Consumido</span>
                                <strong data-sla-elapsed>
                                    <?= htmlspecialchars(formatDecimalHoursToClock($slaTimer['elapsed_hours'] ?? 0)) ?>
                                </strong>
                            </div>

                            <div class="sla-time-box">
                                <span data-sla-remaining-label>
                                    <?= ($slaTimer['remaining_signed_hours'] ?? 0) < 0
                                        ? 'Tiempo excedido'
                                        : 'Tiempo restante' ?>
                                </span>

                                <strong data-sla-remaining>
                                    <?= htmlspecialchars(
                                        formatDecimalHoursToClock(
                                            ($slaTimer['remaining_signed_hours'] ?? 0) < 0
                                                ? $slaTimer['overtime_hours']
                                                : $slaTimer['remaining_hours']
                                        )
                                    ) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="sla-timer-milestones">
                            <div>
                                <span>Inicio</span>
                                <strong>
                                    <?= !empty($slaTimer['started_at'])
                                        ? date('d/m/Y H:i', strtotime($slaTimer['started_at']))
                                        : 'No disponible' ?>
                                </strong>
                            </div>

                            <div>
                                <span>Vencimiento estimado</span>
                                <strong>
                                    <?= !empty($slaTimer['deadline'])
                                        ? date('d/m/Y H:i', strtotime($slaTimer['deadline']))
                                        : 'No disponible' ?>
                                </strong>
                            </div>
                        </div>

                        <p class="sla-timer-note <?= !empty($slaTimer['is_paused']) ? 'paused' : '' ?>" data-sla-note>
                            <?= htmlspecialchars($slaTimer['note'] ?? 'Sin información de SLA.') ?>
                        </p>
                    </div>

                    <div class="card ticket-client-card">
                        <div class="ticket-client-card-header">
                            <h3>Información del cliente</h3>
                            <p>Datos relacionados al solicitante del ticket.</p>
                        </div>

                        <?php if (!empty($clientInfo)): ?>
                            <div class="ticket-client-profile">
                                <div class="ticket-client-avatar <?= !empty(ticketProfilePhotoUrl($clientInfo['profile_photo'] ?? null)) ? 'has-photo' : '' ?>">
                                    <?php if ($clientPhotoUrl = ticketProfilePhotoUrl($clientInfo['profile_photo'] ?? null)): ?>
                                        <img
                                            src="<?= htmlspecialchars($clientPhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="Foto de <?= htmlspecialchars($clientInfo['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8') ?>">
                                    <?php else: ?>
                                        <?= htmlspecialchars(ticketUserInitials($clientInfo['name'] ?? 'Cliente')) ?>
                                    <?php endif; ?>
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
                                    <strong>
                                        <?= htmlspecialchars(
                                            $clientInfo['business_name']
                                            ?? $clientInfo['company']
                                            ?? 'No registrado'
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="ticket-client-info-item">
                                    <span>RUC</span>
                                    <strong><?= htmlspecialchars($clientInfo['ruc'] ?? 'No registrado') ?></strong>
                                </div>

                                <div class="ticket-client-info-item">
                                    <span>Contrato</span>
                                    <strong>
                                        <?= htmlspecialchars(
                                            getSlaContractLabel($clientInfo['sla_contract_type'] ?? '8_5')
                                        ) ?>
                                    </strong>
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

        <?php if ($ticketFlashError !== ''): ?>
            <div class="alert error ticket-detail-feedback">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <span><?= htmlspecialchars($ticketFlashError) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($ticketFlashSuccess !== ''): ?>
            <div class="alert success ticket-detail-feedback">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span><?= htmlspecialchars($ticketFlashSuccess) ?></span>
            </div>
        <?php endif; ?>

        <section class="card ticket-detail-card ticket-detail-hero-card">
            <div class="ticket-detail-header">
                <div>
                    <div class="ticket-detail-code">Ticket #<?= (int)$ticket['id'] ?></div>
                    <h2><?= htmlspecialchars($ticket['subject'] ?? 'Sin asunto') ?></h2>
                    <p class="ticket-detail-description">
                        <?= nl2br(htmlspecialchars($ticket['description'] ?? '')) ?>
                    </p>
                </div>

                <div class="ticket-detail-badges" aria-label="Estado operativo del ticket">
                    <span class="ticket-badge status-badge status-<?= htmlspecialchars($ticketStatusCssClass) ?>">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', (string)($ticket['status'] ?? 'N/D'))))) ?>
                    </span>

                    <span class="ticket-badge priority-badge priority-<?= htmlspecialchars($ticketPriorityCssClass) ?>">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        Prioridad <?= htmlspecialchars(ucfirst(strtolower((string)($ticket['priority'] ?? 'N/D')))) ?>
                    </span>

                    <span class="ticket-badge sla-status-badge sla-status-<?= htmlspecialchars($slaStatusDisplayClass) ?>">
                        <i class="fa-solid fa-stopwatch" aria-hidden="true"></i>
                        <?= htmlspecialchars($slaStatusDisplayLabel) ?>
                    </span>

                    <?php if (!empty($canExportTicketPdf)): ?>
                        <button
                            type="button"
                            class="ticket-detail-export-btn"
                            onclick="openTicketPdfModal()"
                        >
                            <i class="fa-solid fa-file-pdf"></i>
                            Exportar PDF
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($canCloseTicket)): ?>
                        <button
                            type="button"
                            class="ticket-close-action-btn"
                            onclick="openCloseTicketModal(<?= (int)$ticket['id'] ?>)"
                        >
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            Cerrar ticket
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $ttaDetailHours = getTicketTtaHours($ticket);
            $ttaDetailLabel = $ttaDetailHours === null
                ? 'Pendiente'
                : formatSlaDuration($ttaDetailHours);
            $ttaDetailClass = $ttaDetailHours === null ? 'pending' : 'success';

            $ttrDetailHours = getTicketTtrHours($ticket);
            $ttrDetailLabel = $ttrDetailHours === null
                ? 'Pendiente'
                : formatSlaDuration($ttrDetailHours);
            $ttrDetailClass = $ttrDetailHours === null ? 'pending' : 'success';

            $assignedLevelLabel = !empty($ticket['assigned_level'])
                ? 'Nivel ' . (int)$ticket['assigned_level']
                : 'Sin nivel asignado';

            $companyLabel = $ticket['company_business_name']
                ?? $ticket['requester_company_legacy']
                ?? 'Sin empresa';
            ?>

            <div class="ticket-summary-grid">
                <div class="ticket-summary-item">
                    <span class="ticket-summary-label">Cliente</span>
                    <strong><?= htmlspecialchars($ticket['requester_name'] ?? 'No disponible') ?></strong>
                    <small><?= htmlspecialchars($companyLabel) ?></small>
                </div>

                <div class="ticket-summary-item ticket-summary-highlight">
                    <span class="ticket-summary-label">Asignación</span>
                    <strong><?= !empty($ticket['assigned_name']) ? htmlspecialchars($ticket['assigned_name']) : 'Sin asignar' ?></strong>
                    <small><?= htmlspecialchars($assignedLevelLabel) ?></small>
                </div>

                <div class="ticket-summary-item">
                    <span class="ticket-summary-label">Categoría</span>
                    <strong><?= htmlspecialchars($ticket['category'] ?? 'No definida') ?></strong>
                    <small>Clasificación de la incidencia</small>
                </div>

                <div class="ticket-summary-item">
                    <span class="ticket-summary-label">Creación</span>
                    <strong><?= !empty($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : 'No disponible' ?></strong>
                    <small>Registro inicial del ticket</small>
                </div>

                <div class="ticket-summary-item">
                    <span class="ticket-summary-label">Primera atención</span>
                    <strong class="<?= empty($ticket['first_response_at']) ? 'pending' : 'success' ?>">
                        <?= !empty($ticket['first_response_at'])
                            ? date('d/m/Y H:i', strtotime($ticket['first_response_at']))
                            : 'Pendiente' ?>
                    </strong>
                    <small>Primera respuesta técnica</small>
                </div>

                <div class="ticket-summary-item">
                    <span class="ticket-summary-label">Última actualización</span>
                    <strong><?= !empty($ticket['updated_at']) ? date('d/m/Y H:i', strtotime($ticket['updated_at'])) : 'No disponible' ?></strong>
                    <small><?= htmlspecialchars($lastActivity['actor_name'] ?? $lastActivity['actor_role'] ?? 'Sistema') ?></small>
                </div>

                <div class="ticket-summary-item ticket-summary-metric ticket-summary-tta">
                    <span class="ticket-summary-label">TTA</span>
                    <strong class="<?= $ttaDetailClass ?>"><?= htmlspecialchars($ttaDetailLabel) ?></strong>
                    <small>Tiempo de primera atención</small>
                </div>

                <div class="ticket-summary-item ticket-summary-metric ticket-summary-ttr">
                    <span class="ticket-summary-label">TTR</span>
                    <strong class="<?= $ttrDetailClass ?>"><?= htmlspecialchars($ttrDetailLabel) ?></strong>
                    <small>Tiempo total de resolución</small>
                </div>
            </div>

            <details class="ticket-extra-details">
                <summary>
                    <span>
                        <strong>Más información operativa</strong>
                        <small>Contrato, cierre y cumplimiento del SLA</small>
                    </span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>

                <div class="ticket-extra-grid">
                    <div>
                        <span>Contrato SLA</span>
                        <strong><?= htmlspecialchars($slaTimer['contract_label'] ?? 'Contrato 8/5') ?></strong>
                    </div>

                    <div>
                        <span>SLA objetivo</span>
                        <strong><?= htmlspecialchars(formatSlaDuration($ticket['sla_hours'] ?? 0)) ?></strong>
                    </div>

                    <div>
                        <span>Cumplimiento SLA</span>
                        <strong class="<?= htmlspecialchars($slaStatusDisplayClass) ?>">
                            <?= htmlspecialchars($slaStatusDisplayLabel) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Fecha de cierre</span>
                        <strong>
                            <?= !empty($ticket['closed_at'])
                                ? date('d/m/Y H:i', strtotime($ticket['closed_at']))
                                : 'Ticket abierto' ?>
                        </strong>
                    </div>

                    <div>
                        <span>Cerrado por cliente</span>
                        <strong><?= ((int)($ticket['client_closed'] ?? 0) === 1) ? 'Sí' : 'No' ?></strong>
                    </div>

                    <?php if (!empty($ticketClosure)): ?>
                                            <div class="ticket-closure-summary">
                                                <span>Motivo de cierre</span>
                                                <strong><?= htmlspecialchars((string)($ticketClosure['reason_name'] ?? 'No registrado')) ?></strong>
                                            </div>

                                            <div class="ticket-closure-summary">
                                                <span>Cerrado por</span>
                                                <strong>
                                                    <?= htmlspecialchars((string)($ticketClosure['closed_by_name'] ?? 'Sistema')) ?>
                                                    · <?= htmlspecialchars(ticketRoleLabel($ticketClosure['closed_by_role'] ?? '')) ?>
                                                </strong>
                                            </div>

                                            <?php if (trim((string)($ticketClosure['comment'] ?? '')) !== ''): ?>
                                                <div class="ticket-closure-summary ticket-closure-comment">
                                                    <span>Comentario de cierre</span>
                                                    <strong><?= nl2br(htmlspecialchars((string)$ticketClosure['comment'])) ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                    <?php if (in_array($currentRoleForView, ['ADMIN', 'TECH'], true)): ?>
                        <div>
                            <span>Última acción por</span>
                            <strong><?= htmlspecialchars($lastActivity['actor_name'] ?? $lastActivity['actor_role'] ?? 'Sistema') ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </section>

        <?php if ($currentRoleForView === 'TECH'): ?>
            <section
                class="card sla-timer-card sla-timer-card-enhanced ticket-tech-sla-inline"
                data-sla-timer="1"
                        data-timezone-offset="<?= (int)(new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->getOffset() ?>"
                data-sla-seconds="<?= (int)round(($slaTimer['sla_hours'] ?? 0) * 3600) ?>"
                data-elapsed-seconds="<?= (int)round(($slaTimer['elapsed_hours'] ?? 0) * 3600) ?>"
                data-is-closed="<?= !empty($slaTimer['is_closed']) ? '1' : '0' ?>"
                data-contract="<?= htmlspecialchars($slaTimer['contract_type'] ?? '8_5') ?>"
                data-work-start-minutes="<?= systemSlaTimeToMinutes((string)($ticket['sla_work_start'] ?? '08:00:00')) ?>"
                data-work-end-minutes="<?= systemSlaTimeToMinutes((string)($ticket['sla_work_end'] ?? '17:00:00')) ?>"
                data-work-days="<?= htmlspecialchars((string)($ticket['sla_work_days'] ?? '1,2,3,4,5')) ?>"
                data-warning-percent="<?= (int)($slaTimer['warning_percent'] ?? 75) ?>"
                data-critical-percent="<?= (int)($slaTimer['critical_percent'] ?? 90) ?>"
                data-status-paused="<?= !empty($ticket['sla_pause_started_at']) ? '1' : '0' ?>"
                data-is-running="<?= !empty($slaTimer['is_running']) ? '1' : '0' ?>"
                title="<?= htmlspecialchars($slaTimer['tooltip'] ?? '') ?>">
                <div class="sla-timer-header">
                    <div>
                        <span class="sla-timer-eyebrow">Control de tiempo</span>
                        <h3>Cronómetro SLA</h3>
                    </div>

                    <span
                        class="sla-timer-badge <?= htmlspecialchars($slaTimer['phase_class'] ?? 'sla-phase-paused') ?>"
                        data-sla-badge>
                        <?= htmlspecialchars($slaTimer['phase_label'] ?? 'SLA no definido') ?>
                    </span>
                </div>

                <div class="sla-contract-row">
                    <span>
                        <i class="fa-regular fa-clock"></i>
                        <?= htmlspecialchars($slaTimer['contract_label'] ?? 'Contrato 8/5') ?>
                    </span>

                    <strong>
                        Objetivo:
                        <?= htmlspecialchars(formatSlaDuration($slaTimer['sla_hours'] ?? 0)) ?>
                    </strong>
                </div>

                <div class="sla-timer-progress">
                    <div class="sla-timer-bar">
                        <div
                            class="sla-timer-bar-fill <?= htmlspecialchars($slaTimer['phase_class'] ?? 'sla-phase-paused') ?>"
                            data-sla-bar
                            style="width: <?= number_format((float)($slaTimer['progress_percent'] ?? 0), 2, '.', '') ?>%;"></div>
                    </div>

                    <div class="sla-timer-percent" data-sla-percent>
                        <?= number_format((float)($slaTimer['progress_raw_percent'] ?? 0), 1) ?>%
                    </div>
                </div>

                <div class="sla-timer-times sla-timer-times-compact">
                    <div class="sla-time-box">
                        <span>Consumido</span>
                        <strong data-sla-elapsed>
                            <?= htmlspecialchars(formatDecimalHoursToClock($slaTimer['elapsed_hours'] ?? 0)) ?>
                        </strong>
                    </div>

                    <div class="sla-time-box">
                        <span data-sla-remaining-label>
                            <?= ($slaTimer['remaining_signed_hours'] ?? 0) < 0
                                ? 'Tiempo excedido'
                                : 'Tiempo restante' ?>
                        </span>

                        <strong data-sla-remaining>
                            <?= htmlspecialchars(
                                formatDecimalHoursToClock(
                                    ($slaTimer['remaining_signed_hours'] ?? 0) < 0
                                        ? $slaTimer['overtime_hours']
                                        : $slaTimer['remaining_hours']
                                )
                            ) ?>
                        </strong>
                    </div>
                </div>

                <div class="sla-timer-milestones">
                    <div>
                        <span>Inicio</span>
                        <strong>
                            <?= !empty($slaTimer['started_at'])
                                ? date('d/m/Y H:i', strtotime($slaTimer['started_at']))
                                : 'No disponible' ?>
                        </strong>
                    </div>

                    <div>
                        <span>Vencimiento estimado</span>
                        <strong>
                            <?= !empty($slaTimer['deadline'])
                                ? date('d/m/Y H:i', strtotime($slaTimer['deadline']))
                                : 'No disponible' ?>
                        </strong>
                    </div>
                </div>

                <p class="sla-timer-note <?= !empty($slaTimer['is_paused']) ? 'paused' : '' ?>" data-sla-note>
                    <?= htmlspecialchars($slaTimer['note'] ?? 'Sin información de SLA.') ?>
                </p>
            </section>
        <?php endif; ?>

        <!-- Tabs también para cliente/tech -->
        <section class="card ticket-tabs-card">
            <div class="ticket-tabs-header">
                <button class="ticket-tab-btn active" type="button" onclick="showTicketTab('conversationTab', this)">
                    <i class="fa-regular fa-comments" aria-hidden="true"></i>
                    <span>Conversación</span>
                    <strong class="ticket-tab-count"><?= count($messages ?? []) ?></strong>
                </button>

                <button class="ticket-tab-btn" type="button" onclick="showTicketTab('activityTab', this)">
                    <i class="fa-solid fa-timeline" aria-hidden="true"></i>
                    <span>Actividad</span>
                    <strong class="ticket-tab-count"><?= count($activities ?? []) ?></strong>
                </button>

                <?php if ($canUseInternalConversation): ?>
                    <button class="ticket-tab-btn internal-tab-btn" type="button" onclick="showTicketTab('internalConversationTab', this)">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span>Conversación interna</span>
                        <strong class="ticket-tab-count"><?= count($internalMessages ?? []) ?></strong>
                    </button>
                <?php endif; ?>
            </div>

            <div class="ticket-tab-panel active" id="conversationTab">
                <div class="ticket-section-title">
                    <h3>Conversación del ticket</h3>
                    <p>Historial de respuestas y seguimiento.</p>
                </div>

                <?php if (!empty($messages)): ?>
                                    <?php ticketRenderConversationMessages($messages, $messageAttachments, $ticket, $currentUserForView); ?>
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
                                            <?= ticketMessageAvatarMarkup($internalMessage, true) ?>
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

                                    <div class="ticket-message-body ticket-rich-message">
                                        <?= ticketRenderStoredMessage(
                                            $internalMessage['message'] ?? '',
                                            $internalMessage['message_format'] ?? 'plain'
                                        ) ?>
                                    </div>

                                    <?= ticketRenderAttachmentList(
                                        $internalMessageAttachments[(int)$internalMessage['id']] ?? []
                                    ) ?>
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
                        <form
                                            action="/helpdesk-php/save-internal-message.php"
                                            method="POST"
                                            enctype="multipart/form-data"
                                            class="ticket-form internal-message-form ticket-rich-message-form">
                                            <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                                            <?php ticketMessageEditor(
                                                'techInternalReply',
                                                'Mensaje interno',
                                                'Escribe una nota privada para el equipo técnico.',
                                                true,
                                                'internal'
                                            ); ?>

                                            <div class="ticket-form-actions">
                                                <button type="submit" class="btn-primary ticket-send-button" data-loading-label="Enviando nota...">
                                                    <i class="fa-solid fa-lock"></i>
                                                    <span>Enviar mensaje interno</span>
                                                </button>
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

                <form
                                    action="/helpdesk-php/reply-ticket.php"
                                    method="POST"
                                    enctype="multipart/form-data"
                                    class="ticket-form ticket-rich-message-form">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">

                                    <?php ticketMessageEditor(
                                        'publicReply',
                                        'Mensaje',
                                        'Escribe una respuesta clara. Puedes insertar imágenes dentro del contenido.'
                                    ); ?>

                                    <div class="ticket-form-actions">
                                        <a href="/helpdesk-php/home.php" class="btn-secondary">Atrás</a>
                                        <button type="submit" class="btn-primary ticket-send-button" data-loading-label="Enviando respuesta...">
                                            <i class="fa-solid fa-paper-plane"></i>
                                            <span>Enviar respuesta</span>
                                        </button>
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

                <?php ticketMessageEditor(
                    'editMessageRich',
                    'Mensaje',
                    'Actualiza el contenido del mensaje.',
                    false,
                    'edit'
                ); ?>
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
<div class="modal-overlay" id="closeTicketModal" aria-hidden="true">
    <div class="custom-modal ticket-close-modal" role="dialog" aria-modal="true" aria-labelledby="closeTicketModalTitle">
        <form action="/helpdesk-php/close-ticket.php" method="POST" id="closeTicketForm">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars((string)($closeTicketCsrfToken ?? systemSlaCsrfToken())) ?>"
            >
            <input type="hidden" name="ticket_id" id="closeTicketFormId" value="">
            <input type="hidden" name="confirm_close" id="confirmCloseTicketValue" value="0">

            <div class="custom-modal-header ticket-close-modal-header">
                <div>
                    <span class="ticket-close-modal-eyebrow">Finalización de la atención</span>
                    <h3 id="closeTicketModalTitle">Cerrar ticket #<?= (int)$ticket['id'] ?></h3>
                    <p>Selecciona la causa que describe el resultado final de la incidencia.</p>
                </div>

                <button
                    type="button"
                    class="modal-close-btn"
                    onclick="closeCloseTicketModal()"
                    aria-label="Cerrar modal"
                >×</button>
            </div>

            <div class="custom-modal-body ticket-close-modal-body">
                <div class="ticket-close-summary">
                    <span class="ticket-close-summary-icon" aria-hidden="true">
                        <i class="fa-solid fa-ticket"></i>
                    </span>
                    <div>
                        <small>Ticket que será cerrado</small>
                        <strong>#<?= (int)$ticket['id'] ?> · <?= htmlspecialchars((string)($ticket['subject'] ?? 'Sin asunto')) ?></strong>
                        <p>El cronómetro SLA se detendrá y el cierre quedará registrado en la trazabilidad.</p>
                    </div>
                </div>

                <?php if (empty($closureReasons)): ?>
                    <div class="ticket-close-module-warning">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span>No hay motivos activos. Configúralos en Herramientas → Motivos de cierre.</span>
                    </div>
                <?php else: ?>
                    <div class="ticket-close-field">
                        <label for="closureReasonId">
                            Motivo de cierre
                            <span>*</span>
                        </label>
                        <select
                            name="closure_reason_id"
                            id="closureReasonId"
                            required
                            onchange="updateCloseReasonDetails()"
                        >
                            <option value="">Selecciona un motivo</option>
                            <?php foreach ($closureReasons as $reason): ?>
                                <option
                                    value="<?= (int)$reason['id'] ?>"
                                    data-description="<?= htmlspecialchars((string)($reason['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-requires-comment="<?= (int)($reason['requires_comment'] ?? 0) === 1 ? '1' : '0' ?>"
                                >
                                    <?= htmlspecialchars((string)($reason['name'] ?? 'Sin nombre')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ticket-close-reason-preview" id="closeReasonPreview" hidden>
                        <span class="ticket-close-reason-preview-icon" aria-hidden="true">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                        <div>
                            <strong id="closeReasonPreviewTitle">Motivo seleccionado</strong>
                            <p id="closeReasonPreviewDescription"></p>
                        </div>
                    </div>

                    <div class="ticket-close-field">
                        <div class="ticket-close-label-row">
                            <label for="closureComment">
                                Comentario de cierre
                                <span id="closureCommentRequiredMark" hidden>*</span>
                            </label>
                            <small id="closureCommentRequirement">Opcional</small>
                        </div>

                        <textarea
                            name="closure_comment"
                            id="closureComment"
                            rows="5"
                            maxlength="2000"
                            placeholder="Resume la solución aplicada, validación realizada o detalle necesario para el cierre."
                            oninput="validateCloseTicketForm()"
                        ></textarea>

                        <div class="ticket-close-comment-meta">
                            <span>Este comentario formará parte de la trazabilidad.</span>
                            <span id="closureCommentCounter">0 / 2000</span>
                        </div>
                    </div>

                    <label class="ticket-close-confirmation">
                        <input
                            type="checkbox"
                            id="confirmCloseTicketCheckbox"
                            onchange="toggleCloseTicketButton()"
                        >
                        <span class="ticket-close-confirmation-box" aria-hidden="true">
                            <i class="fa-solid fa-check"></i>
                        </span>
                        <span>
                            <strong>Confirmo el cierre definitivo</strong>
                            <small>El ticket quedará en estado CERRADO y ya no admitirá nuevas respuestas.</small>
                        </span>
                    </label>
                <?php endif; ?>
            </div>

            <div class="custom-modal-footer ticket-close-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeCloseTicketModal()">
                    Cancelar
                </button>

                <button
                    type="submit"
                    id="confirmCloseTicketBtn"
                    class="ticket-close-confirm-btn"
                    disabled
                >
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>Cerrar ticket</span>
                </button>
            </div>
        </form>
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

<?php if (!empty($canExportTicketPdf)): ?>
    <?php
    $ticketPdfPublicCount = count($messages);
    $ticketPdfActivityCount = count($activities);
    $ticketPdfInternalCount = count($internalMessages);
    $ticketPdfImageCount = 0;
    $ticketPdfDocumentCount = 0;

    foreach ([$messageAttachments, $internalMessageAttachments] as $attachmentMap) {
        foreach ($attachmentMap as $attachments) {
            foreach ($attachments as $attachment) {
                if ((int)($attachment['is_inline'] ?? 0) === 1) {
                    $ticketPdfImageCount++;
                } else {
                    $ticketPdfDocumentCount++;
                }
            }
        }
    }
    ?>
    <!-- ==========================================================
         MODAL CONFIGURAR EXPORTACIÓN PDF
         ========================================================== -->
    <div class="modal-overlay" id="ticketPdfModal" aria-hidden="true">
        <div class="custom-modal ticket-pdf-export-modal" role="dialog" aria-modal="true" aria-labelledby="ticketPdfModalTitle">
            <div class="custom-modal-header ticket-pdf-modal-header">
                <div>
                    <span class="ticket-pdf-step">Paso 1</span>
                    <h3 id="ticketPdfModalTitle">Exportar reporte del ticket #<?= (int)$ticket['id'] ?></h3>
                    <p>Elige un reporte compacto para presentación o uno completo para auditoría.</p>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeTicketPdfModal()" aria-label="Cerrar">×</button>
            </div>

            <form
                action="/helpdesk-php/export-ticket-pdf.php"
                method="GET"
                target="_blank"
                id="ticketPdfExportForm"
                onsubmit="closeTicketPdfModal()"
            >
                <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">

                <div class="custom-modal-body ticket-pdf-modal-body">
                    <div class="ticket-pdf-section-heading">
                        <strong>Tipo de reporte</strong>
                        <span>Selecciona el nivel de detalle que necesitas.</span>
                    </div>

                    <div class="ticket-pdf-type-grid" role="radiogroup" aria-label="Tipo de reporte">
                        <label class="ticket-pdf-type-card is-selected" data-pdf-type-card="executive">
                            <input type="radio" name="type" value="executive" checked>
                            <span class="ticket-pdf-type-icon"><i class="fa-solid fa-bolt"></i></span>
                            <span class="ticket-pdf-type-copy">
                                <strong>Reporte ejecutivo</strong>
                                <small>Resumen compacto, rápido y fácil de presentar.</small>
                                <em>2 a 5 páginas</em>
                            </span>
                            <span class="ticket-pdf-radio-mark"></span>
                        </label>

                        <label class="ticket-pdf-type-card" data-pdf-type-card="full">
                            <input type="radio" name="type" value="full">
                            <span class="ticket-pdf-type-icon"><i class="fa-solid fa-list-check"></i></span>
                            <span class="ticket-pdf-type-copy">
                                <strong>Reporte completo</strong>
                                <small>Trazabilidad íntegra para auditoría o revisión técnica.</small>
                                <em>Extensión variable</em>
                            </span>
                            <span class="ticket-pdf-radio-mark"></span>
                        </label>
                    </div>

                    <div class="ticket-pdf-executive-options" id="ticketPdfExecutiveOptions">
                        <div class="ticket-pdf-section-heading compact">
                            <strong>Contenido del reporte ejecutivo</strong>
                            <span>Los límites mantienen el archivo ordenado y ligero.</span>
                        </div>

                        <div class="ticket-pdf-option-list">
                            <label class="ticket-pdf-option">
                                <input type="hidden" name="include_public" value="0">
                                <input type="checkbox" name="include_public" value="1" checked>
                                <span class="ticket-pdf-option-check"><i class="fa-solid fa-check"></i></span>
                                <span><strong>Incluir conversación pública</strong><small>Últimos <?= min(8, $ticketPdfPublicCount) ?> de <?= $ticketPdfPublicCount ?> mensajes</small></span>
                            </label>

                            <label class="ticket-pdf-option">
                                <input type="hidden" name="include_activity" value="0">
                                <input type="checkbox" name="include_activity" value="1" checked>
                                <span class="ticket-pdf-option-check"><i class="fa-solid fa-check"></i></span>
                                <span><strong>Incluir actividad</strong><small>Últimos <?= min(15, $ticketPdfActivityCount) ?> de <?= $ticketPdfActivityCount ?> eventos; los críticos no se agrupan</small></span>
                            </label>

                            <label class="ticket-pdf-option">
                                <input type="hidden" name="include_internal" value="0">
                                <input type="checkbox" name="include_internal" value="1" checked>
                                <span class="ticket-pdf-option-check"><i class="fa-solid fa-check"></i></span>
                                <span><strong>Incluir conversación interna</strong><small>Últimos <?= min(5, $ticketPdfInternalCount) ?> de <?= $ticketPdfInternalCount ?> mensajes</small></span>
                            </label>

                            <label class="ticket-pdf-option">
                                <input type="hidden" name="include_images" value="0">
                                <input type="checkbox" name="include_images" value="1" checked>
                                <span class="ticket-pdf-option-check"><i class="fa-solid fa-check"></i></span>
                                <span><strong>Incluir miniaturas de imágenes</strong><small>Máximo 2 por mensaje · <?= $ticketPdfImageCount ?> disponibles</small></span>
                            </label>

                            <label class="ticket-pdf-option">
                                <input type="hidden" name="include_documents" value="0">
                                <input type="checkbox" name="include_documents" value="1" checked>
                                <span class="ticket-pdf-option-check"><i class="fa-solid fa-check"></i></span>
                                <span><strong>Incluir lista de documentos</strong><small>Solo nombre, tipo y tamaño · <?= $ticketPdfDocumentCount ?> disponibles</small></span>
                            </label>
                        </div>
                    </div>

                    <div class="ticket-pdf-full-note" id="ticketPdfFullNote" hidden>
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>El reporte completo incluirá toda la trazabilidad.</strong>
                            <span>Se exportarán todos los mensajes, actividades, notas internas, imágenes y documentos registrados.</span>
                        </div>
                    </div>

                    <div class="ticket-pdf-size-control">
                        <span class="ticket-pdf-size-icon"><i class="fa-solid fa-compress"></i></span>
                        <div>
                            <strong>Control automático del tamaño</strong>
                            <p>Los textos extensos se resumirán en el reporte ejecutivo. Las imágenes se mostrarán como miniaturas optimizadas y los documentos solo aparecerán listados.</p>
                        </div>
                    </div>
                </div>

                <div class="custom-modal-footer ticket-pdf-modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeTicketPdfModal()">Cancelar</button>
                    <button type="submit" class="btn-primary ticket-pdf-generate-btn">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        Generar PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>

/**
 * Cronómetro SLA visual en tiempo real.
 *
 * En lugar de sumar un segundo por intervalo, calcula la diferencia real
 * desde que la página fue abierta. Así no se congela cuando la pestaña
 * pierde foco, el navegador reduce los intervalos o el equipo entra en reposo.
 */
function formatSlaSeconds(totalSeconds) {
    totalSeconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));

    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return String(hours).padStart(2, '0')
        + ':' + String(minutes).padStart(2, '0')
        + ':' + String(seconds).padStart(2, '0');
}

function fixedTimezoneParts(timestampMs, offsetSeconds) {
    const shifted = new Date(timestampMs + (offsetSeconds * 1000));

    return {
        year: shifted.getUTCFullYear(),
        month: shifted.getUTCMonth(),
        day: shifted.getUTCDate(),
        weekday: shifted.getUTCDay(),
        hour: shifted.getUTCHours(),
        minute: shifted.getUTCMinutes(),
        second: shifted.getUTCSeconds()
    };
}

function isSlaScheduleRunning(timestampMs, contractType, offsetSeconds, workStartMinutes, workEndMinutes, workDays) {
    if (contractType === '24_7') {
        return true;
    }

    const parts = fixedTimezoneParts(timestampMs, offsetSeconds);
    const isoWeekday = parts.weekday === 0 ? 7 : parts.weekday;

    if (!workDays.includes(isoWeekday)) {
        return false;
    }

    const currentMinutes = (parts.hour * 60) + parts.minute;
    return currentMinutes >= workStartMinutes && currentMinutes < workEndMinutes;
}

function businessSecondsBetween(startMs, endMs, offsetSeconds, workStartMinutes, workEndMinutes, workDays) {
    if (endMs <= startMs) {
        return 0;
    }

    const startParts = fixedTimezoneParts(startMs, offsetSeconds);
    const endParts = fixedTimezoneParts(endMs, offsetSeconds);

    let localDayCursor = Date.UTC(
        startParts.year,
        startParts.month,
        startParts.day
    );

    const localEndDay = Date.UTC(
        endParts.year,
        endParts.month,
        endParts.day
    );

    let totalMilliseconds = 0;

    while (localDayCursor <= localEndDay) {
        const localDay = new Date(localDayCursor);
        const weekday = localDay.getUTCDay();
        const isoWeekday = weekday === 0 ? 7 : weekday;

        if (workDays.includes(isoWeekday)) {
            const year = localDay.getUTCFullYear();
            const month = localDay.getUTCMonth();
            const day = localDay.getUTCDate();
            const workStartHour = Math.floor(workStartMinutes / 60);
            const workStartMinute = workStartMinutes % 60;
            const workEndHour = Math.floor(workEndMinutes / 60);
            const workEndMinute = workEndMinutes % 60;

            const workStartMs = Date.UTC(year, month, day, workStartHour, workStartMinute, 0)
                - (offsetSeconds * 1000);
            const workEndMs = Date.UTC(year, month, day, workEndHour, workEndMinute, 0)
                - (offsetSeconds * 1000);

            const overlapStart = Math.max(startMs, workStartMs);
            const overlapEnd = Math.min(endMs, workEndMs);

            if (overlapEnd > overlapStart) {
                totalMilliseconds += overlapEnd - overlapStart;
            }
        }

        localDayCursor += 24 * 60 * 60 * 1000;
    }

    return Math.floor(totalMilliseconds / 1000);
}

function initializeSlaTimerCard(card) {
    if (!card || card.dataset.slaRealtimeReady === '1') {
        return;
    }

    card.dataset.slaRealtimeReady = '1';
    card.dataset.baseElapsedSeconds = String(
        Math.max(0, Number(card.dataset.elapsedSeconds || 0))
    );
    card.dataset.clientStartedAt = String(Date.now());

    const badge = card.querySelector('[data-sla-badge]');
    const note = card.querySelector('[data-sla-note]');

    card.dataset.initialPhaseClass = badge
        ? Array.from(badge.classList)
            .find(className => className.startsWith('sla-phase-')) || 'sla-phase-paused'
        : 'sla-phase-paused';

    card.dataset.initialPhaseLabel = badge?.textContent?.trim() || 'Ticket cerrado';
    card.dataset.initialNote = note?.textContent?.trim() || '';
}

function updateSlaTimerCard(card) {
    initializeSlaTimerCard(card);

    const badge = card.querySelector('[data-sla-badge]');
    const bar = card.querySelector('[data-sla-bar]');
    const percentText = card.querySelector('[data-sla-percent]');
    const elapsedText = card.querySelector('[data-sla-elapsed]');
    const remainingText = card.querySelector('[data-sla-remaining]');
    const remainingLabel = card.querySelector('[data-sla-remaining-label]');
    const note = card.querySelector('[data-sla-note]');

    if (!badge || !bar || !percentText || !elapsedText || !remainingText || !remainingLabel || !note) {
        return;
    }

    const nowMs = Date.now();
    const isClosed = card.dataset.isClosed === '1';
    const contractType = card.dataset.contract || '8_5';
    const timezoneOffset = Number(card.dataset.timezoneOffset || -18000);
    const workStartMinutes = Number(card.dataset.workStartMinutes || 480);
    const workEndMinutes = Number(card.dataset.workEndMinutes || 1020);
    const workDays = String(card.dataset.workDays || '1,2,3,4,5')
        .split(',')
        .map(value => Number(value))
        .filter(value => value >= 1 && value <= 7);
    const warningPercent = Number(card.dataset.warningPercent || 75);
    const criticalPercent = Number(card.dataset.criticalPercent || 90);
    const statusPaused = card.dataset.statusPaused === '1';
    const slaSeconds = Math.max(0, Number(card.dataset.slaSeconds || 0));
    const baseElapsedSeconds = Math.max(
        0,
        Number(card.dataset.baseElapsedSeconds || card.dataset.elapsedSeconds || 0)
    );
    const clientStartedAt = Number(card.dataset.clientStartedAt || nowMs);

    let elapsedSeconds = baseElapsedSeconds;

    if (!isClosed && !statusPaused && slaSeconds > 0) {
        if (contractType === '24_7') {
            elapsedSeconds += Math.max(
                0,
                Math.floor((nowMs - clientStartedAt) / 1000)
            );
        } else {
            elapsedSeconds += businessSecondsBetween(
                clientStartedAt,
                nowMs,
                timezoneOffset,
                workStartMinutes,
                workEndMinutes,
                workDays
            );
        }
    }

    const scheduleRunning = isSlaScheduleRunning(
        nowMs,
        contractType,
        timezoneOffset,
        workStartMinutes,
        workEndMinutes,
        workDays
    );

    const remainingSigned = slaSeconds - elapsedSeconds;
    const displaySeconds = Math.abs(remainingSigned);
    const rawPercent = slaSeconds > 0
        ? Math.max(0, (elapsedSeconds / slaSeconds) * 100)
        : 0;
    const barPercent = Math.min(100, rawPercent);

    let phaseClass = 'sla-phase-green';
    let label = 'Dentro del SLA';
    let message = 'El ticket se encuentra dentro del tiempo objetivo.';

    if (slaSeconds <= 0) {
        phaseClass = 'sla-phase-paused';
        label = 'SLA no definido';
        message = 'Este ticket no tiene un SLA objetivo configurado.';
    } else if (isClosed) {
        phaseClass = card.dataset.initialPhaseClass || 'sla-phase-paused';
        label = card.dataset.initialPhaseLabel || 'Ticket cerrado';
        message = card.dataset.initialNote || 'El conteo del SLA finalizó.';
    } else if (statusPaused) {
        phaseClass = 'sla-phase-paused';
        label = 'Conteo pausado';
        message = 'El contador está pausado por el estado actual del ticket.';
    } else if (contractType !== '24_7' && !scheduleRunning) {
        phaseClass = 'sla-phase-paused';
        label = 'Conteo pausado';
        message = 'El contador está fuera del horario de atención configurado.';
    } else if (rawPercent >= 100) {
        phaseClass = 'sla-phase-red';
        label = 'SLA vencido';
        message = 'El tiempo objetivo fue superado y el ticket requiere atención prioritaria.';
    } else if (rawPercent >= criticalPercent) {
        phaseClass = 'sla-phase-yellow';
        label = 'Alerta crítica';
        message = 'El ticket alcanzó el nivel crítico del SLA.';
    } else if (rawPercent >= warningPercent) {
        phaseClass = 'sla-phase-yellow';
        label = 'Próximo a vencer';
        message = 'El ticket está cerca de consumir el tiempo objetivo.';
    }

    badge.className = 'sla-timer-badge ' + phaseClass;
    bar.className = 'sla-timer-bar-fill ' + phaseClass;
    badge.textContent = label;
    bar.style.width = barPercent.toFixed(2) + '%';
    percentText.textContent = rawPercent.toFixed(1) + '%';
    elapsedText.textContent = formatSlaSeconds(elapsedSeconds);
    remainingText.textContent = formatSlaSeconds(displaySeconds);
    remainingLabel.textContent = remainingSigned < 0
        ? 'Tiempo excedido'
        : 'Tiempo restante';
    note.textContent = message;
    note.classList.toggle('paused', phaseClass === 'sla-phase-paused');
    card.setAttribute('title', message);
}

function updateAllSlaTimers() {
    document.querySelectorAll('[data-sla-timer="1"]').forEach(updateSlaTimerCard);
}

updateAllSlaTimers();

const slaRealtimeInterval = window.setInterval(updateAllSlaTimers, 1000);

document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
        updateAllSlaTimers();
    }
});

window.addEventListener('focus', updateAllSlaTimers);
window.addEventListener('pageshow', updateAllSlaTimers);

let deleteMessageId = null;
let closeTicketId = null;

function updateTicketPdfType() {
    const selected = document.querySelector('#ticketPdfExportForm input[name="type"]:checked');
    const type = selected?.value || 'executive';
    const options = document.getElementById('ticketPdfExecutiveOptions');
    const fullNote = document.getElementById('ticketPdfFullNote');

    document.querySelectorAll('[data-pdf-type-card]').forEach(function (card) {
        card.classList.toggle('is-selected', card.dataset.pdfTypeCard === type);
    });

    if (options) {
        options.hidden = type !== 'executive';
    }

    if (fullNote) {
        fullNote.hidden = type !== 'full';
    }
}

function openTicketPdfModal() {
    const modal = document.getElementById('ticketPdfModal');
    if (!modal) return;

    updateTicketPdfType();
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ticket-pdf-modal-open');

    window.setTimeout(function () {
        modal.querySelector('input[name="type"]:checked')?.focus();
    }, 40);
}

function closeTicketPdfModal() {
    const modal = document.getElementById('ticketPdfModal');
    if (!modal) return;

    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('ticket-pdf-modal-open');
}

document.querySelectorAll('#ticketPdfExportForm input[name="type"]').forEach(function (radio) {
    radio.addEventListener('change', updateTicketPdfType);
});

const ticketPdfModal = document.getElementById('ticketPdfModal');
if (ticketPdfModal) {
    ticketPdfModal.addEventListener('click', function (event) {
        if (event.target === ticketPdfModal) {
            closeTicketPdfModal();
        }
    });
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && document.getElementById('ticketPdfModal')?.classList.contains('show')) {
        closeTicketPdfModal();
    }
});

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
function decodeBase64Utf8(value) {
    try {
        const bytes = Uint8Array.from(atob(value), character => character.charCodeAt(0));
        return new TextDecoder('utf-8').decode(bytes);
    } catch (error) {
        return '';
    }
}

function escapeRichText(value) {
    const element = document.createElement('div');
    element.textContent = value;
    return element.innerHTML.replace(/\n/g, '<br>');
}

function openEditMessageModalFromButton(button) {
    if (!button) return;

    const messageId = button.getAttribute('data-message-id') || '';
    const encodedContent = button.getAttribute('data-message-content') || '';
    const messageFormat = button.getAttribute('data-message-format') || 'plain';
    const decodedContent = decodeBase64Utf8(encodedContent);

    openEditMessageModal(messageId, decodedContent, messageFormat);
}

function openEditMessageModal(messageId, messageContent, messageFormat) {
    const modal = document.getElementById('editMessageModal');
    const input = document.getElementById('editMessageId');
    const editor = document.getElementById('editMessageRichEditor');
    const hiddenInput = document.getElementById('editMessageRichInput');

    if (!modal || !input || !editor || !hiddenInput) return;

    input.value = messageId;
    editor.innerHTML = messageFormat === 'html'
        ? messageContent
        : '<p>' + escapeRichText(messageContent) + '</p>';
    hiddenInput.value = editor.innerHTML;
    modal.classList.add('show');

    setTimeout(function () {
        editor.focus();
    }, 80);
}

function closeEditMessageModal() {
    const modal = document.getElementById('editMessageModal');
    const input = document.getElementById('editMessageId');
    const editor = document.getElementById('editMessageRichEditor');
    const hiddenInput = document.getElementById('editMessageRichInput');

    if (modal) modal.classList.remove('show');
    if (input) input.value = '';
    if (editor) editor.innerHTML = '';
    if (hiddenInput) hiddenInput.value = '';
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
function updateCloseReasonDetails() {
    const select = document.getElementById('closureReasonId');
    const preview = document.getElementById('closeReasonPreview');
    const previewTitle = document.getElementById('closeReasonPreviewTitle');
    const previewDescription = document.getElementById('closeReasonPreviewDescription');
    const comment = document.getElementById('closureComment');
    const requiredMark = document.getElementById('closureCommentRequiredMark');
    const requirement = document.getElementById('closureCommentRequirement');

    if (!select) {
        validateCloseTicketForm();
        return;
    }

    const option = select.selectedOptions[0];
    const hasReason = Boolean(select.value);
    const requiresComment = option?.dataset.requiresComment === '1';
    const description = option?.dataset.description?.trim() || 'Sin descripción adicional.';

    if (preview) {
        preview.hidden = !hasReason;
    }

    if (previewTitle) {
        previewTitle.textContent = hasReason ? option.textContent.trim() : 'Motivo seleccionado';
    }

    if (previewDescription) {
        previewDescription.textContent = description;
    }

    if (comment) {
        comment.required = requiresComment;
        comment.setAttribute('aria-required', requiresComment ? 'true' : 'false');
    }

    if (requiredMark) {
        requiredMark.hidden = !requiresComment;
    }

    if (requirement) {
        requirement.textContent = requiresComment ? 'Obligatorio para este motivo' : 'Opcional';
        requirement.classList.toggle('is-required', requiresComment);
    }

    validateCloseTicketForm();
}

function updateClosureCommentCounter() {
    const comment = document.getElementById('closureComment');
    const counter = document.getElementById('closureCommentCounter');

    if (!comment || !counter) {
        return;
    }

    counter.textContent = String(comment.value.length) + ' / 2000';
}

function validateCloseTicketForm() {
    const select = document.getElementById('closureReasonId');
    const comment = document.getElementById('closureComment');
    const checkbox = document.getElementById('confirmCloseTicketCheckbox');
    const confirmValue = document.getElementById('confirmCloseTicketValue');
    const confirmBtn = document.getElementById('confirmCloseTicketBtn');

    if (!confirmBtn) {
        return false;
    }

    const option = select?.selectedOptions[0];
    const requiresComment = option?.dataset.requiresComment === '1';
    const hasReason = Boolean(select?.value);
    const hasRequiredComment = !requiresComment || Boolean(comment?.value.trim());
    const isConfirmed = Boolean(checkbox?.checked);
    const isValid = Boolean(closeTicketId && hasReason && hasRequiredComment && isConfirmed);

    if (confirmValue) {
        confirmValue.value = isConfirmed ? '1' : '0';
    }

    confirmBtn.disabled = !isValid;
    confirmBtn.classList.toggle('is-ready', isValid);

    updateClosureCommentCounter();

    return isValid;
}

function openCloseTicketModal(ticketId) {
    closeTicketId = Number(ticketId) || null;

    const modal = document.getElementById('closeTicketModal');
    const form = document.getElementById('closeTicketForm');
    const ticketInput = document.getElementById('closeTicketFormId');
    const select = document.getElementById('closureReasonId');
    const comment = document.getElementById('closureComment');
    const checkbox = document.getElementById('confirmCloseTicketCheckbox');
    const confirmValue = document.getElementById('confirmCloseTicketValue');
    const confirmBtn = document.getElementById('confirmCloseTicketBtn');

    if (!modal || !form || !ticketInput || !closeTicketId) {
        return;
    }

    form.reset();
    ticketInput.value = String(closeTicketId);

    if (comment) {
        comment.required = false;
    }

    if (checkbox) {
        checkbox.checked = false;
    }

    if (confirmValue) {
        confirmValue.value = '0';
    }

    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.classList.remove('is-ready', 'is-loading');
        confirmBtn.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Cerrar ticket</span>';
    }

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ticket-close-modal-open');

    updateCloseReasonDetails();
    updateClosureCommentCounter();

    window.setTimeout(function () {
        select?.focus();
    }, 60);
}

function closeCloseTicketModal() {
    const modal = document.getElementById('closeTicketModal');

    if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.body.classList.remove('ticket-close-modal-open');
    closeTicketId = null;
}

function toggleCloseTicketButton() {
    validateCloseTicketForm();
}

document.getElementById('closureComment')?.addEventListener('input', function () {
    validateCloseTicketForm();
});

document.getElementById('closeTicketForm')?.addEventListener('submit', function (event) {
    if (!validateCloseTicketForm()) {
        event.preventDefault();
        return;
    }

    const button = document.getElementById('confirmCloseTicketBtn');

    if (button) {
        button.disabled = true;
        button.classList.add('is-loading');
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><span>Cerrando ticket...</span>';
    }
});

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
    const ticketPdfModal = document.getElementById('ticketPdfModal');

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

    if (ticketPdfModal && ticketPdfModal.classList.contains('show') && e.target === ticketPdfModal) {
        closeTicketPdfModal();
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


/**
 * Editor enriquecido de mensajes
 */
(function () {
    function executeRichCommand(editor, command, value = null) {
        editor.focus();
        document.execCommand('styleWithCSS', false, true);
        document.execCommand(command, false, value);
    }

    function applyFontSize(editor, size) {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return;
        }

        const range = selection.getRangeAt(0);
        const span = document.createElement('span');
        span.style.fontSize = size;

        try {
            range.surroundContents(span);
        } catch (error) {
            const fragment = range.extractContents();
            span.appendChild(fragment);
            range.insertNode(span);
        }

        selection.removeAllRanges();
        const newRange = document.createRange();
        newRange.selectNodeContents(span);
        selection.addRange(newRange);
        editor.focus();
    }

    function createFileDataTransfer() {
        try {
            return new DataTransfer();
        } catch (error) {
            return null;
        }
    }

    function initializeRichEditor(field) {
        if (!field || field.dataset.richEditorReady === '1') {
            return;
        }

        field.dataset.richEditorReady = '1';

        const editor = field.querySelector('.ticket-rich-editor-area');
        const hiddenInput = field.querySelector('[data-rich-input]');
        const toolbar = field.querySelector('.ticket-rich-toolbar');
        const imageInput = field.querySelector('[data-rich-images]');
        const documentsInput = field.querySelector('[data-rich-documents]');
        const documentTrigger = field.querySelector('[data-document-trigger]');
        const documentList = field.querySelector('[data-document-list]');
        const documentDropzone = field.querySelector('[data-document-dropzone]');
        const documentCount = field.querySelector('[data-document-count]');
        const characterCounter = field.querySelector('[data-rich-counter]');

        if (!editor || !hiddenInput || !toolbar) {
            return;
        }

        const imageTransfer = imageInput ? createFileDataTransfer() : null;
        let documentTransfer = documentsInput ? createFileDataTransfer() : null;

        toolbar.addEventListener('mousedown', function (event) {
            if (event.target.closest('button')) {
                event.preventDefault();
            }
        });

        toolbar.addEventListener('click', function (event) {
            const button = event.target.closest('button');

            if (!button) {
                return;
            }

            const command = button.dataset.richCommand;
            const action = button.dataset.richAction;

            if (command) {
                executeRichCommand(editor, command);
                return;
            }

            if (action === 'link') {
                const url = window.prompt('Escribe la dirección del enlace:');

                if (url) {
                    executeRichCommand(editor, 'createLink', url);
                }

                return;
            }

            if (action === 'image' && imageInput) {
                imageInput.click();
            }
        });

        const blockSelect = toolbar.querySelector('[data-rich-block]');

        blockSelect?.addEventListener('change', function () {
            executeRichCommand(editor, 'formatBlock', this.value);
            this.value = 'p';
        });

        const sizeSelect = toolbar.querySelector('[data-rich-size]');

        sizeSelect?.addEventListener('change', function () {
            applyFontSize(editor, this.value);
        });

        const colorInput = toolbar.querySelector('[data-rich-color]');

        colorInput?.addEventListener('input', function () {
            executeRichCommand(editor, 'foreColor', this.value);
        });

        const highlightInput = toolbar.querySelector('[data-rich-highlight]');

        highlightInput?.addEventListener('input', function () {
            executeRichCommand(editor, 'hiliteColor', this.value);
        });

        function addInlineImage(file) {
            if (!imageInput || !imageTransfer) {
                return;
            }

            if (!/^image\/(jpeg|png|webp|gif)$/i.test(file.type)) {
                window.alert('Solo se permiten imágenes JPG, PNG, WEBP o GIF.');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                window.alert('Cada imagen debe pesar como máximo 10 MB.');
                return;
            }

            if (imageTransfer.items.length >= 8) {
                window.alert('Solo puedes insertar hasta 8 imágenes por mensaje.');
                return;
            }

            const inlineIndex = imageTransfer.items.length;
            imageTransfer.items.add(file);
            imageInput.files = imageTransfer.files;

            const reader = new FileReader();

            reader.onload = function () {
                editor.focus();

                const image = document.createElement('img');
                image.src = String(reader.result || '');
                image.alt = file.name || 'Imagen adjunta';
                image.dataset.inlineIndex = String(inlineIndex);
                image.className = 'ticket-rich-inline-image';

                const selection = window.getSelection();

                if (selection && selection.rangeCount > 0) {
                    const range = selection.getRangeAt(0);
                    range.deleteContents();
                    range.insertNode(image);
                    range.setStartAfter(image);
                    range.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(range);
                } else {
                    editor.appendChild(image);
                }

                editor.appendChild(document.createElement('p'));
            };

            reader.readAsDataURL(file);
        }

        imageInput?.addEventListener('change', function () {
            Array.from(this.files || []).forEach(addInlineImage);
        });

        editor.addEventListener('paste', function (event) {
            const pastedImages = Array.from(event.clipboardData?.files || [])
                .filter(file => file.type.startsWith('image/'));

            if (pastedImages.length === 0) {
                return;
            }

            event.preventDefault();
            pastedImages.forEach(addInlineImage);
        });

        function rebuildDocumentInput(files) {
            if (!documentsInput) {
                return;
            }

            documentTransfer = createFileDataTransfer();

            if (!documentTransfer) {
                return;
            }

            files.forEach(file => documentTransfer.items.add(file));
            documentsInput.files = documentTransfer.files;
            renderDocuments();
        }

        function renderDocuments() {
            if (!documentList || !documentsInput) {
                return;
            }

            const files = Array.from(documentsInput.files || []);
            documentList.innerHTML = '';

            if (documentCount) {
                documentCount.textContent = files.length === 0
                    ? 'Sin archivos'
                    : files.length + (files.length === 1 ? ' archivo' : ' archivos');
            }

            documentDropzone?.classList.toggle('has-files', files.length > 0);

            files.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'ticket-document-chip';

                const copy = document.createElement('span');
                copy.innerHTML = '<strong></strong><small></small>';
                copy.querySelector('strong').textContent = file.name;
                copy.querySelector('small').textContent =
                    (file.size / 1024 / 1024).toFixed(2) + ' MB';

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', 'Quitar archivo');
                remove.textContent = '×';
                remove.addEventListener('click', function () {
                    const nextFiles = files.filter((_, fileIndex) => fileIndex !== index);
                    rebuildDocumentInput(nextFiles);
                });

                item.appendChild(copy);
                item.appendChild(remove);
                documentList.appendChild(item);
            });
        }

        documentTrigger?.addEventListener('click', function () {
            documentsInput?.click();
        });

        function appendDocuments(selectedFiles) {
            if (!documentsInput || selectedFiles.length === 0) {
                return;
            }

            const currentFiles = Array.from(documentsInput.files || []);
            const mergedFiles = [...currentFiles];

            for (const file of selectedFiles) {
                if (mergedFiles.length >= 8) {
                    window.alert('Solo puedes adjuntar hasta 8 documentos.');
                    break;
                }

                if (file.size > 15 * 1024 * 1024) {
                    window.alert('El archivo "' + file.name + '" supera el límite de 15 MB.');
                    continue;
                }

                const duplicate = mergedFiles.some(existing =>
                    existing.name === file.name
                    && existing.size === file.size
                    && existing.lastModified === file.lastModified
                );

                if (!duplicate) {
                    mergedFiles.push(file);
                }
            }

            rebuildDocumentInput(mergedFiles);
        }

        documentsInput?.addEventListener('change', function () {
            const selected = Array.from(this.files || []);
            const previousFiles = documentTransfer
                ? Array.from(documentTransfer.files || [])
                : [];

            rebuildDocumentInput(previousFiles);
            appendDocuments(selected);
        });

        if (documentDropzone && documentsInput) {
            ['dragenter', 'dragover'].forEach(function (eventName) {
                documentDropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    documentDropzone.classList.add('is-dragging');
                });
            });

            ['dragleave', 'drop'].forEach(function (eventName) {
                documentDropzone.addEventListener(eventName, function (event) {
                    event.preventDefault();
                    documentDropzone.classList.remove('is-dragging');
                });
            });

            documentDropzone.addEventListener('drop', function (event) {
                appendDocuments(Array.from(event.dataTransfer?.files || []));
            });
        }

        const form = field.closest('form');

        function updateCharacterCounter() {
            if (!characterCounter) {
                return;
            }

            const characterCount = editor.innerText
                .replace(/\u200B/g, '')
                .replace(/\u00A0/g, ' ')
                .trim()
                .length;

            characterCounter.textContent = characterCount
                + (characterCount === 1 ? ' carácter' : ' caracteres');
            characterCounter.classList.toggle('is-warning', characterCount > 8000);
        }

        updateCharacterCounter();
        renderDocuments();

        form?.addEventListener('submit', function (event) {
            hiddenInput.value = editor.innerHTML.trim();

            const hasText = editor.innerText
                .replace(/\u200B/g, '')
                .replace(/\u00A0/g, ' ')
                .trim() !== '';
            const hasImage = editor.querySelector('img') !== null;
            const hasDocuments = (documentsInput?.files?.length || 0) > 0;

            if (!hasText && !hasImage && !hasDocuments) {
                event.preventDefault();
                editor.focus();
                field.classList.add('has-error');
                window.alert('Escribe un mensaje o adjunta un archivo.');
                return;
            }

            field.classList.remove('has-error');

            const submitButton = form.querySelector('button[type="submit"]');

            if (submitButton && !submitButton.dataset.originalContent) {
                submitButton.dataset.originalContent = submitButton.innerHTML;
            }

            if (submitButton && !submitButton.disabled) {
                const loadingLabel = submitButton.dataset.loadingLabel || 'Enviando...';
                submitButton.disabled = true;
                submitButton.classList.add('is-loading');
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i><span>'
                    + loadingLabel
                    + '</span>';
            }
        });

        const syncEditorValue = () => {
            hiddenInput.value = editor.innerHTML.trim();
        };

        editor.addEventListener('input', function () {
            syncEditorValue();
            updateCharacterCounter();
            field.classList.remove('has-error');
        });

        editor.addEventListener('blur', syncEditorValue);
        editor.addEventListener('keyup', syncEditorValue);

        form?.addEventListener('formdata', function () {
            syncEditorValue();
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('[data-rich-editor]').forEach(function (field) {
            const editor = field.querySelector('.ticket-rich-editor-area');
            const hiddenInput = field.querySelector('[data-rich-input]');

            if (editor && hiddenInput) {
                hiddenInput.value = editor.innerHTML.trim();
            }
        });
    }, true);

    function initializeAllRichEditors() {
        document.querySelectorAll('[data-rich-editor]').forEach(initializeRichEditor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAllRichEditors, { once: true });
    } else {
        initializeAllRichEditors();
    }
})();


    function initializeTicketMessageUX() {
        const list = document.querySelector('[data-ticket-message-scroll]');

        if (list) {
            list.scrollTop = list.scrollHeight;
        }

        document.querySelectorAll('[data-collapsible-message]').forEach(function (messageBody) {
            const toggle = messageBody.parentElement?.querySelector('[data-message-toggle]');

            if (!toggle) {
                return;
            }

            messageBody.classList.add('is-collapsed');

            window.requestAnimationFrame(function () {
                const hasOverflow = messageBody.scrollHeight > messageBody.clientHeight + 8;

                if (!hasOverflow) {
                    messageBody.classList.remove('is-collapsed');
                    toggle.hidden = true;
                    return;
                }

                toggle.hidden = false;
                toggle.textContent = 'Ver más';
            });

            toggle.addEventListener('click', function () {
                const isCollapsed = messageBody.classList.toggle('is-collapsed');
                toggle.textContent = isCollapsed ? 'Ver más' : 'Ver menos';
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTicketMessageUX, { once: true });
    } else {
        initializeTicketMessageUX();
    }

</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
