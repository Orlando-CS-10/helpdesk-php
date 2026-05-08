<?php
require_once __DIR__ . '/../../helpers/session.php';

$title = 'Mis Tickets';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="panel">
    <div class="topbar">
        <h1>Mis Tickets</h1>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="/helpdesk-php/home.php" class="btn-secondary">Ir al inicio</a>
            <a href="/helpdesk-php/app/views/client/create-ticket.php" class="btn-primary">Crear Ticket</a>
            <a href="/helpdesk-php/logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="card my-tickets-card">
        <div class="my-tickets-header">
            <h2>Listado de tickets registrados</h2>
            <p>
                Aquí puedes consultar todos los tickets que has creado, su estado actual
                y acceder al detalle de cada uno.
            </p>
        </div>

        <?php if (!empty($tickets)): ?>
            <div class="tickets-table-wrapper">
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Asunto</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Categoría</th>
                            <th>Creado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>
                                    <span class="ticket-code-pill">
                                        #<?= (int)$ticket['id'] ?>
                                    </span>
                                </td>

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
                                    <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                </td>

                                <td>
                                    <a href="/helpdesk-php/ticket-detail.php?id=<?= (int)$ticket['id'] ?>" class="ticket-link-btn">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-ticket-box">
                <h4>No tienes tickets registrados</h4>
                <p>
                    Aún no has creado ninguna incidencia. Cuando registres tu primer ticket,
                    aparecerá aquí con su información y estado.
                </p>

                <div style="margin-top:16px;">
                    <a href="/helpdesk-php/app/views/client/create-ticket.php" class="btn-primary">Crear Ticket</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>