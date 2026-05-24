<?php
require_once __DIR__ . '/../../helpers/session.php';

$messageData = $messageData ?? null;

if (empty($messageData) || empty($messageData['id'])) {
    $_SESSION['ticket_error'] = 'No se encontró información del mensaje.';
    header('Location: /helpdesk-php/home.php');
    exit;
}

$messageData['ticket_id'] = $messageData['ticket_id'] ?? 0;
$messageData['name'] = $messageData['name'] ?? 'Usuario';
$messageData['role'] = $messageData['role'] ?? '';
$messageData['created_at'] = $messageData['created_at'] ?? date('Y-m-d H:i:s');
$messageData['message'] = $messageData['message'] ?? '';

$title = 'Editar mensaje';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="panel">
    <div class="topbar">
        <h1>Editar mensaje</h1>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="/helpdesk-php/ticket-detail.php?id=<?= (int)$messageData['ticket_id'] ?>" class="btn-secondary">Volver al ticket</a>
            <a href="/helpdesk-php/logout.php" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <div class="card ticket-reply-card">
        <div class="ticket-section-title">
            <h3>Modificar respuesta</h3>
            <p>Puedes actualizar el contenido de este mensaje.</p>
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

        <div class="edit-message-meta">
            <div class="edit-message-chip">
                <strong>Autor:</strong> <?= htmlspecialchars($messageData['name']) ?>
            </div>
            <div class="edit-message-chip">
                <strong>Rol:</strong> <?= htmlspecialchars($messageData['role']) ?>
            </div>
            <div class="edit-message-chip">
                <strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($messageData['created_at'])) ?>
            </div>
        </div>

        <form action="/helpdesk-php/update-message.php" method="POST" class="ticket-form">
            <input type="hidden" name="message_id" value="<?= (int)$messageData['id'] ?>">
            <input type="hidden" name="ticket_id" value="<?= (int)$messageData['ticket_id'] ?>">

            <div class="form-group">
                <label for="message">Mensaje</label>
                <textarea
                    id="message"
                    name="message"
                    rows="8"
                    required
                ><?= htmlspecialchars($messageData['message']) ?></textarea>
            </div>

            <div class="ticket-form-actions">
                <a href="/helpdesk-php/ticket-detail.php?id=<?= (int)$messageData['ticket_id'] ?>" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>