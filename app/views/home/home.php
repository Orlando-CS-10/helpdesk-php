<?php
// Título de la página que se mostrará en la pestaña del navegador
$title = 'Inicio - Mesa de Ayuda';

// Incluye el encabezado general del sitio
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../../helpers/session.php';

$unreadNotifications = $unreadNotifications ?? 0;
$notifications = $notifications ?? [];
?>

<!-- ============================= -->
<!-- HEADER PRINCIPAL DEL SITIO -->
<!-- ============================= -->
<header class="site-header">
    <div class="container header-inner">

        <div class="brand-box">
            <img src="public/assets/img/logo.png" alt="Logo de la empresa" class="logo">

            <div>
                <h2 class="brand-title">Mesa de Ayuda</h2>
                <p class="brand-subtitle">
                    Soporte técnico inteligente para una atención más rápida y ordenada
                </p>
            </div>
        </div>

        <?php if (isLoggedIn()): ?>
            <div class="header-user-tools">

                <div class="notification-menu">
                    <button
                        class="notification-trigger"
                        type="button"
                        onclick="toggleNotificationMenu()"
                        aria-label="Abrir notificaciones">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1l-2-2z" />
                        </svg>

                        <?php if ((int)$unreadNotifications > 0): ?>
                            <span class="notification-badge"><?= (int)$unreadNotifications ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-dropdown-header">
                            <div class="notification-title-block">
                                <h3>Notificaciones</h3>
                                <p>Últimos movimientos de tus tickets.</p>
                            </div>

                            <span class="notification-unread-chip">
                                <?= (int)$unreadNotifications ?> sin leer
                            </span>
                        </div>

                        <div class="notification-dropdown-actions">
                            <form action="/helpdesk-php/mark-all-notifications-read.php" method="POST">
                                <button type="submit" class="notification-action-btn">
                                    Marcar todo como leído
                                </button>
                            </form>

                            <form
                                action="/helpdesk-php/delete-read-notifications.php"
                                method="POST"
                                onsubmit="return confirm('¿Eliminar las notificaciones leídas?');">
                                <button type="submit" class="notification-action-btn danger">
                                    Eliminar leídas
                                </button>
                            </form>
                        </div>

                        <div class="notification-dropdown-list">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $isUnread = (int)($notification['is_read'] ?? 0) === 0;
                                    $notificationUrl = !empty($notification['related_ticket_id'])
                                        ? '/helpdesk-php/ticket-detail.php?id=' . (int)$notification['related_ticket_id']
                                        : '#';
                                    ?>
                                    <a
                                        class="notification-item <?= $isUnread ? 'unread' : '' ?>"
                                        href="<?= htmlspecialchars($notificationUrl) ?>">
                                        <span class="notification-state-dot <?= $isUnread ? 'active' : '' ?>"></span>

                                        <div class="notification-item-body">
                                            <div class="notification-item-top">
                                                <strong><?= htmlspecialchars($notification['title'] ?? 'Notificación') ?></strong>
                                                <span><?= htmlspecialchars(date('d/m H:i', strtotime($notification['created_at'] ?? 'now'))) ?></span>
                                            </div>

                                            <p><?= htmlspecialchars($notification['message'] ?? '') ?></p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notification-empty">
                                    <strong>Sin notificaciones</strong>
                                    <p>Cuando ocurra algo importante en tus tickets, aparecerá aquí.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="user-menu">
                    <button class="user-menu-trigger" type="button" onclick="toggleUserMenu()">
                        <div class="user-avatar">
                            <?php
                            $username = user()['name'] ?? 'Usuario';
                            echo strtoupper(substr($username, 0, 1));
                            ?>
                        </div>

                        <div class="user-meta">
                            <span class="user-status">
                                <?php
                                $role = user()['role'] ?? '';

                                if ($role === 'ADMIN') {
                                    echo 'Administrador activo';
                                } elseif ($role === 'TECH') {
                                    echo 'Técnico activo';
                                } else {
                                    echo 'Sesión activa';
                                }
                                ?>
                            </span>
                            <strong class="user-name"><?= htmlspecialchars(user()['name']) ?></strong>
                        </div>
                    </button>

                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-dropdown-header">
                            <div class="user-avatar large">
                                <?php
                                $username = user()['name'] ?? 'Usuario';
                                echo strtoupper(substr($username, 0, 1));
                                ?>
                            </div>

                            <div>
                                <div class="dropdown-name"><?= htmlspecialchars(user()['name']) ?></div>
                                <div class="dropdown-role">
                                    <?php
                                    $role = user()['role'] ?? '';

                                    if ($role === 'ADMIN') {
                                        echo 'Administrador';
                                    } elseif ($role === 'TECH') {
                                        echo 'Técnico';
                                    } else {
                                        echo 'Cliente';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="user-dropdown-links">
                            <?php if ((user()['role'] ?? '') === 'CLIENT'): ?>
                                <a href="/helpdesk-php/my-tickets.php">Mis tickets</a>
                                <a href="#">Contactos</a>
                                <a href="/helpdesk-php/settings.php">Ajustes</a>

                            <?php elseif ((user()['role'] ?? '') === 'ADMIN'): ?>
                                <a href="/helpdesk-php/index.php">Panel de control</a>
                                <a href="/helpdesk-php/admin-tickets.php">Gestión de tickets</a>
                                <a href="/helpdesk-php/admin-users.php">Usuarios</a>
                                <a href="#">Dashboard</a>
                                <a href="/helpdesk-php/settings.php">Ajustes</a>

                            <?php elseif ((user()['role'] ?? '') === 'TECH'): ?>
                                <a href="#">Panel técnico</a>
                                <a href="#">Tickets asignados</a>
                                <a href="#">Pendientes</a>
                                <a href="/helpdesk-php/settings.php">Ajustes</a>
                            <?php endif; ?>

                            <a href="/helpdesk-php/logout.php" class="danger-link">Cerrar sesión</a>
                        </div>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <nav class="header-actions">
                <a href="login.php" class="btn-secondary">Iniciar sesión</a>
                <a href="#" class="btn-primary">Crear cuenta</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<script>
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }

    document.addEventListener('click', function(event) {
        const menu = document.querySelector('.user-menu');
        const dropdown = document.getElementById('userDropdown');

        if (!menu.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
</script>

<!-- ============================= -->
<!-- SECCIÓN HERO / BIENVENIDA -->
<!-- ============================= -->
<section class="hero">
    <div class="container hero-grid">

        <!-- Columna izquierda: mensaje principal -->
        <div class="hero-text">
            <span class="badge">Sistema web de soporte técnico</span>

            <h1>Bienvenido a la Mesa de Ayuda</h1>

            <p>
                Centraliza tus solicitudes, registra incidencias, consulta el estado de tus tickets
                y mejora la atención de soporte con una plataforma más organizada, trazable y eficiente.
            </p>

            <!-- ============================= -->
            <!-- BLOQUE DINÁMICO (TICKET / LOGIN) -->
            <!-- ============================= -->
            <div class="quick-action-box">

                <?php if (isLoggedIn()): ?>

                    <?php
                    // Conexión a BD
                    require_once __DIR__ . '/../../config/database.php';

                    // Usuario actual
                    $currentUser = user();
                    $userId = $currentUser['id'];

                    // Buscar tickets del cliente
                    $sql = "SELECT id, subject, description, status, priority, created_at
                FROM tickets
                WHERE requester_id = :user_id
                ORDER BY created_at DESC
                LIMIT 5";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['user_id' => $userId]);
                    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="quick-action-header">
                        <div>
                            <h3>Mis tickets recientes</h3>
                            <p>
                                Aquí puedes visualizar las últimas incidencias registradas y su estado actual.
                            </p>
                        </div>

                        <div>
                            <a href="/helpdesk-php/app/views/client/create-ticket.php" class="btn-primary">Crear Ticket</a>
                        </div>
                    </div>

                    <?php if (!empty($tickets)): ?>

                        <div class="ticket-list">
                            <?php foreach ($tickets as $ticket): ?>
                                <div class="ticket-card">
                                    <div class="ticket-card-top">
                                        <div>
                                            <h4><?= htmlspecialchars($ticket['subject']) ?></h4>
                                            <span class="ticket-date">
                                                Creado: <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                            </span>
                                        </div>

                                        <div class="ticket-badges">
                                            <span class="ticket-status status-<?= strtolower($ticket['status']) ?>">
                                                <?= htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $ticket['status'])))) ?>
                                            </span>

                                            <span class="ticket-priority priority-<?= strtolower($ticket['priority']) ?>">
                                                <?= htmlspecialchars($ticket['priority']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <p class="ticket-description">
                                        <?= htmlspecialchars($ticket['description']) ?>
                                    </p>

                                    <div class="ticket-card-footer">
                                        <span class="ticket-code">Ticket #<?= $ticket['id'] ?></span>
                                        <a href="/helpdesk-php/ticket-detail.php?id=<?= (int)$ticket['id'] ?>" class="ticket-link">Ver detalle</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>

                        <div class="empty-ticket-box">
                            <h4>No hay ningún ticket registrado</h4>
                            <p>
                                Aún no has creado incidencias en el sistema. Cuando registres tu primer ticket,
                                aparecerá aquí con su información y estado.
                            </p>
                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <div class="quick-action-content">
                        <h3>Acceso requerido</h3>
                        <p>
                            Para registrar incidencias y ver tus tickets, necesitas iniciar sesión
                            o crear una cuenta.
                        </p>

                        <div class="quick-actions-buttons">
                            <a href="login.php" class="btn-primary">Iniciar sesión</a>
                            <a href="#" class="btn-secondary">Crear cuenta</a>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <!-- Columna derecha: funciones principales -->
        <div class="hero-card">
            <div class="hero-card-header">
                <span class="card-tag">Funciones principales</span>

                <h3>¿Qué podrás hacer aquí?</h3>

                <p>
                    La plataforma está diseñada para centralizar la atención de incidencias
                    y facilitar el seguimiento de cada solicitud de soporte.
                </p>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <div class="feature-icon">01</div>
                    <div class="feature-content">
                        <h4>Registrar tickets de soporte</h4>
                        <p>
                            Reporta incidencias de forma rápida y ordenada, dejando constancia
                            de cada solicitud desde el inicio.
                        </p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon">02</div>
                    <div class="feature-content">
                        <h4>Dar seguimiento a incidencias</h4>
                        <p>
                            Consulta el estado de tus tickets y revisa el avance de atención
                            sin depender de mensajes dispersos o llamadas informales.
                        </p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon">03</div>
                    <div class="feature-content">
                        <h4>Mejorar tiempos de respuesta y solución</h4>
                        <p>
                            La gestión centralizada permite responder con mayor rapidez y dar
                            una atención más eficiente a cada caso reportado.
                        </p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon">04</div>
                    <div class="feature-content">
                        <h4>Centralizar la atención en una sola plataforma</h4>
                        <p>
                            Toda la información se organiza en un único sistema, evitando
                            pérdida de datos y mejorando el control del servicio.
                        </p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon">05</div>
                    <div class="feature-content">
                        <h4>Contar con trazabilidad completa</h4>
                        <p>
                            Cada ticket conserva historial, cambios de estado y responsables,
                            permitiendo una gestión más clara y medible.
                        </p>
                    </div>
                </div>
            </div>

            <div class="hero-card-footer">
                <p>
                    Todo esto contribuye a una atención más ordenada, ágil y confiable para usuarios y técnicos.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================= -->
<!-- SECCIÓN DE BENEFICIOS -->
<!-- ============================= -->
<section class="info-section" id="beneficios">
    <div class="container">

        <div class="section-title">
            <h2>¿Por qué usar este sistema?</h2>
            <p>
                Diseñado para resolver los problemas comunes de la atención manual,
                la desorganización y la falta de seguimiento.
            </p>
        </div>

        <div class="cards-grid">
            <div class="info-card">
                <h3>Atención centralizada</h3>
                <p>
                    Todas las incidencias se registran en un solo lugar, evitando pérdidas de información
                    por llamadas, mensajes o correos dispersos.
                </p>
            </div>

            <div class="info-card">
                <h3>Trazabilidad completa</h3>
                <p>
                    Cada ticket cuenta con historial, estado, responsable y seguimiento,
                    permitiendo un mejor control del servicio.
                </p>
            </div>

            <div class="info-card">
                <h3>Mayor eficiencia</h3>
                <p>
                    El sistema ayuda a reducir tiempos de respuesta, mejorar la organización
                    y elevar la calidad de atención al usuario.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================= -->
<!-- SECCIÓN INFORMATIVA / PROPÓSITO -->
<!-- ============================= -->
<section class="about-section">
    <div class="container about-box">

        <div>
            <h2>Un sistema pensado para resolver problemáticas reales</h2>
            <p>
                Esta plataforma nace para mejorar la gestión de soporte, reducir la demora en la atención,
                disminuir la desorganización y ofrecer una experiencia más clara tanto para usuarios como para técnicos.
            </p>
        </div>

        <div>
            <h3>Objetivo principal</h3>
            <p>
                Brindar una solución web que permita gestionar incidencias de manera ordenada, ágil y medible.
            </p>
        </div>
    </div>
</section>

<!-- ============================= -->
<!-- PIE DE PÁGINA -->
<!-- ============================= -->
<footer class="site-footer">
    <div class="container footer-inner">
        <p>© 2026 Mesa de Ayuda. Todos los derechos reservados.</p>
        <p>Proyecto de sistema web de soporte técnico</p>
    </div>
</footer>

<script>
    function toggleUserMenu() {
        const userDropdown = document.getElementById('userDropdown');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (notificationDropdown) {
            notificationDropdown.classList.remove('show');
        }

        userDropdown.classList.toggle('show');
    }

    function toggleNotificationMenu() {
        const userDropdown = document.getElementById('userDropdown');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (userDropdown) {
            userDropdown.classList.remove('show');
        }

        if (notificationDropdown) {
            notificationDropdown.classList.toggle('show');
        }
    }

    document.addEventListener('click', function(event) {
        const userMenu = document.querySelector('.user-menu');
        const notificationMenu = document.querySelector('.notification-menu');

        const userDropdown = document.getElementById('userDropdown');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (userMenu && userDropdown && !userMenu.contains(event.target)) {
            userDropdown.classList.remove('show');
        }

        if (notificationMenu && notificationDropdown && !notificationMenu.contains(event.target)) {
            notificationDropdown.classList.remove('show');
        }
    });
</script>

<?php
// Incluye el cierre general del documento HTML
require_once __DIR__ . '/../layouts/footer.php';
?>