<?php
function prettyStatus(?string $status): string
{
    if ($status === null || $status === '') {
        return 'Sin estado';
    }

    return [
        'ABIERTO' => 'Abierto',
        'EN_PROCESO' => 'En proceso',
        'RESPONDIDO' => 'Respondido',
        'CERRADO' => 'Cerrado',
    ][$status] ?? ucfirst(strtolower(str_replace('_', ' ', $status)));
}
?>

<?php
require_once __DIR__ . '/../../helpers/session.php';
require_once __DIR__ . '/../../helpers/business_hours.php';
require_once __DIR__ . '/../../helpers/sla_helper.php';

$title = 'Gestión de Tickets';
$search = $search ?? ($_GET['search'] ?? '');
$status = $status ?? ($_GET['status'] ?? '');
$priority = $priority ?? ($_GET['priority'] ?? '');
$category = $category ?? ($_GET['category'] ?? '');
$ticketCode = $ticketCode ?? ($_GET['ticket_code'] ?? '');
$assignedTo = $assignedTo ?? ($_GET['assigned_to'] ?? '');
$techLevel = $techLevel ?? ($_GET['tech_level'] ?? '');
$assignmentStatus = $assignmentStatus ?? ($_GET['assignment_status'] ?? '');
$slaStatus = $slaStatus ?? ($_GET['sla_status'] ?? '');
$dateFrom = $dateFrom ?? ($_GET['date_from'] ?? '');
$dateTo = $dateTo ?? ($_GET['date_to'] ?? '');
$techUsers = $techUsers ?? [];

$perPage = $perPage ?? (int)($_GET['per_page'] ?? 10);
$currentPage = $currentPage ?? (int)($_GET['page'] ?? 1);
$totalTickets = $totalTickets ?? count($tickets ?? []);
$totalPages = $totalPages ?? 1;
$showingFrom = $showingFrom ?? (!empty($tickets) ? 1 : 0);
$showingTo = $showingTo ?? count($tickets ?? []);

