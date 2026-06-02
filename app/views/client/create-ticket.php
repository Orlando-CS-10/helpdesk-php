<?php
require_once __DIR__ . '/../../helpers/session.php';
requireRole('CLIENT');

$title = 'Nueva solicitud de soporte';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="panel client-ticket-create-page">
    <div class="topbar client-ticket-topbar">
        <div>
            <span class="client-ticket-eyebrow">Mesa de ayuda</span>
            <h1>Nueva solicitud de soporte</h1>
            <p>Registra una incidencia para que el equipo técnico pueda revisarla y brindarte seguimiento.</p>
        </div>

        <div class="client-ticket-topbar-actions">
            <a href="/helpdesk-php/home.php" class="btn-secondary">Volver al panel</a>
            <a href="/helpdesk-php/logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="ticket-create-layout">
        <div class="card ticket-form-card">
            <div class="ticket-form-header">
                <span class="ticket-form-badge">Formulario de atención</span>
                <h2>Registrar incidencia</h2>
                <p>
                    Completa los datos principales del problema. Mientras más clara sea la descripción,
                    más rápido podrá identificarse la causa y asignarse la atención correspondiente.
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
                    <label for="subject">Asunto del problema</label>
                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        placeholder="Ejemplo: No puedo acceder al sistema"
                        maxlength="150"
                        required
                    >
                    <small class="form-help">Usa un título breve que resuma la incidencia.</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Prioridad</label>
                        <select id="priority" name="priority" required>
                            <option value="">Seleccione una prioridad</option>
                            <option value="BAJA">Baja - No impide trabajar</option>
                            <option value="MEDIA">Media - Afecta parcialmente</option>
                            <option value="ALTA">Alta - Servicio crítico afectado</option>
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

                <div class="priority-guide">
                    <div class="priority-guide-item priority-low">
                        <strong>Baja</strong>
                        <span>Consulta, ajuste menor o solicitud sin impacto crítico.</span>
                    </div>
                    <div class="priority-guide-item priority-medium">
                        <strong>Media</strong>
                        <span>El servicio funciona, pero presenta lentitud o fallas parciales.</span>
                    </div>
                    <div class="priority-guide-item priority-high">
                        <strong>Alta</strong>
                        <span>El servicio principal está caído o impide continuar la operación.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descripción detallada</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="7"
                        placeholder="Describe qué ocurrió, desde cuándo sucede, qué área o equipo está afectado y si aparece algún mensaje de error..."
                        required
                    ></textarea>
                    <small class="form-help">Puedes incluir hora aproximada, equipo afectado, área y pasos realizados antes del error.</small>
                </div>

                <div class="ticket-form-actions">
                    <a href="/helpdesk-php/home.php" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Crear solicitud</button>
                </div>
            </form>
        </div>

        <aside class="ticket-create-helper">
            <div class="ticket-helper-card ticket-helper-main">
                <div class="ticket-helper-icon">?</div>
                <h3>¿Cómo describir mejor tu incidencia?</h3>
                <p>
                    Indica el problema, el momento en que inició y cómo afecta tu trabajo.
                    Esto ayuda a que el equipo técnico identifique mejor el caso.
                </p>
            </div>

            <div class="ticket-helper-card">
                <h4>Incluye, si aplica:</h4>
                <ul>
                    <li>Área o equipo afectado.</li>
                    <li>Mensaje de error visible.</li>
                    <li>Hora aproximada del incidente.</li>
                    <li>Captura o evidencia, si luego se solicita.</li>
                </ul>
            </div>

        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
