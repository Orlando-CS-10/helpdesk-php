<?php
$search = $search ?? ($_GET['search'] ?? '');
$sla = $sla ?? ($_GET['sla'] ?? '');
$statusFilter = $statusFilter ?? ($_GET['status'] ?? '');
$clients = $clients ?? [];
$summary = $summary ?? [];
$companyModuleReady = $companyModuleReady ?? false;
$canManageClients = $canManageClients ?? false;
$companyLogoColumnReady = $companyLogoColumnReady ?? false;
$slaProfilesReady = $slaProfilesReady ?? false;
$availableSlaProfiles = $availableSlaProfiles ?? [];

$title = 'Clientes / Empresas';
$activePage = 'clients';
$pageTitle = 'Clientes / Empresas';
$pageSubtitle = 'Administra empresas cliente, contratos SLA y contactos vinculados.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-users.php',
        'class' => 'btn-secondary',
        'text' => 'Ver usuarios'
    ]
];

function clientCompanyDisplayName(array $client): string
{
    $tradeName = trim((string)($client['trade_name'] ?? ''));
    $businessName = trim((string)($client['business_name'] ?? ''));

    return $tradeName !== '' ? $tradeName : $businessName;
}

function clientCompanyInitials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'EM';
    }

    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';

    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= function_exists('mb_substr')
            ? mb_substr($word, 0, 1, 'UTF-8')
            : substr($word, 0, 1);
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($initials, 'UTF-8')
        : strtoupper($initials);
}

function clientCompanyLogoUrl(array $client): ?string
{
    $logoPath = trim((string)($client['logo_path'] ?? ''));
    if ($logoPath === '') {
        return null;
    }

    if (str_starts_with($logoPath, '/')) {
        return $logoPath;
    }

    return '/helpdesk-php/' . ltrim($logoPath, '/');
}

function clientCompanySlaLabel(?string $contract): string
{
    return match ($contract) {
        '24_7' => '24/7',
        '8_5' => '8/5',
        default => '-',
    };
}

function clientCompanySlaHelp(?string $contract): string
{
    return match ($contract) {
        '24_7' => 'Atención continua',
        '8_5' => 'Horario laboral',
        default => 'Sin contrato',
    };
}

function clientCompanySlaProfileName(array $client): string
{
    $profileName = trim((string)($client['sla_profile_name'] ?? ''));
    return $profileName !== '' ? $profileName : 'SLA ' . clientCompanySlaLabel($client['sla_contract_type'] ?? null);
}