if (!function_exists('buildTicketPaginationUrl')) {
    function buildTicketPaginationUrl(array $extra = []): string
    {
        $query = $_GET;

        foreach ($extra as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        return '/helpdesk-php/admin-tickets.php' . (!empty($query) ? '?' . http_build_query($query) : '');
    }
}


$techLevelById = [];
$techLevelByName = [];

foreach ($techUsers as $tech) {
    $techId = (int)($tech['id'] ?? 0);
    $currentTechLevel = (int)($tech['tech_level'] ?? 1);
    $techNameKey = mb_strtolower(trim((string)($tech['name'] ?? '')));

    if ($techId > 0) {
        $techLevelById[$techId] = $currentTechLevel;
    }

    if ($techNameKey !== '') {
        $techLevelByName[$techNameKey] = $currentTechLevel;
    }
}

if (!function_exists('getAssignedTechLevelForTicket')) {
    function getAssignedTechLevelForTicket(array $ticket, array $techLevelById, array $techLevelByName): int
    {
        $assignedId = (int)($ticket['assigned_to'] ?? 0);

        if ($assignedId > 0 && isset($techLevelById[$assignedId])) {
            return (int)$techLevelById[$assignedId];
        }

        $assignedNameKey = mb_strtolower(trim((string)($ticket['assigned_name'] ?? '')));

        if ($assignedNameKey !== '' && isset($techLevelByName[$assignedNameKey])) {
            return (int)$techLevelByName[$assignedNameKey];
        }

        foreach (['assigned_tech_level', 'assigned_level', 'technician_level', 'tech_level'] as $levelKey) {
            if (isset($ticket[$levelKey]) && (int)$ticket[$levelKey] > 0) {
                return (int)$ticket[$levelKey];
            }
        }

        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Variables para layouts admin
|--------------------------------------------------------------------------
| Estas variables las usan:
| - admin-sidebar.php para marcar el menú activo
| - admin-topbar.php para mostrar título/subtítulo
*/
$activePage = 'tickets';
$pageTitle = 'Gestión de Tickets';
$pageSubtitle = 'Consulta, filtra, asigna y analiza tickets con indicadores operativos.';

/*
|--------------------------------------------------------------------------
| Botones extra del topbar
|--------------------------------------------------------------------------
| En tu tickets.php original tenías:
| - Panel admin
| - Ir al inicio
|
| Para respetarlo, usamos esta variable en admin-topbar.php.
*/
$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/index.php',
        'class' => 'btn-secondary',
        'text' => 'Panel admin'
    ],
    [
        'href' => '/helpdesk-php/home.php',
        'class' => 'btn-secondary',
        'text' => 'Ir al inicio'
    ]
];

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">

    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">

        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content">
            <section class="card admin-filters-card">
                <div class="my-tickets-header">
                    <h2>Filtros</h2>
                    <p>Consulta y administra tickets con criterios operativos y de búsqueda.</p>
                </div>

                <form action="/helpdesk-php/admin-tickets.php" method="GET" class="ticket-form admin-ticket-filter-form">
                    <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
                    <div class="admin-ticket-filter-grid">
                        <div class="form-group">
                            <label for="ticket_code">Código de ticket</label>
                            <input
                                type="text"
                                id="ticket_code"
                                name="ticket_code"
                                value="<?= htmlspecialchars($ticketCode) ?>"
                                placeholder="#1, #2, #3...">
                        </div>

                        <div class="form-group form-group-wide">
                            <label for="search">Buscar</label>
                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Asunto, descripción, cliente o técnico">
                        </div>

                        <div class="form-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="ABIERTO" <?= $status === 'ABIERTO' ? 'selected' : '' ?>>Abierto</option>
                                <option value="EN_PROCESO" <?= $status === 'EN_PROCESO' ? 'selected' : '' ?>>En proceso</option>
                                <option value="RESPONDIDO" <?= $status === 'RESPONDIDO' ? 'selected' : '' ?>>Respondido</option>
                                <option value="CERRADO" <?= $status === 'CERRADO' ? 'selected' : '' ?>>Cerrado</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="priority">Prioridad</label>
                            <select id="priority" name="priority">
                                <option value="">Todas</option>
                                <option value="BAJA" <?= $priority === 'BAJA' ? 'selected' : '' ?>>Baja</option>
                                <option value="MEDIA" <?= $priority === 'MEDIA' ? 'selected' : '' ?>>Media</option>
                                <option value="ALTA" <?= $priority === 'ALTA' ? 'selected' : '' ?>>Alta</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="category">Categoría</label>
                            <select id="category" name="category">
                                <option value="">Todas</option>
                                <option value="ACCESO" <?= $category === 'ACCESO' ? 'selected' : '' ?>>Acceso</option>
                                <option value="SISTEMA" <?= $category === 'SISTEMA' ? 'selected' : '' ?>>Sistema</option>
                                <option value="HARDWARE" <?= $category === 'HARDWARE' ? 'selected' : '' ?>>Hardware</option>
                                <option value="SOFTWARE" <?= $category === 'SOFTWARE' ? 'selected' : '' ?>>Software</option>
                                <option value="RED" <?= $category === 'RED' ? 'selected' : '' ?>>Red</option>
                                <option value="OTROS" <?= $category === 'OTROS' ? 'selected' : '' ?>>Otros</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="assigned_to">Técnico asignado</label>
                            <select id="assigned_to" name="assigned_to">
                                <option value="">Todos</option>
                                <?php foreach ($techUsers as $techFilter): ?>
                                    <?php $techFilterId = (int)($techFilter['id'] ?? 0); ?>
                                    <option value="<?= $techFilterId ?>" <?= (string)$assignedTo === (string)$techFilterId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($techFilter['name'] ?? '') ?> - Nivel <?= (int)($techFilter['tech_level'] ?? 1) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tech_level">Nivel técnico</label>
                            <select id="tech_level" name="tech_level">
                                <option value="">Todos</option>
                                <option value="1" <?= (string)$techLevel === '1' ? 'selected' : '' ?>>Nivel 1</option>
                                <option value="2" <?= (string)$techLevel === '2' ? 'selected' : '' ?>>Nivel 2</option>
                                <option value="3" <?= (string)$techLevel === '3' ? 'selected' : '' ?>>Nivel 3</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="assignment_status">Asignación</label>
                            <select id="assignment_status" name="assignment_status">
                                <option value="">Todos</option>
                                <option value="ASIGNADO" <?= $assignmentStatus === 'ASIGNADO' ? 'selected' : '' ?>>Asignados</option>
                                <option value="SIN_ASIGNAR" <?= $assignmentStatus === 'SIN_ASIGNAR' ? 'selected' : '' ?>>Sin asignar</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="sla_status">SLA cumplido</label>
                            <select id="sla_status" name="sla_status">
                                <option value="">Todos</option>
                                <option value="CUMPLIDO" <?= $slaStatus === 'CUMPLIDO' ? 'selected' : '' ?>>Cumplido</option>
                                <option value="NO_CUMPLIDO" <?= $slaStatus === 'NO_CUMPLIDO' ? 'selected' : '' ?>>No cumplido</option>
                                <option value="PENDIENTE" <?= $slaStatus === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="date_from">Fecha desde</label>
                            <input
                                type="date"
                                id="date_from"
                                name="date_from"
                                value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>

                        <div class="form-group">
                            <label for="date_to">Fecha hasta</label>
                            <input
                                type="date"
                                id="date_to"
                                name="date_to"
                                value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                    </div>

                    <div class="ticket-form-actions admin-ticket-filter-actions">
                        <a href="/helpdesk-php/admin-tickets.php" class="btn-secondary">Limpiar</a>
                        <button type="submit" class="btn-primary">Filtrar</button>
                    </div>
                </form>
            </section>

            <section class="card my-tickets-card">
                <div class="my-tickets-header admin-ticket-list-header">
                    <div>
                        <h2>Listado general de tickets</h2>
                        <p>Incluye tiempos de respuesta, resolución y cumplimiento de SLA por ticket.</p>
                    </div>

                    <div class="ticket-page-size-box">
                        <span>Ver</span>
                        <form action="/helpdesk-php/admin-tickets.php" method="GET" class="ticket-page-size-form">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if (!in_array($key, ['per_page', 'page'], true) && is_scalar($value)): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars((string)$value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <select name="per_page" onchange="this.form.submit()" aria-label="Cantidad de tickets por página">
                                <option value="10" <?= (int)$perPage === 10 ? 'selected' : '' ?>>10</option>
                                <option value="20" <?= (int)$perPage === 20 ? 'selected' : '' ?>>20</option>
                                <option value="30" <?= (int)$perPage === 30 ? 'selected' : '' ?>>30</option>
                            </select>
                        </form>
                        <span>por página</span>
                    </div>
                </div>

                <div class="ticket-pagination-summary">
                    <span>
                        Mostrando <strong><?= (int)$showingFrom ?></strong> - <strong><?= (int)$showingTo ?></strong>
                        de <strong><?= (int)$totalTickets ?></strong> tickets
                    </span>

                    <?php if ((int)$totalTickets > (int)$perPage): ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($tickets)): ?>
                    <div class="tickets-table-wrapper">
                        <table class="tickets-table admin-tickets-table admin-tickets-table-wide">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Cliente</th>
                                    <th>Asunto</th>
                                    <th>Estado</th>
                                    <th>Prioridad</th>
                                    <th>Categoría</th>
                                    <th>Asignado</th>
                                    <th>SLA</th>
                                    <th>TTA</th>
                                    <th>TTR</th>
                                    <th>SLA cumplido</th>
                                    <th>Gestión</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <?php $assignedTechLevel = getAssignedTechLevelForTicket($ticket, $techLevelById, $techLevelByName); ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-code-pill">#<?= (int)$ticket['id'] ?></span>
                                        </td>

                                        <td><?= htmlspecialchars($ticket['requester_name']) ?></td>

                                        <td>
                                            <div class="ticket-subject-cell">
                                                <strong><?= htmlspecialchars($ticket['subject']) ?></strong>
                                                <p><?= htmlspecialchars(mb_strimwidth($ticket['description'], 0, 90, '...')) ?></p>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="table-badge status-badge">
                                                <?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $ticket['status'])))) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="table-badge priority-badge">
                                                <?= htmlspecialchars($ticket['priority']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="table-badge category-badge">
                                                <?= htmlspecialchars($ticket['category']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= !empty($ticket['assigned_name']) ? htmlspecialchars($ticket['assigned_name']) : 'Sin asignar' ?>
                                        </td>

                                        <td>
                                            <span class="metric-pill">
                                                <?= (int)$ticket['sla_hours'] ?> h
                                            </span>
                                        </td>

                                        <td>
                                            <?php
                                            $firstResponseAt = $ticket['level_first_response_at']
                                                ?? $ticket['first_response_at']
                                                ?? null;

                                            $ttaLabel = formatBusinessTimeStatus(
                                                $ticket['created_at'] ?? null,
                                                $firstResponseAt,
                                                empty($firstResponseAt)
                                            );

                                            $ttaClass = match ($ttaLabel) {
                                                'Pendiente', 'Fuera de horario' => 'pending-pill',
                                                default => 'neutral-pill',
                                            };
                                            ?>

                                            <span class="metric-pill <?= $ttaClass ?>">
                                                <?= htmlspecialchars($ttaLabel) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php
                                            $closedAt = $ticket['closed_at'] ?? null;

                                            if (empty($closedAt) && ($ticket['status'] ?? '') === 'CERRADO') {
                                                $closedAt = $ticket['updated_at'] ?? null;
                                            }

                                            $ttrLabel = formatBusinessTimeStatus(
                                                $ticket['created_at'] ?? null,
                                                $closedAt,
                                                ($ticket['status'] ?? '') !== 'CERRADO'
                                            );

                                            $ttrClass = match ($ttrLabel) {
                                                'Pendiente', 'Fuera de horario' => 'pending-pill',
                                                default => 'neutral-pill',
                                            };
                                            ?>

                                            <span class="metric-pill <?= $ttrClass ?>">
                                                <?= htmlspecialchars($ttrLabel) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="metric-pill <?= getSlaStatusClass($ticket) ?>">
                                                <?= htmlspecialchars(getSlaStatusLabel($ticket)) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if (($ticket['status'] ?? '') !== 'CERRADO'): ?>

                                                <div class="admin-ticket-actions">

                                                    <form action="/helpdesk-php/update-ticket-admin.php" method="POST" class="admin-ticket-inline-form">
                                                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                                                        <input type="hidden" name="assigned_to" value="<?= htmlspecialchars($ticket['assigned_to'] ?? '') ?>">

                                                        <select name="status" required>
                                                            <option value="ABIERTO" <?= $ticket['status'] === 'ABIERTO' ? 'selected' : '' ?>>Abierto</option>
                                                            <option value="EN_PROCESO" <?= $ticket['status'] === 'EN_PROCESO' ? 'selected' : '' ?>>En proceso</option>
                                                            <option value="RESPONDIDO" <?= $ticket['status'] === 'RESPONDIDO' ? 'selected' : '' ?>>Respondido</option>
                                                            <option value="CERRADO" <?= $ticket['status'] === 'CERRADO' ? 'selected' : '' ?>>Cerrado</option>
                                                        </select>

                                                        <button type="submit" class="btn-primary small-btn">Guardar</button>
                                                    </form>

                                                    <button
                                                        type="button"
                                                        class="btn-secondary small-btn admin-assign-tech-btn"
                                                        data-ticket-id="<?= (int)$ticket['id'] ?>"
                                                        data-assigned-to="<?= (int)($ticket['assigned_to'] ?? 0) ?>"
                                                        data-assigned-level="<?= (int)$assignedTechLevel ?>"
                                                        onclick="openAssignModal(<?= (int)$ticket['id'] ?>, <?= (int)($ticket['assigned_to'] ?? 0) ?>, <?= (int)$assignedTechLevel ?>)">
                                                        Asignar técnico
                                                    </button>

                                                </div>

                                            <?php else: ?>

                                                <span class="metric-pill neutral-pill admin-readonly-pill">Solo lectura</span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <a href="/helpdesk-php/ticket-detail.php?id=<?= (int)$ticket['id'] ?>" class="ticket-link-btn">
                                                Detalle
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ((int)$totalPages > 1): ?>
                        <?php
                        $previousPage = max(1, (int)$currentPage - 1);
                        $nextPage = min((int)$totalPages, (int)$currentPage + 1);
                        $startPage = max(1, (int)$currentPage - 2);
                        $endPage = min((int)$totalPages, (int)$currentPage + 2);
                        ?>

                        <nav class="tickets-pagination" aria-label="Paginación de tickets">
                            <a
                                class="tickets-page-btn <?= (int)$currentPage <= 1 ? 'disabled' : '' ?>"
                                href="<?= (int)$currentPage <= 1 ? '#' : htmlspecialchars(buildTicketPaginationUrl(['page' => $previousPage, 'per_page' => $perPage])) ?>">
                                ‹ Anterior
                            </a>

                            <div class="tickets-page-numbers">
                                <?php if ($startPage > 1): ?>
                                    <a class="tickets-page-number" href="<?= htmlspecialchars(buildTicketPaginationUrl(['page' => 1, 'per_page' => $perPage])) ?>">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="tickets-page-dots">…</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                                    <a
                                        class="tickets-page-number <?= (int)$pageNumber === (int)$currentPage ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars(buildTicketPaginationUrl(['page' => $pageNumber, 'per_page' => $perPage])) ?>">
                                        <?= (int)$pageNumber ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($endPage < (int)$totalPages): ?>
                                    <?php if ($endPage < (int)$totalPages - 1): ?>
                                        <span class="tickets-page-dots">…</span>
                                    <?php endif; ?>
                                    <a class="tickets-page-number" href="<?= htmlspecialchars(buildTicketPaginationUrl(['page' => $totalPages, 'per_page' => $perPage])) ?>"><?= (int)$totalPages ?></a>
                                <?php endif; ?>
                            </div>

                            <a
                                class="tickets-page-btn <?= (int)$currentPage >= (int)$totalPages ? 'disabled' : '' ?>"
                                href="<?= (int)$currentPage >= (int)$totalPages ? '#' : htmlspecialchars(buildTicketPaginationUrl(['page' => $nextPage, 'per_page' => $perPage])) ?>">
                                Siguiente ›
                            </a>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-ticket-box">
                        <h4>No se encontraron tickets</h4>
                        <p>No hay registros que coincidan con los filtros aplicados.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<div class="modal-overlay" id="assignTechModal">
    <div class="custom-modal custom-modal-lg">
        <div class="custom-modal-header">
            <h3>Asignar técnico</h3>
            <button type="button" class="modal-close-btn" onclick="closeAssignModal()">×</button>
        </div>

        <div class="custom-modal-body">
            <input type="hidden" id="assignTicketId">

            <div class="tech-level-tabs">
                <button type="button" class="tech-level-btn active" data-level="all" onclick="filterTechLevel('all', this)">Todos</button>
                <button type="button" class="tech-level-btn" data-level="1" onclick="filterTechLevel('1', this)">Nivel 1</button>
                <button type="button" class="tech-level-btn" data-level="2" onclick="filterTechLevel('2', this)">Nivel 2</button>
                <button type="button" class="tech-level-btn" data-level="3" onclick="filterTechLevel('3', this)">Nivel 3</button>
            </div>

            <div class="tech-list">
                <?php foreach ($techUsers as $tech): ?>
                    <form
                        action="/helpdesk-php/update-ticket-admin.php"
                        method="POST"
                        class="tech-card"
                        data-tech-id="<?= (int)$tech['id'] ?>"
                        data-level="<?= (int)($tech['tech_level'] ?? 1) ?>">
                        <input type="hidden" name="ticket_id" class="modal-ticket-id">
                        <input type="hidden" name="assigned_to" value="<?= (int)$tech['id'] ?>">
                        <input type="hidden" name="status" value="EN_PROCESO">

                        <div class="tech-avatar">
                            <?= strtoupper(substr($tech['name'], 0, 1)) ?>
                        </div>

                        <div class="tech-info">
                            <div class="tech-name-row">
                                <strong><?= htmlspecialchars($tech['name']) ?></strong>
                                <span class="tech-level-badge">
                                    Nivel <?= (int)($tech['tech_level'] ?? 1) ?>
                                </span>
                            </div>

                            <p>
                                Tickets activos:
                                <strong class="tech-load-number"><?= (int)($tech['active_tickets'] ?? 0) ?></strong>
                            </p>
                        </div>

                        <button
                            type="submit"
                            class="btn-primary small-btn tech-assign-btn"
                            data-tech-id="<?= (int)$tech['id'] ?>"
                            data-default-text="Asignar">
                            Asignar
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<script>
(function () {
    let currentAssignedLevel = 0;
    let currentAssignedTo = 0;

    const lockMessages = {
        scale: 'No puedes escalar a este nivel. Primero asigna a un técnico del nivel intermedio.',
        descale: 'No puedes desescalar a un técnico inferior.'
    };

    function parseNumber(value) {
        const number = Number.parseInt(value, 10);
        return Number.isNaN(number) ? 0 : number;
    }

    function getTechCardById(techId) {
        if (!techId) {
            return null;
        }

        return document.querySelector('#assignTechModal .tech-card[data-tech-id="' + techId + '"]');
    }

    function inferAssignedLevelFromCard() {
        if (currentAssignedLevel > 0 || currentAssignedTo <= 0) {
            return;
        }

        const currentTechCard = getTechCardById(currentAssignedTo);

        if (currentTechCard) {
            currentAssignedLevel = parseNumber(currentTechCard.dataset.level);
        }
    }

    function getLockReason(level) {
        const numericLevel = parseNumber(level);

        if (currentAssignedLevel <= 0 || numericLevel <= 0) {
            return '';
        }

        if (numericLevel < currentAssignedLevel) {
            return lockMessages.descale;
        }

        if (numericLevel > currentAssignedLevel + 1) {
            return lockMessages.scale;
        }

        return '';
    }

    function setActiveTab(button) {
        document.querySelectorAll('#assignTechModal .tech-level-btn').forEach((item) => {
            item.classList.remove('active');
        });

        if (button) {
            button.classList.add('active');
        }
    }

    function filterCardsByLevel(level, button) {
        if (button && button.classList.contains('is-locked')) {
            return;
        }

        setActiveTab(button);

        document.querySelectorAll('#assignTechModal .tech-card').forEach((card) => {
            const shouldShow = level === 'all' || card.dataset.level === String(level);
            card.classList.toggle('is-hidden-by-level', !shouldShow);
        });
    }

    function resetToAllTab() {
        const allButton = document.querySelector('#assignTechModal .tech-level-btn[data-level="all"]');
        filterCardsByLevel('all', allButton);
    }

    function applyTechLevelLocks() {
        inferAssignedLevelFromCard();

        document.querySelectorAll('#assignTechModal .tech-level-btn').forEach((button) => {
            const level = button.dataset.level || 'all';

            if (level === 'all') {
                button.classList.remove('is-locked');
                button.removeAttribute('aria-disabled');
                button.removeAttribute('data-tooltip');
                return;
            }

            const lockReason = getLockReason(level);
            const isLocked = lockReason !== '';

            button.classList.toggle('is-locked', isLocked);
            button.setAttribute('aria-disabled', isLocked ? 'true' : 'false');

            if (isLocked) {
                button.setAttribute('data-tooltip', lockReason);
            } else {
                button.removeAttribute('data-tooltip');
            }
        });

        document.querySelectorAll('#assignTechModal .tech-card').forEach((card) => {
            const techLevel = parseNumber(card.dataset.level);
            const techId = parseNumber(card.dataset.techId);
            const assignButton = card.querySelector('.tech-assign-btn');
            const lockReason = getLockReason(techLevel);
            const isLocked = lockReason !== '';
            const isCurrentTech = currentAssignedTo > 0 && techId === currentAssignedTo;

            card.classList.toggle('is-locked', isLocked);
            card.classList.toggle('is-current-tech', isCurrentTech);

            if (isLocked) {
                card.setAttribute('data-tooltip', lockReason);
            } else if (isCurrentTech) {
                card.setAttribute('data-tooltip', 'Técnico asignado actualmente.');
            } else {
                card.removeAttribute('data-tooltip');
            }

            if (!assignButton) {
                return;
            }

            if (isLocked) {
                assignButton.disabled = true;
                assignButton.textContent = 'Bloqueado';
                assignButton.setAttribute('data-tooltip', lockReason);
            } else if (isCurrentTech) {
                assignButton.disabled = true;
                assignButton.textContent = 'Asignado';
                assignButton.setAttribute('data-tooltip', 'Técnico asignado actualmente.');
            } else {
                assignButton.disabled = false;
                assignButton.textContent = assignButton.dataset.defaultText || 'Asignar';
                assignButton.removeAttribute('data-tooltip');
            }
        });
    }

    function openAssignModalSafe(ticketId, assignedTo, assignedLevel) {
        const modal = document.getElementById('assignTechModal');
        const ticketIdInput = document.getElementById('assignTicketId');

        currentAssignedTo = parseNumber(assignedTo);
        currentAssignedLevel = parseNumber(assignedLevel);

        if (ticketIdInput) {
            ticketIdInput.value = ticketId;
        }

        document.querySelectorAll('#assignTechModal .modal-ticket-id').forEach((input) => {
            input.value = ticketId;
        });

        applyTechLevelLocks();
        resetToAllTab();

        if (modal) {
            modal.classList.add('active', 'show', 'is-open');
            modal.style.display = 'flex';
        }

        document.body.classList.add('modal-open');
    }

    function closeAssignModalSafe() {
        const modal = document.getElementById('assignTechModal');

        if (modal) {
            modal.classList.remove('active', 'show', 'is-open');
            modal.style.display = 'none';
        }

        document.body.classList.remove('modal-open');
    }

    document.addEventListener('click', function (event) {
        const assignModalButton = event.target.closest('.admin-assign-tech-btn');

        if (assignModalButton) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            openAssignModalSafe(
                assignModalButton.dataset.ticketId,
                assignModalButton.dataset.assignedTo,
                assignModalButton.dataset.assignedLevel
            );
            return;
        }

        const levelButton = event.target.closest('#assignTechModal .tech-level-btn');

        if (levelButton) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            applyTechLevelLocks();

            if (levelButton.classList.contains('is-locked')) {
                return;
            }

            filterCardsByLevel(levelButton.dataset.level || 'all', levelButton);
            return;
        }

        const closeButton = event.target.closest('#assignTechModal .modal-close-btn');

        if (closeButton) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            closeAssignModalSafe();
        }
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('#assignTechModal .tech-card');

        if (!form) {
            return;
        }

        applyTechLevelLocks();

        if (form.classList.contains('is-locked') || form.classList.contains('is-current-tech')) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        }
    }, true);

    window.openAssignModal = openAssignModalSafe;
    window.closeAssignModal = closeAssignModalSafe;
    window.filterTechLevel = filterCardsByLevel;
})();
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>