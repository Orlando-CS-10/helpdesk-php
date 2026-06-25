<?php
$title = 'Contactos de empresa';
$activePage = 'clients';

$companyName = trim((string) ($company['trade_name'] ?? ''));
if ($companyName === '') {
    $companyName = trim((string) ($company['business_name'] ?? 'Empresa cliente'));
}

$pageTitle = 'Contactos de ' . $companyName;
$pageSubtitle = 'Selecciona un contacto para consultar su historial individual de tickets y atenciones.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-clients.php',
        'class' => 'btn-secondary',
        'text' => 'Volver a empresas',
    ],
];

function companyContactsSafe(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function companyContactsInitials(string $name): string
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

function companyContactsPhotoUrl(?string $photo): string
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


function companyContactsCompanyLogoUrl(?string $logoPath): string
{
    $logoPath = trim((string) $logoPath);
    if ($logoPath === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $logoPath)) {
        return $logoPath;
    }

    if (str_starts_with($logoPath, '/helpdesk-php/')) {
        return $logoPath;
    }

    if (str_starts_with($logoPath, '/')) {
        return '/helpdesk-php' . $logoPath;
    }

    return '/helpdesk-php/' . ltrim($logoPath, '/');
}

function companyContactsCompanyInitials(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') {
        return 'EM';
    }

    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= function_exists('mb_substr')
            ? mb_substr($part, 0, 1, 'UTF-8')
            : substr($part, 0, 1);
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($initials, 'UTF-8')
        : strtoupper($initials);
}

function companyContactsBuildUrl(array $changes = []): string
{
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return '/helpdesk-php/admin-company-contacts.php?' . http_build_query($query);
}

