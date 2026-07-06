<?php
require_once __DIR__ . '/../../helpers/session.php';
requireRole('CLIENT');

$title = 'Nueva solicitud de soporte';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="panel client-ticket-create-page">
    <div class="client-ticket-topbar">
        <div class="client-ticket-title-block">
            <span class="client-ticket-eyebrow">
                <span class="client-ticket-eyebrow-dot"></span>
                Mesa de ayuda
            </span>
            <h1>Nueva solicitud de soporte</h1>
            <p>Registra una incidencia para que el equipo técnico pueda revisarla y brindarte seguimiento.</p>
        </div>

        <div class="client-ticket-topbar-actions">
            <a href="/helpdesk-php/home.php" class="btn-secondary client-ticket-return-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Volver al panel</span>
            </a>
            <a href="/helpdesk-php/logout.php" class="btn-logout client-ticket-logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </div>

    <div class="ticket-create-layout ticket-create-layout-v2">
        <div class="card ticket-form-card ticket-form-card-v2">
            <div class="ticket-form-header ticket-form-header-v2">
                <span class="ticket-form-badge">
                    <i class="fa-regular fa-clipboard"></i>
                    Formulario de atención
                </span>
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

            <form action="/helpdesk-php/create-ticket.php" method="POST" class="ticket-form ticket-create-form-v2" id="clientTicketCreateForm">
                <div class="form-group ticket-create-field ticket-create-field-full">
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

                <div class="form-row ticket-create-select-row">
                    <div class="form-group ticket-create-field">
                        <label for="priority">Prioridad</label>
                        <select id="priority" name="priority" required>
                            <option value="">Seleccione una prioridad</option>
                            <option value="BAJA">Baja - No impide trabajar</option>
                            <option value="MEDIA">Media - Afecta parcialmente</option>
                            <option value="ALTA">Alta - Servicio crítico afectado</option>
                        </select>
                        <small class="form-help">Elige la prioridad según el impacto real del problema.</small>
                    </div>

                    <div class="form-group ticket-create-field">
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
                        <small class="form-help">Selecciona el tipo de incidencia para orientar mejor al equipo técnico.</small>
                    </div>
                </div>

                <div class="form-group ticket-create-field ticket-create-field-full">
                    <label for="description">Descripción detallada</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="6"
                        placeholder="Describe qué ocurrió, desde cuándo sucede, qué área o equipo está afectado y si aparece algún mensaje de error..."
                        required
                    ></textarea>
                    <small class="form-help">Puedes incluir hora aproximada, equipo afectado, área y pasos realizados antes del error.</small>
                </div>

                <div class="ticket-form-actions ticket-create-actions-v2">
                    <a href="/helpdesk-php/home.php" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary ticket-create-submit-btn">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Crear solicitud</span>
                    </button>
                </div>
            </form>
        </div>

        <aside class="ticket-create-helper ticket-create-helper-v2">
            <div class="ticket-helper-card ticket-helper-main ticket-helper-main-v2">
                <div class="ticket-helper-icon">
                    <i class="fa-solid fa-question"></i>
                </div>
                <h3>¿Cómo describir mejor tu incidencia?</h3>
                <p>
                    Indica el problema, el momento en que inició y cómo afecta tu trabajo.
                    Esto ayuda a que el equipo técnico identifique mejor el caso.
                </p>
            </div>

            <div class="ticket-helper-card ticket-helper-list-card">
                <h4>Incluye, si aplica:</h4>
                <ul>
                    <li>
                        <span><i class="fa-regular fa-user"></i></span>
                        <strong>Área o equipo afectado.</strong>
                    </li>
                    <li>
                        <span><i class="fa-regular fa-eye"></i></span>
                        <strong>Mensaje de error visible.</strong>
                    </li>
                    <li>
                        <span><i class="fa-regular fa-clock"></i></span>
                        <strong>Hora aproximada del incidente.</strong>
                    </li>
                    <li>
                        <span><i class="fa-solid fa-paperclip"></i></span>
                        <strong>Captura o evidencia, si luego se solicita.</strong>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</div>


<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
