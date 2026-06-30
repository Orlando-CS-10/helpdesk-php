<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_maintenance.php';

$maintenance = getSystemMaintenanceSettings($pdo);
$currentUser = user();

if (empty($maintenance['is_enabled'])) {
    header('Location: /helpdesk-php/index.php');
    exit;
}

if ($currentUser && strtoupper((string) ($currentUser['role'] ?? '')) === 'ADMIN' && !empty($maintenance['allow_admin'])) {
    header('Location: /helpdesk-php/admin-system-tools.php');
    exit;
}

$title = 'Sistema en mantenimiento';
$useAuthLayout = true;
require_once __DIR__ . '/app/views/layouts/header.php';
?>
<link rel="stylesheet" href="/helpdesk-php/public/assets/css/admin-system-tools.css?v=20260626-system-tools-1">
<main class="maintenance-public-page">
    <section class="maintenance-public-card">
        <div class="maintenance-public-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <span>Mantenimiento programado</span>
        <h1>Volvemos en cuanto terminemos los ajustes</h1>
        <p><?= htmlspecialchars((string) ($maintenance['message'] ?? 'El sistema se encuentra temporalmente en mantenimiento.'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (!empty($maintenance['estimated_return_at'])): ?>
            <div class="maintenance-public-time">
                <i class="fa-regular fa-clock"></i>
                Regreso estimado: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $maintenance['estimated_return_at'])), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <div class="maintenance-public-actions">
            <a class="btn-primary" href="/helpdesk-php/index.php">Comprobar disponibilidad</a>
            <a class="btn-secondary" href="/helpdesk-php/login.php">Acceso administrativo</a>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/app/views/layouts/footer.php'; ?>
