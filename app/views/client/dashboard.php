<?php
require_once __DIR__ . '/../../helpers/session.php';
requireRole('CLIENT');
$title = 'Panel Cliente';
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="panel">
    <div class="topbar">
        <h1>Panel del Cliente</h1>
            <div style="display:flex; gap:10px;">
            <a href="home.php" class="btn-secondary">Ir al inicio</a>
            <a class="btn-logout" href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <div class="card">
        <h2>Bienvenido, <?= htmlspecialchars(user()['name']) ?></h2>
        <p>Este será el panel del cliente. Aquí luego pondremos “Crear Ticket” y “Mis Tickets”.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>