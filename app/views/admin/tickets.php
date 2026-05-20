<?php
function prettyStatus($status)
{
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
$techUsers = $techUsers ?? [];

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

                <form action="/helpdesk-php/admin-tickets.php" method="GET" class="ticket-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">Buscar</label>
                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Asunto, descripción o cliente">
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
                    </div>

                    <div class="ticket-form-actions">
                        <a href="/helpdesk-php/admin-tickets.php" class="btn-secondary">Limpiar</a>
                        <button type="submit" class="btn-primary">Filtrar</button>
                    </div>
                </form>
            </section>

            <section class="card my-tickets-card">
                <div class="my-tickets-header">
                    <h2>Listado general de tickets</h2>
                    <p>Incluye tiempos de respuesta, resolución y cumplimiento de SLA por ticket.</p>
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
                                            <?php if ($ticket['tta_hours'] !== null): ?>
                                                <span class="metric-pill neutral-pill"><?= formatDecimalHoursToClock(calculateBusinessHours($ticket['created_at'], $ticket['first_response_at'])) ?></span>
                                            <?php else: ?>
                                                <span class="metric-pill pending-pill">Pendiente</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($ticket['ttr_hours'] !== null): ?>
                                                <span class="metric-pill neutral-pill"><?= formatDecimalHoursToClock(calculateBusinessHours($ticket['created_at'], $ticket['closed_at'])) ?></span>
                                            <?php else: ?>
                                                <span class="metric-pill pending-pill">Pendiente</span>
                                            <?php endif; ?>
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
                                                        onclick="openAssignModal(<?= (int)$ticket['id'] ?>)">
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
                <button type="button" class="tech-level-btn active" onclick="filterTechLevel('all', this)">Todos</button>
                <button type="button" class="tech-level-btn" onclick="filterTechLevel('1', this)">Nivel 1</button>
                <button type="button" class="tech-level-btn" onclick="filterTechLevel('2', this)">Nivel 2</button>
                <button type="button" class="tech-level-btn" onclick="filterTechLevel('3', this)">Nivel 3</button>
            </div>

            <div class="tech-list">
                <?php foreach ($techUsers as $tech): ?>
                    <form
                        action="/helpdesk-php/update-ticket-admin.php"
                        method="POST"
                        class="tech-card"
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

                            <p>Técnico disponible para asignación de incidencias.</p>
                        </div>

                        <button type="submit" class="btn-primary small-btn tech-assign-btn">
                            Asignar
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>