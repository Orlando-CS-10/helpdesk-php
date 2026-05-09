<!doctype html>

<?php
require_once __DIR__ . '/../../helpers/session.php';

$notifications = [];
$unreadNotifications = 0;

if (isLoggedIn()) {
    require_once __DIR__ . '/../../config/database.php';

    $currentUser = user();

    $sqlNotifications = "SELECT id, title, message, type, is_read, related_ticket_id, created_at
                         FROM notifications
                         WHERE user_id = :user_id
                         ORDER BY created_at DESC
                         LIMIT 8";

    $stmtNotifications = $pdo->prepare($sqlNotifications);
    $stmtNotifications->execute([
        'user_id' => $currentUser['id']
    ]);

    $notifications = $stmtNotifications->fetchAll(PDO::FETCH_ASSOC);

    $sqlUnread = "SELECT COUNT(*) AS total
                  FROM notifications
                  WHERE user_id = :user_id
                    AND is_read = 0";

    $stmtUnread = $pdo->prepare($sqlUnread);
    $stmtUnread->execute([
        'user_id' => $currentUser['id']
    ]);

    $unreadNotifications = (int)($stmtUnread->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}
?>

<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Mesa de Ayuda'; ?></title>
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/base.css?v=1">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/layout.css">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/admin.css">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/tables.css">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/tickets.css?v=1">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/dashboard.css">
    <link rel="stylesheet" href="/helpdesk-php/public/assets/css/responsive.css">
    <script src="https://kit.fontawesome.com/b44fd2b2de.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    $toastMessage = '';
    $toastType = '';

    if (!empty($_SESSION['ticket_success'])) {
        $toastMessage = $_SESSION['ticket_success'];
        $toastType = 'success';
        unset($_SESSION['ticket_success']);
    } elseif (!empty($_SESSION['ticket_error'])) {
        $toastMessage = $_SESSION['ticket_error'];
        $toastType = 'error';
        unset($_SESSION['ticket_error']);
    } elseif (!empty($_SESSION['settings_success'])) {
        $toastMessage = $_SESSION['settings_success'];
        $toastType = 'success';
        unset($_SESSION['settings_success']);
    } elseif (!empty($_SESSION['settings_error'])) {
        $toastMessage = $_SESSION['settings_error'];
        $toastType = 'error';
        unset($_SESSION['settings_error']);
    }
    ?>

    <?php if (!empty($toastMessage)): ?>
        <div class="toast-container">
            <div class="toast toast-<?= htmlspecialchars($toastType) ?>" id="appToast">
                <div class="toast-content">
                    <strong class="toast-title">
                        <?= $toastType === 'success' ? 'Correcto' : 'Atención' ?>
                    </strong>
                    <p class="toast-message"><?= htmlspecialchars($toastMessage) ?></p>
                </div>

                <button class="toast-close" onclick="closeToast()">×</button>
            </div>
        </div>

        <script>
            function closeToast() {
                const toast = document.getElementById('appToast');
                if (toast) {
                    toast.classList.add('toast-hide');
                    setTimeout(() => toast.remove(), 300);
                }
            }

            setTimeout(() => {
                closeToast();
            }, 4000);
        </script>
    <?php endif; ?>