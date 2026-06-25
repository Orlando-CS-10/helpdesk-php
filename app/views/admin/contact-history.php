<?php
$title = 'Historial del contacto';
$activePage = 'clients';

$companyName = trim((string) ($contact['company_trade_name'] ?? ''));
if ($companyName === '') {
    $companyName = trim((string) ($contact['company_business_name'] ?? 'Empresa sin vincular'));
}

$pageTitle = 'Historial de ' . (string) ($contact['name'] ?? 'Contacto');
$pageSubtitle = 'Consulta los tickets y la trazabilidad de atención de este contacto por separado.';

$companyContactsUrl = !empty($contact['company_id'])
    ? '/helpdesk-php/admin-company-contacts.php?company_id=' . (int) $contact['company_id']
    : '/helpdesk-php/admin-clients.php';

$adminTopbarButtons = [
    [
        'href' => $companyContactsUrl,
        'class' => 'btn-secondary',
        'text' => 'Volver a contactos',
    ],
];

function contactHistorySafe(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function contactHistoryInitials(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        return 'CT';
    }

    $parts = explode(' ', $name);
    $first = mb_substr($parts[0] ?? 'C', 0, 1, 'UTF-8');
    $second = count($parts) > 1
        ? mb_substr($parts[1], 0, 1, 'UTF-8')
        : mb_substr($parts[0], 1, 1, 'UTF-8');

    return mb_strtoupper($first . $second, 'UTF-8');
}

function contactHistoryPhotoUrl(?string $photo): string
{
    $photo = trim((string) $photo);
    if ($photo === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $photo)) {
        return $photo;
    }

    if (str_starts_with($photo, '/helpdesk-php/')) {
        return $photo;
    }

    if (str_starts_with($photo, '/')) {
        return '/helpdesk-php' . $photo;
    }

    return '/helpdesk-php/' . ltrim($photo, '/');
}

function contactHistoryStatusLabel(string $status): string
{
    return match ($status) {
        'ABIERTO' => 'Abierto',
        'EN_PROCESO' => 'En proceso',
        'RESPONDIDO' => 'Respondido',
        'CERRADO' => 'Cerrado',
        default => $status,
    };
}

function contactHistoryStatusClass(string $status): string
{
    return match ($status) {
        'ABIERTO' => 'status-open',
        'EN_PROCESO' => 'status-progress',
        'RESPONDIDO' => 'status-answered',
        'CERRADO' => 'status-closed',
        default => 'status-neutral',
    };
}

function contactHistoryPriorityClass(string $priority): string
{
    return match ($priority) {
        'ALTA' => 'priority-high',
        'MEDIA' => 'priority-medium',
        'BAJA' => 'priority-low',
        default => 'priority-neutral',
    };
}

function contactHistoryBuildUrl(array $changes = []): string
{
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return '/helpdesk-php/admin-contact-history.php?' . http_build_query($query);
}

