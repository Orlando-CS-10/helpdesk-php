<?php
require_once __DIR__ . '/../../helpers/session.php';
requireRole('CLIENT');

$title = 'Crear Ticket';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="panel">
    <div class="topbar">
        <h1>Crear Ticket</h1>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="/helpdesk-php/home.php" class="btn-secondary">Ir al inicio</a>
            <a href="/helpdesk-php/logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="card ticket-form-card">
        <div class="ticket-form-header">
            <h2>Registrar nueva incidencia</h2>
            <p>
                Completa la información del problema para que el equipo de soporte
                pueda atender tu solicitud de manera ordenada y eficiente.
            </p>
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

        <form action="/helpdesk-php/create-ticket.php" method="POST" class="ticket-form">

            <div class="form-group">
                <label for="subject">Asunto</label>
                <input
                    type="text"
                    id="subject"
                    name="subject"
                    placeholder="Ejemplo: No puedo acceder al sistema"
                    required
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="priority">Prioridad</label>
                    <select id="priority" name="priority" required>
                        <option value="">Seleccione una prioridad</option>
                        <option value="BAJA">Baja</option>
                        <option value="MEDIA">Media</option>
                        <option value="ALTA">Alta</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Categoría</label>
                    <select id="category" name="category" required>
                        <option value="">Seleccione una categoría</option>
                        <option value="ACCESO">Acceso</option>
                        <option value="SISTEMA">Sistema</option>
                        <option value="HARDWARE">Hardware</option>
                        <option value="SOFTWARE">Software</option>
                        <option value="RED">Red</option>
                        <option value="OTROS">Otros</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Descripción</label>
                <textarea
                    id="description"
                    name="description"
                    rows="7"
                    placeholder="Describe con detalle el problema que presentas..."
                    required
                ></textarea>
            </div>

            <div class="ticket-form-actions">
                <a href="/helpdesk-php/home.php" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Crear Ticket</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>