function companyContactsDate(?string $value): string
{
    if (empty($value)) {
        return 'Sin actividad';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Sin actividad';
}

$companyLogoUrl = companyContactsCompanyLogoUrl($company['logo_path'] ?? null);
$companyLogoInitials = companyContactsCompanyInitials($companyName);

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-company-contacts-content">
            <nav class="contact-history-breadcrumb" aria-label="Migas de pan">
                <a href="/helpdesk-php/admin-clients.php">Empresas</a>
                <span>›</span>
                <strong><?= companyContactsSafe($companyName) ?></strong>
            </nav>

            <section class="contact-company-hero">
                <div class="contact-company-hero-copy">
                    <span class="contact-history-eyebrow">Empresa cliente</span>
                    <h2><?= companyContactsSafe($companyName) ?></h2>
                    <p>
                        <?= companyContactsSafe($company['business_name'] ?? '') ?>
                        <?php if (!empty($company['ruc'])): ?> · RUC <?= companyContactsSafe($company['ruc']) ?><?php endif; ?>
                    </p>
                    <div class="contact-company-meta">
                        <span><i class="fa-solid fa-envelope"></i><?= companyContactsSafe($company['email'] ?: 'Sin correo corporativo') ?></span>
                        <span><i class="fa-solid fa-phone"></i><?= companyContactsSafe($company['phone'] ?: 'Sin teléfono') ?></span>
                        <span><i class="fa-solid fa-shield-halved"></i>SLA <?= companyContactsSafe(($company['sla_contract_type'] ?? '') === '24_7' ? '24/7' : '8/5') ?></span>
                    </div>
                </div>

                <div class="contact-company-hero-logo <?= $companyLogoUrl !== '' ? 'has-logo' : 'is-fallback' ?>">
                    <?php if ($companyLogoUrl !== ''): ?>
                        <img
                            src="<?= companyContactsSafe($companyLogoUrl) ?>"
                            alt="Logo de <?= companyContactsSafe($companyName) ?>"
                            loading="eager">
                    <?php else: ?>
                        <span aria-hidden="true"><?= companyContactsSafe($companyLogoInitials) ?></span>
                        <small>Sin logo</small>
                    <?php endif; ?>
                </div>
            </section>

            <section class="contact-history-summary-grid">
                <article class="contact-history-kpi">
                    <span>Contactos</span>
                    <strong><?= (int) ($summary['total_contacts'] ?? 0) ?></strong>
                    <small>Personas vinculadas</small>
                </article>
                <article class="contact-history-kpi">
                    <span>Activos</span>
                    <strong><?= (int) ($summary['active_contacts'] ?? 0) ?></strong>
                    <small>Con acceso habilitado</small>
                </article>
                <article class="contact-history-kpi">
                    <span>Tickets</span>
                    <strong><?= (int) ($summary['total_tickets'] ?? 0) ?></strong>
                    <small>Registrados por contactos</small>
                </article>
                <article class="contact-history-kpi">
                    <span>Abiertos</span>
                    <strong><?= (int) ($summary['open_tickets'] ?? 0) ?></strong>
                    <small>Pendientes de cierre</small>
                </article>
            </section>

            <section class="card contact-history-filter-card">
                <div>
                    <h3>Buscar contactos</h3>
                    <p>Filtra por nombre, correo, teléfono, cargo o estado de cuenta.</p>
                </div>

                <form action="/helpdesk-php/admin-company-contacts.php" method="GET" class="contact-history-filter-form">
                    <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                    <div class="form-group contact-history-search-field">
                        <label for="search">Buscar</label>
                        <input type="text" id="search" name="search" value="<?= companyContactsSafe($search) ?>" placeholder="Nombre, correo, cargo o teléfono">
                    </div>
                    <div class="form-group">
                        <label for="status">Estado</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="contact-history-filter-actions">
                        <a class="btn-secondary" href="/helpdesk-php/admin-company-contacts.php?company_id=<?= (int) $company['id'] ?>">Limpiar</a>
                        <button type="submit" class="btn-primary">Filtrar</button>
                    </div>
                </form>
            </section>

            <section class="contact-history-section-heading">
                <div>
                    <span class="contact-history-eyebrow">Directorio</span>
                    <h3>Contactos de la empresa</h3>
                </div>
                <small><?= (int) $totalContacts ?> resultado<?= (int) $totalContacts === 1 ? '' : 's' ?></small>
            </section>

            <?php if (!empty($contacts)): ?>
                <section class="company-contact-grid">
                    <?php foreach ($contacts as $contactItem): ?>
                        <?php
                            $photoUrl = companyContactsPhotoUrl($contactItem['profile_photo'] ?? null);
                            $ticketsCount = (int) ($contactItem['tickets_count'] ?? 0);
                            $openTickets = (int) ($contactItem['open_tickets_count'] ?? 0);
                            $closedTickets = (int) ($contactItem['closed_tickets_count'] ?? 0);
                        ?>
                        <article class="company-contact-card">
                            <div class="company-contact-card-head">
                                <div class="company-contact-avatar <?= $photoUrl !== '' ? 'has-photo' : '' ?>">
                                    <?php if ($photoUrl !== ''): ?>
                                        <img src="<?= companyContactsSafe($photoUrl) ?>" alt="Foto de <?= companyContactsSafe($contactItem['name']) ?>">
                                    <?php else: ?>
                                        <?= companyContactsSafe(companyContactsInitials((string) $contactItem['name'])) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="company-contact-identity">
                                    <strong><?= companyContactsSafe($contactItem['name']) ?></strong>
                                    <span><?= companyContactsSafe($contactItem['position'] ?: 'Contacto cliente') ?></span>
                                </div>
                                <span class="contact-account-state <?= (int) $contactItem['status'] === 1 ? 'is-active' : 'is-inactive' ?>">
                                    <?= (int) $contactItem['status'] === 1 ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </div>

                            <dl class="company-contact-details">
                                <div><dt>Correo</dt><dd><?= companyContactsSafe($contactItem['email']) ?></dd></div>
                                <div><dt>Teléfono</dt><dd><?= companyContactsSafe($contactItem['phone'] ?: 'No registrado') ?></dd></div>
                                <div><dt>Última actividad</dt><dd><?= companyContactsSafe(companyContactsDate($contactItem['last_activity_at'] ?? null)) ?></dd></div>
                            </dl>

                            <div class="company-contact-ticket-stats">
                                <span><strong><?= $ticketsCount ?></strong>Total</span>
                                <span><strong><?= $openTickets ?></strong>Abiertos</span>
                                <span><strong><?= $closedTickets ?></strong>Cerrados</span>
                            </div>

                            <a class="company-contact-history-button" href="/helpdesk-php/admin-contact-history.php?user_id=<?= (int) $contactItem['id'] ?>">
                                Ver historial individual
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </section>

                <?php if ($totalPages > 1): ?>
                    <nav class="contact-history-pagination" aria-label="Paginación de contactos">
                        <?php if ($page > 1): ?>
                            <a href="<?= companyContactsSafe(companyContactsBuildUrl(['page' => $page - 1])) ?>">‹ Anterior</a>
                        <?php endif; ?>

                        <span>Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= companyContactsSafe(companyContactsBuildUrl(['page' => $page + 1])) ?>">Siguiente ›</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <section class="contact-history-empty-state">
                    <i class="fa-solid fa-address-book"></i>
                    <h3>No se encontraron contactos</h3>
                    <p>La empresa todavía no tiene contactos asociados o los filtros no devolvieron resultados.</p>
                    <a class="btn-secondary" href="/helpdesk-php/admin-users.php?role=CLIENT&company_id=<?= (int) $company['id'] ?>">Revisar usuarios</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