function clientCompanySlaProfileHelp(array $client): string
{
    $scheduleType = strtoupper((string)($client['sla_profile_schedule_type'] ?? ''));
    if ($scheduleType === '24_7') {
        return 'Atención continua 24/7';
    }
    if ($scheduleType === 'BUSINESS') {
        $start = substr((string)($client['sla_profile_work_start'] ?? '08:00'), 0, 5);
        $end = substr((string)($client['sla_profile_work_end'] ?? '17:00'), 0, 5);
        return 'Horario laboral · ' . $start . '–' . $end;
    }
    return clientCompanySlaHelp($client['sla_contract_type'] ?? null);
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-clients-content">
            <?php if (!empty($_SESSION['client_success'])): ?>
                <section class="card admin-alert-card admin-alert-success">
                    <strong><?= htmlspecialchars($_SESSION['client_success']) ?></strong>
                </section>
                <?php unset($_SESSION['client_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['client_error'])): ?>
                <section class="card admin-alert-card admin-alert-error">
                    <strong><?= htmlspecialchars($_SESSION['client_error']) ?></strong>
                </section>
                <?php unset($_SESSION['client_error']); ?>
            <?php endif; ?>

            <?php if ($companyModuleReady && !$companyLogoColumnReady && $canManageClients): ?>
                <section class="card admin-alert-card admin-alert-warning">
                    <strong>Los logos todavía no están habilitados.</strong>
                    <p>Ejecuta <code>database/client_company_logo.sql</code>. Mientras tanto se mostrarán las iniciales de cada empresa.</p>
                </section>
            <?php endif; ?>

            <?php if (empty($companyModuleReady)): ?>
                <section class="card admin-alert-card admin-alert-warning">
                    <h3>Tabla de empresas no encontrada</h3>
                    <p>Primero ejecuta el SQL de empresas cliente para activar este módulo.</p>
                </section>
            <?php else: ?>
                <section class="admin-client-summary-grid">
                    <article class="admin-kpi-card">
                        <span class="admin-kpi-label">Empresas registradas</span>
                        <strong class="admin-kpi-value"><?= (int)($summary['total'] ?? 0) ?></strong>
                        <p>Total de clientes corporativos.</p>
                    </article>

                    <article class="admin-kpi-card">
                        <span class="admin-kpi-label">Empresas activas</span>
                        <strong class="admin-kpi-value"><?= (int)($summary['active'] ?? 0) ?></strong>
                        <p>Clientes habilitados para operación.</p>
                    </article>

                    <article class="admin-kpi-card">
                        <span class="admin-kpi-label">Contrato 24/7</span>
                        <strong class="admin-kpi-value"><?= (int)($summary['contract_24_7'] ?? 0) ?></strong>
                        <p>SLA con atención continua.</p>
                    </article>

                    <article class="admin-kpi-card">
                        <span class="admin-kpi-label">Contrato 8/5</span>
                        <strong class="admin-kpi-value"><?= (int)($summary['contract_8_5'] ?? 0) ?></strong>
                        <p>SLA en horario laboral.</p>
                    </article>
                </section>

                <section class="card admin-filters-card">
                    <div class="my-tickets-header">
                        <h2>Filtros</h2>
                        <p>Busca empresas por RUC, razón social, nombre comercial, correo o teléfono.</p>
                    </div>

                    <form action="/helpdesk-php/admin-clients.php" method="GET" class="ticket-form">
                        <div class="form-row admin-client-filter-row">
                            <div class="form-group">
                                <label for="search">Buscar</label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    value="<?= htmlspecialchars($search) ?>"
                                    placeholder="RUC, razón social o nombre comercial">
                            </div>

                            <div class="form-group">
                                <label for="sla">Contrato SLA</label>
                                <select id="sla" name="sla">
                                    <option value="">Todos</option>
                                    <option value="24_7" <?= $sla === '24_7' ? 'selected' : '' ?>>24/7</option>
                                    <option value="8_5" <?= $sla === '8_5' ? 'selected' : '' ?>>8/5</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="status">Estado</label>
                                <select id="status" name="status">
                                    <option value="">Todos</option>
                                    <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Activo</option>
                                    <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="ticket-form-actions">
                            <a href="/helpdesk-php/admin-clients.php" class="btn-secondary">Limpiar</a>
                            <button type="submit" class="btn-primary">Filtrar</button>
                        </div>
                    </form>
                </section>

                <section class="card my-tickets-card">
                    <div class="my-tickets-header admin-client-table-header">
                        <div>
                            <h2>Listado de empresas cliente</h2>
                            <p>Consulta RUC, contrato SLA, contactos vinculados y tickets asociados.</p>
                        </div>

                        <?php if ($canManageClients): ?>
                            <button type="button" class="ticket-link-btn" data-open-client-modal="createClientCompanyModal">
                                <i class="fa-solid fa-plus"></i>
                                Nueva empresa
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($clients)): ?>
                        <div class="tickets-table-wrapper">
                            <table class="tickets-table admin-clients-table admin-clients-table-redesign">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Contacto corporativo</th>
                                        <th>Servicio</th>
                                        <th>Actividad</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                        <?php
                                            $clientId = (int)($client['id'] ?? 0);
                                            $displayName = clientCompanyDisplayName($client);
                                            $contract = $client['sla_contract_type'] ?? null;
                                            $contractLabel = clientCompanySlaLabel($contract);
                                            $contractHelp = clientCompanySlaHelp($contract);
                                            $contactsCount = (int)($client['contacts_count'] ?? 0);
                                            $ticketsCount = (int)($client['tickets_count'] ?? 0);
                                            $openTicketsCount = (int)($client['open_tickets_count'] ?? 0);
                                            $ruc = trim((string)($client['ruc'] ?? ''));
                                            $isActive = (int)($client['status'] ?? 0) === 1;
                                            $registeredAt = !empty($client['created_at'])
                                                ? date('d/m/Y', strtotime($client['created_at']))
                                                : '-';
                                            $companyLogoUrl = clientCompanyLogoUrl($client);
                                            $companyInitials = clientCompanyInitials($displayName);
                                        ?>
                                        <tr class="admin-client-row">
                                            <td class="admin-client-company-cell" data-label="Empresa">
                                                <div class="admin-client-company-block">
                                                    <div class="admin-client-company-logo <?= $companyLogoUrl !== null ? 'has-logo' : 'is-fallback' ?>">
                                                        <?php if ($companyLogoUrl !== null): ?>
                                                            <img
                                                                src="<?= htmlspecialchars($companyLogoUrl) ?>"
                                                                alt="Logo de <?= htmlspecialchars($displayName !== '' ? $displayName : 'la empresa') ?>"
                                                                loading="lazy">
                                                        <?php else: ?>
                                                            <span aria-hidden="true"><?= htmlspecialchars($companyInitials) ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="admin-client-company-copy">
                                                        <strong class="admin-client-company-name">
                                                            <?= htmlspecialchars($displayName !== '' ? $displayName : '-') ?>
                                                        </strong>

                                                        <?php if (!empty($client['business_name']) && $client['business_name'] !== $displayName): ?>
                                                            <span class="admin-client-legal-name">
                                                                <?= htmlspecialchars($client['business_name']) ?>
                                                            </span>
                                                        <?php endif; ?>

                                                        <div class="admin-client-company-meta">
                                                            <span>
                                                                <i class="fa-regular fa-id-card"></i>
                                                                RUC <?= $ruc !== '' ? htmlspecialchars($ruc) : 'no registrado' ?>
                                                            </span>
                                                        </div>

                                                        <?php if (!empty($client['fiscal_address'])): ?>
                                                            <small class="admin-client-address">
                                                                <i class="fa-solid fa-location-dot"></i>
                                                                <span><?= htmlspecialchars($client['fiscal_address']) ?></span>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>

                                            <td data-label="Contacto corporativo">
                                                <div class="admin-client-contact-list">
                                                    <?php if (!empty($client['email'])): ?>
                                                        <span class="admin-client-contact-item">
                                                            <i class="fa-regular fa-envelope"></i>
                                                            <span><?= htmlspecialchars($client['email']) ?></span>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($client['phone'])): ?>
                                                        <span class="admin-client-contact-item">
                                                            <i class="fa-solid fa-phone"></i>
                                                            <span><?= htmlspecialchars($client['phone']) ?></span>
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (empty($client['email']) && empty($client['phone'])): ?>
                                                        <span class="admin-client-empty-value">Sin datos de contacto</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td data-label="Servicio">
                                                <div class="admin-client-service-stack">
                                                    <span class="metric-pill <?= $contract === '24_7' ? 'success-pill' : 'neutral-pill' ?>">
                                                        <?= htmlspecialchars(clientCompanySlaProfileName($client)) ?>
                                                    </span>
                                                    <small><?= htmlspecialchars(clientCompanySlaProfileHelp($client)) ?></small>
                                                    <span class="metric-pill <?= $isActive ? 'success-pill' : 'danger-pill' ?>">
                                                        <?= $isActive ? 'Activo' : 'Inactivo' ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <td data-label="Actividad">
                                                <div class="admin-client-activity-grid">
                                                    <a
                                                        class="admin-client-stat"
                                                        href="/helpdesk-php/admin-company-contacts.php?company_id=<?= $clientId ?>"
                                                        title="Ver contactos de la empresa">
                                                        <strong><?= $contactsCount ?></strong>
                                                        <span>Contacto<?= $contactsCount === 1 ? '' : 's' ?></span>
                                                    </a>

                                                    <span class="admin-client-stat">
                                                        <strong><?= $ticketsCount ?></strong>
                                                        <span>Ticket<?= $ticketsCount === 1 ? '' : 's' ?></span>
                                                    </span>

                                                    <span class="admin-client-stat <?= $openTicketsCount > 0 ? 'has-open-tickets' : '' ?>">
                                                        <strong><?= $openTicketsCount ?></strong>
                                                        <span>Abierto<?= $openTicketsCount === 1 ? '' : 's' ?></span>
                                                    </span>
                                                </div>
                                            </td>

                                            <td data-label="Registro">
                                                <div class="admin-client-registration">
                                                    <i class="fa-regular fa-calendar"></i>
                                                    <span>
                                                        <strong><?= htmlspecialchars($registeredAt) ?></strong>
                                                        <small>Fecha de alta</small>
                                                    </span>
                                                </div>
                                            </td>

                                            <td data-label="Acciones">
                                                <div class="admin-client-actions admin-client-actions-redesign">
                                                    <a
                                                        href="/helpdesk-php/admin-company-contacts.php?company_id=<?= $clientId ?>"
                                                        class="ticket-link-btn admin-client-history-link">
                                                        <i class="fa-solid fa-address-book"></i>
                                                        <span>Ver contactos</span>
                                                    </a>

                                                    <?php if ($canManageClients): ?>
                                                        <div class="admin-client-secondary-actions">
                                                            <button
                                                                type="button"
                                                                class="ticket-link-btn admin-client-edit-action"
                                                                data-open-client-modal="editClientCompanyModal<?= $clientId ?>">
                                                                <i class="fa-regular fa-pen-to-square"></i>
                                                                <span>Editar</span>
                                                            </button>

                                                            <a
                                                                href="/helpdesk-php/toggle-client-company.php?id=<?= $clientId ?>"
                                                                class="admin-client-status-action <?= $isActive ? 'is-deactivate' : 'is-activate' ?>">
                                                                <i class="fa-solid <?= $isActive ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                                                                <span><?= $isActive ? 'Desactivar' : 'Activar' ?></span>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-ticket-box">
                            <h4>No se encontraron empresas</h4>
                            <p>No hay clientes registrados o no existen resultados con los filtros aplicados.</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php if ($companyModuleReady && $canManageClients): ?>
    <div class="client-modal-backdrop" id="createClientCompanyModal" aria-hidden="true">
        <div class="client-company-modal" role="dialog" aria-modal="true" aria-labelledby="createClientCompanyTitle">
            <div class="client-company-modal-header">
                <div>
                    <span>Cliente corporativo</span>
                    <h3 id="createClientCompanyTitle">Registrar empresa cliente</h3>
                </div>
                <button type="button" class="client-modal-close" data-close-client-modal="createClientCompanyModal">&times;</button>
            </div>

            <form action="/helpdesk-php/create-client-company.php" method="POST" enctype="multipart/form-data" class="client-company-form" autocomplete="off">
                <div class="client-company-logo-editor">
                    <div class="client-company-logo-preview" id="createCompanyLogoPreview">
                        <img src="" alt="Vista previa del logo" class="is-hidden" data-company-logo-image>
                        <span data-company-logo-fallback>EM</span>
                    </div>
                    <div class="client-company-logo-copy">
                        <label class="client-company-logo-button" for="client_company_logo">
                            <i class="fa-regular fa-image"></i>
                            Seleccionar logo
                        </label>
                        <input
                            type="file"
                            id="client_company_logo"
                            name="company_logo"
                            class="client-company-logo-input"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            data-company-logo-input
                            data-preview-target="createCompanyLogoPreview">
                        <strong data-company-logo-filename>Ningún archivo seleccionado</strong>
                        <small>JPG, PNG o WebP. Tamaño máximo: 2 MB. Se recomienda un logo cuadrado o con fondo transparente.</small>
                    </div>
                </div>

                <div class="client-company-grid">
                    <div class="form-group">
                        <label for="client_ruc">RUC</label>
                        <input type="text" id="client_ruc" name="ruc" maxlength="11" inputmode="numeric" placeholder="Ej. 20100000000">
                    </div>
                    <div class="form-group">
                        <label for="client_business_name">Razón social</label>
                        <input type="text" id="client_business_name" name="business_name" required placeholder="Ej. FERREYROS S.A.">
                    </div>
                    <div class="form-group">
                        <label for="client_trade_name">Nombre comercial</label>
                        <input type="text" id="client_trade_name" name="trade_name" placeholder="Ej. Ferreyros">
                    </div>
                    <div class="form-group">
                        <?php if ($slaProfilesReady && $availableSlaProfiles): ?>
                            <label for="client_sla_profile_id">Perfil SLA</label>
                            <select id="client_sla_profile_id" name="sla_profile_id" required>
                                <?php foreach ($availableSlaProfiles as $slaProfile): ?>
                                    <option value="<?= (int)$slaProfile['id'] ?>" <?= !empty($slaProfile['is_default']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$slaProfile['name']) ?> · <?= htmlspecialchars((string)($slaProfile['schedule_label'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="sla_contract_type" value="8_5">
                        <?php else: ?>
                            <label for="client_sla_contract_type">Contrato SLA</label>
                            <select id="client_sla_contract_type" name="sla_contract_type" required>
                                <option value="8_5">8/5 - Horario laboral</option>
                                <option value="24_7">24/7 - Atención continua</option>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group client-company-full">
                        <label for="client_fiscal_address">Dirección fiscal</label>
                        <input type="text" id="client_fiscal_address" name="fiscal_address" placeholder="Dirección de la empresa">
                    </div>
                    <div class="form-group">
                        <label for="client_phone">Teléfono</label>
                        <input type="text" id="client_phone" name="phone" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="client_email">Correo corporativo</label>
                        <input type="email" id="client_email" name="email" placeholder="contacto@empresa.com">
                    </div>
                </div>

                <div class="client-company-modal-actions">
                    <button type="button" class="btn-secondary" data-close-client-modal="createClientCompanyModal">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar empresa</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($clients as $client): ?>
        <?php $clientId = (int)($client['id'] ?? 0); ?>
        <div class="client-modal-backdrop" id="editClientCompanyModal<?= $clientId ?>" aria-hidden="true">
            <div class="client-company-modal" role="dialog" aria-modal="true" aria-labelledby="editClientCompanyTitle<?= $clientId ?>">
                <div class="client-company-modal-header">
                    <div>
                        <span>Actualizar cliente</span>
                        <h3 id="editClientCompanyTitle<?= $clientId ?>">Editar empresa cliente</h3>
                    </div>
                    <button type="button" class="client-modal-close" data-close-client-modal="editClientCompanyModal<?= $clientId ?>">&times;</button>
                </div>

                <form action="/helpdesk-php/update-client-company.php" method="POST" enctype="multipart/form-data" class="client-company-form" autocomplete="off">
                    <input type="hidden" name="id" value="<?= $clientId ?>">
                    <?php
                        $editDisplayName = clientCompanyDisplayName($client);
                        $editLogoUrl = clientCompanyLogoUrl($client);
                        $editInitials = clientCompanyInitials($editDisplayName);
                    ?>
                    <div class="client-company-logo-editor">
                        <div
                            class="client-company-logo-preview"
                            id="editCompanyLogoPreview<?= $clientId ?>"
                            data-current-logo="<?= htmlspecialchars($editLogoUrl ?? '') ?>">
                            <img
                                src="<?= htmlspecialchars($editLogoUrl ?? '') ?>"
                                alt="Vista previa del logo"
                                class="<?= $editLogoUrl === null ? 'is-hidden' : '' ?>"
                                data-company-logo-image>
                            <span
                                class="<?= $editLogoUrl !== null ? 'is-hidden' : '' ?>"
                                data-company-logo-fallback><?= htmlspecialchars($editInitials) ?></span>
                        </div>
                        <div class="client-company-logo-copy">
                            <label class="client-company-logo-button" for="edit_company_logo_<?= $clientId ?>">
                                <i class="fa-regular fa-image"></i>
                                Cambiar logo
                            </label>
                            <input
                                type="file"
                                id="edit_company_logo_<?= $clientId ?>"
                                name="company_logo"
                                class="client-company-logo-input"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                data-company-logo-input
                                data-preview-target="editCompanyLogoPreview<?= $clientId ?>">
                            <strong data-company-logo-filename>
                                <?= $editLogoUrl !== null ? 'Logo actual de la empresa' : 'Ningún logo registrado' ?>
                            </strong>
                            <small>JPG, PNG o WebP. Tamaño máximo: 2 MB.</small>

                            <?php if ($editLogoUrl !== null): ?>
                                <label class="client-company-remove-logo">
                                    <input
                                        type="checkbox"
                                        name="remove_logo"
                                        value="1"
                                        data-company-logo-remove
                                        data-preview-target="editCompanyLogoPreview<?= $clientId ?>">
                                    Eliminar logo actual
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="client-company-grid">
                        <div class="form-group">
                            <label for="edit_ruc_<?= $clientId ?>">RUC</label>
                            <input type="text" id="edit_ruc_<?= $clientId ?>" name="ruc" maxlength="11" inputmode="numeric" value="<?= htmlspecialchars($client['ruc'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_business_name_<?= $clientId ?>">Razón social</label>
                            <input type="text" id="edit_business_name_<?= $clientId ?>" name="business_name" required value="<?= htmlspecialchars($client['business_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_trade_name_<?= $clientId ?>">Nombre comercial</label>
                            <input type="text" id="edit_trade_name_<?= $clientId ?>" name="trade_name" value="<?= htmlspecialchars($client['trade_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <?php if ($slaProfilesReady && $availableSlaProfiles): ?>
                                <label for="edit_sla_profile_id_<?= $clientId ?>">Perfil SLA</label>
                                <select id="edit_sla_profile_id_<?= $clientId ?>" name="sla_profile_id" required>
                                    <?php foreach ($availableSlaProfiles as $slaProfile): ?>
                                        <option value="<?= (int)$slaProfile['id'] ?>" <?= (int)($client['sla_profile_id'] ?? 0) === (int)$slaProfile['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string)$slaProfile['name']) ?> · <?= htmlspecialchars((string)($slaProfile['schedule_label'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="sla_contract_type" value="<?= htmlspecialchars((string)($client['sla_contract_type'] ?? '8_5')) ?>">
                            <?php else: ?>
                                <label for="edit_sla_contract_type_<?= $clientId ?>">Contrato SLA</label>
                                <select id="edit_sla_contract_type_<?= $clientId ?>" name="sla_contract_type" required>
                                    <option value="8_5" <?= ($client['sla_contract_type'] ?? '') === '8_5' ? 'selected' : '' ?>>8/5 - Horario laboral</option>
                                    <option value="24_7" <?= ($client['sla_contract_type'] ?? '') === '24_7' ? 'selected' : '' ?>>24/7 - Atención continua</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group client-company-full">
                            <label for="edit_fiscal_address_<?= $clientId ?>">Dirección fiscal</label>
                            <input type="text" id="edit_fiscal_address_<?= $clientId ?>" name="fiscal_address" value="<?= htmlspecialchars($client['fiscal_address'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_phone_<?= $clientId ?>">Teléfono</label>
                            <input type="text" id="edit_phone_<?= $clientId ?>" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="edit_email_<?= $clientId ?>">Correo corporativo</label>
                            <input type="email" id="edit_email_<?= $clientId ?>" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="client-company-modal-actions">
                        <button type="button" class="btn-secondary" data-close-client-modal="editClientCompanyModal<?= $clientId ?>">Cancelar</button>
                        <button type="submit" class="btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        function openClientModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        }

        function closeClientModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('[data-company-logo-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                const file = input.files && input.files[0] ? input.files[0] : null;
                const targetId = input.getAttribute('data-preview-target');
                const preview = targetId ? document.getElementById(targetId) : null;
                const filename = input.closest('.client-company-logo-copy')?.querySelector('[data-company-logo-filename]');

                if (!file || !preview) return;

                const image = preview.querySelector('[data-company-logo-image]');
                const fallback = preview.querySelector('[data-company-logo-fallback]');
                const objectUrl = URL.createObjectURL(file);

                if (image) {
                    image.src = objectUrl;
                    image.classList.remove('is-hidden');
                }

                if (fallback) {
                    fallback.classList.add('is-hidden');
                }

                if (filename) {
                    filename.textContent = file.name;
                }

                const removeCheckbox = input.closest('.client-company-logo-copy')?.querySelector('[data-company-logo-remove]');
                if (removeCheckbox) {
                    removeCheckbox.checked = false;
                }
            });
        });

        document.querySelectorAll('[data-company-logo-remove]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const targetId = checkbox.getAttribute('data-preview-target');
                const preview = targetId ? document.getElementById(targetId) : null;
                if (!preview) return;

                const image = preview.querySelector('[data-company-logo-image]');
                const fallback = preview.querySelector('[data-company-logo-fallback]');
                const currentLogo = preview.getAttribute('data-current-logo') || '';

                if (checkbox.checked) {
                    if (image) image.classList.add('is-hidden');
                    if (fallback) fallback.classList.remove('is-hidden');
                    return;
                }

                if (currentLogo !== '') {
                    if (image) {
                        image.src = currentLogo;
                        image.classList.remove('is-hidden');
                    }
                    if (fallback) fallback.classList.add('is-hidden');
                }
            });
        });

        document.querySelectorAll('[data-open-client-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                openClientModal(button.getAttribute('data-open-client-modal'));
            });
        });

        document.querySelectorAll('[data-close-client-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeClientModal(button.getAttribute('data-close-client-modal'));
            });
        });

        document.querySelectorAll('.client-modal-backdrop').forEach(function (backdrop) {
            backdrop.addEventListener('click', function (event) {
                if (event.target === backdrop) closeClientModal(backdrop.id);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.client-modal-backdrop.show').forEach(function (modal) {
                closeClientModal(modal.id);
            });
        });
    });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