function contactHistoryDate(?string $value, bool $withTime = true): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);
    if (!$timestamp) {
        return '-';
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

$photoUrl = contactHistoryPhotoUrl($contact['profile_photo'] ?? null);
$totalTickets = (int) ($summary['total'] ?? 0);
$openCount = (int) ($summary['open_count'] ?? 0);
$closedCount = (int) ($summary['closed_count'] ?? 0);
$slaMetCount = (int) ($summary['sla_met_count'] ?? 0);
$slaRate = $totalTickets > 0 ? round(($slaMetCount / $totalTickets) * 100) : 0;

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-contact-history-content">
            <nav class="contact-history-breadcrumb" aria-label="Migas de pan">
                <a href="/helpdesk-php/admin-clients.php">Empresas</a>
                <span>›</span>
                <a href="<?= contactHistorySafe($companyContactsUrl) ?>"><?= contactHistorySafe($companyName) ?></a>
                <span>›</span>
                <strong><?= contactHistorySafe($contact['name']) ?></strong>
            </nav>

            <section class="contact-profile-hero">
                <div class="contact-profile-avatar <?= $photoUrl !== '' ? 'has-photo' : '' ?>">
                    <?php if ($photoUrl !== ''): ?>
                        <img src="<?= contactHistorySafe($photoUrl) ?>" alt="Foto de <?= contactHistorySafe($contact['name']) ?>">
                    <?php else: ?>
                        <?= contactHistorySafe(contactHistoryInitials((string) $contact['name'])) ?>
                    <?php endif; ?>
                </div>

                <div class="contact-profile-copy">
                    <span class="contact-history-eyebrow">Historial individual</span>
                    <h2><?= contactHistorySafe($contact['name']) ?></h2>
                    <p><?= contactHistorySafe($contact['position'] ?: 'Contacto cliente') ?> · <?= contactHistorySafe($companyName) ?></p>
                    <div class="contact-profile-meta">
                        <span><i class="fa-solid fa-envelope"></i><?= contactHistorySafe($contact['email']) ?></span>
                        <span><i class="fa-solid fa-phone"></i><?= contactHistorySafe($contact['phone'] ?: 'Sin teléfono') ?></span>
                        <span><i class="fa-solid fa-circle-check"></i><?= (int) $contact['status'] === 1 ? 'Cuenta activa' : 'Cuenta inactiva' ?></span>
                    </div>
                </div>

                <a class="contact-profile-company-link" href="<?= contactHistorySafe($companyContactsUrl) ?>">
                    <small>Empresa</small>
                    <strong><?= contactHistorySafe($companyName) ?></strong>
                    <span>Ver sus contactos <i class="fa-solid fa-arrow-right"></i></span>
                </a>
            </section>

            <section class="contact-history-summary-grid">
                <article class="contact-history-kpi">
                    <span>Total de tickets</span>
                    <strong><?= $totalTickets ?></strong>
                    <small>Registrados por el contacto</small>
                </article>
                <article class="contact-history-kpi">
                    <span>En atención</span>
                    <strong><?= $openCount ?></strong>
                    <small>Aún no cerrados</small>
                </article>
                <article class="contact-history-kpi">
                    <span>Cerrados</span>
                    <strong><?= $closedCount ?></strong>
                    <small>Atenciones concluidas</small>
                </article>
                <article class="contact-history-kpi">
                    <span>Cumplimiento SLA</span>
                    <strong><?= $slaRate ?>%</strong>
                    <small><?= $slaMetCount ?> tickets cumplidos</small>
                </article>
            </section>

            <section class="card contact-history-filter-card">
                <div>
                    <h3>Filtrar historial</h3>
                    <p>Ubica tickets por código, asunto, estado, prioridad o fecha.</p>
                </div>

                <form action="/helpdesk-php/admin-contact-history.php" method="GET" class="contact-ticket-filter-form">
                    <input type="hidden" name="user_id" value="<?= (int) $contact['id'] ?>">

                    <div class="form-group contact-history-search-field">
                        <label for="search">Buscar</label>
                        <input type="text" id="search" name="search" value="<?= contactHistorySafe($search) ?>" placeholder="Código, asunto, descripción o categoría">
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
                            <option value="ALTA" <?= $priority === 'ALTA' ? 'selected' : '' ?>>Alta</option>
                            <option value="MEDIA" <?= $priority === 'MEDIA' ? 'selected' : '' ?>>Media</option>
                            <option value="BAJA" <?= $priority === 'BAJA' ? 'selected' : '' ?>>Baja</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_from">Desde</label>
                        <input type="date" id="date_from" name="date_from" value="<?= contactHistorySafe($dateFrom) ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_to">Hasta</label>
                        <input type="date" id="date_to" name="date_to" value="<?= contactHistorySafe($dateTo) ?>">
                    </div>

                    <div class="contact-history-filter-actions">
                        <a class="btn-secondary" href="/helpdesk-php/admin-contact-history.php?user_id=<?= (int) $contact['id'] ?>">Limpiar</a>
                        <button type="submit" class="btn-primary">Filtrar</button>
                    </div>
                </form>
            </section>

            <section class="contact-history-section-heading">
                <div>
                    <span class="contact-history-eyebrow">Trazabilidad</span>
                    <h3>Tickets del contacto</h3>
                </div>
                <small><?= (int) $totalTicketsFiltered ?> resultado<?= (int) $totalTicketsFiltered === 1 ? '' : 's' ?></small>
            </section>

            <?php if (!empty($tickets)): ?>
                <section class="contact-ticket-timeline">
                    <?php foreach ($tickets as $ticketItem): ?>
                        <?php
                            $ticketStatus = (string) ($ticketItem['status'] ?? '');
                            $ticketPriority = (string) ($ticketItem['priority'] ?? '');
                            $descriptionText = trim(strip_tags((string) ($ticketItem['description'] ?? '')));
                            if (mb_strlen($descriptionText, 'UTF-8') > 180) {
                                $descriptionText = mb_substr($descriptionText, 0, 177, 'UTF-8') . '...';
                            }
                        ?>
                        <article class="contact-ticket-history-card">
                            <div class="contact-ticket-timeline-marker" aria-hidden="true">
                                <span></span>
                            </div>

                            <div class="contact-ticket-history-main">
                                <div class="contact-ticket-history-head">
                                    <div>
                                        <span class="contact-ticket-code">Ticket #<?= (int) $ticketItem['id'] ?></span>
                                        <h4><?= contactHistorySafe($ticketItem['subject']) ?></h4>
                                    </div>
                                    <div class="contact-ticket-badges">
                                        <span class="contact-ticket-status <?= contactHistorySafe(contactHistoryStatusClass($ticketStatus)) ?>">
                                            <?= contactHistorySafe(contactHistoryStatusLabel($ticketStatus)) ?>
                                        </span>
                                        <span class="contact-ticket-priority <?= contactHistorySafe(contactHistoryPriorityClass($ticketPriority)) ?>">
                                            <?= contactHistorySafe(ucfirst(mb_strtolower($ticketPriority, 'UTF-8'))) ?>
                                        </span>
                                    </div>
                                </div>

                                <p class="contact-ticket-description"><?= contactHistorySafe($descriptionText !== '' ? $descriptionText : 'Sin descripción registrada.') ?></p>

                                <div class="contact-ticket-metadata">
                                    <span><i class="fa-solid fa-layer-group"></i><?= contactHistorySafe($ticketItem['category'] ?: 'Otros') ?></span>
                                    <span><i class="fa-solid fa-user-gear"></i><?= contactHistorySafe($ticketItem['assigned_technician'] ?: 'Sin asignar') ?></span>
                                    <span><i class="fa-solid fa-headset"></i>Nivel <?= (int) ($ticketItem['support_level'] ?? 1) ?></span>
                                    <span><i class="fa-regular fa-calendar"></i><?= contactHistorySafe(contactHistoryDate($ticketItem['created_at'] ?? null)) ?></span>
                                </div>

                                <div class="contact-ticket-trace-summary">
                                    <span><strong><?= (int) ($ticketItem['public_messages_count'] ?? 0) ?></strong> mensajes</span>
                                    <span><strong><?= (int) ($ticketItem['activities_count'] ?? 0) ?></strong> actividades</span>
                                    <span><strong><?= (int) ($ticketItem['attachments_count'] ?? 0) ?></strong> adjuntos</span>
                                    <?php if (!empty($ticketItem['closed_at'])): ?>
                                        <span><strong><?= contactHistorySafe(contactHistoryDate($ticketItem['closed_at'], false)) ?></strong> cierre</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="contact-ticket-history-action">
                                <a href="/helpdesk-php/ticket-detail.php?id=<?= (int) $ticketItem['id'] ?>">
                                    Ver detalle completo
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>

                <?php if ($totalPages > 1): ?>
                    <nav class="contact-history-pagination" aria-label="Paginación del historial">
                        <?php if ($page > 1): ?>
                            <a href="<?= contactHistorySafe(contactHistoryBuildUrl(['page' => $page - 1])) ?>">‹ Anterior</a>
                        <?php endif; ?>

                        <span>Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= contactHistorySafe(contactHistoryBuildUrl(['page' => $page + 1])) ?>">Siguiente ›</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <section class="contact-history-empty-state">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <h3>No hay tickets para mostrar</h3>
                    <p>Este contacto todavía no ha creado tickets o los filtros aplicados no encontraron resultados.</p>
                    <a class="btn-secondary" href="/helpdesk-php/admin-contact-history.php?user_id=<?= (int) $contact['id'] ?>">Limpiar filtros</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
