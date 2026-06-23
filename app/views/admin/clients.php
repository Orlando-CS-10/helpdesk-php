<?php
$search = $search ?? ($_GET['search'] ?? '');
$sla = $sla ?? ($_GET['sla'] ?? '');
$statusFilter = $statusFilter ?? ($_GET['status'] ?? '');
$clients = $clients ?? [];
$summary = $summary ?? [];
$companyModuleReady = $companyModuleReady ?? false;
$canManageClients = $canManageClients ?? false;

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
                            <table class="tickets-table admin-clients-table">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>RUC</th>
                                        <th>Contacto corporativo</th>
                                        <th>Contrato SLA</th>
                                        <th>Contactos</th>
                                        <th>Tickets</th>
                                        <th>Estado</th>
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
                                        ?>
                                        <tr>
                                            <td class="admin-client-company-cell">
                                                <strong><?= htmlspecialchars($displayName !== '' ? $displayName : '-') ?></strong>
                                                <?php if (!empty($client['business_name']) && $client['business_name'] !== $displayName): ?>
                                                    <span><?= htmlspecialchars($client['business_name']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($client['fiscal_address'])): ?>
                                                    <small><?= htmlspecialchars($client['fiscal_address']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $ruc !== '' ? htmlspecialchars($ruc) : '-' ?></td>
                                            <td>
                                                <?php if (!empty($client['email'])): ?>
                                                    <strong><?= htmlspecialchars($client['email']) ?></strong><br>
                                                <?php endif; ?>
                                                <span><?= !empty($client['phone']) ? htmlspecialchars($client['phone']) : '-' ?></span>
                                            </td>
                                            <td>
                                                <span class="metric-pill <?= $contract === '24_7' ? 'success-pill' : 'neutral-pill' ?>">SLA <?= htmlspecialchars($contractLabel) ?></span>
                                                <small class="admin-client-help-text"><?= htmlspecialchars($contractHelp) ?></small>
                                            </td>
                                            <td>
                                                <a class="admin-client-count-link" href="/helpdesk-php/admin-users.php?search=<?= urlencode($ruc !== '' ? $ruc : $displayName) ?>">
                                                    <?= $contactsCount ?> contacto<?= $contactsCount === 1 ? '' : 's' ?>
                                                </a>
                                            </td>
                                            <td>
                                                <strong><?= $ticketsCount ?></strong>
                                                <small class="admin-client-help-text"><?= $openTicketsCount ?> abiertos</small>
                                            </td>
                                            <td>
                                                <?php if ((int)($client['status'] ?? 0) === 1): ?>
                                                    <span class="metric-pill success-pill">Activo</span>
                                                <?php else: ?>
                                                    <span class="metric-pill danger-pill">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= !empty($client['created_at']) ? date('d/m/Y', strtotime($client['created_at'])) : '-' ?></td>
                                            <td>
                                                <div class="admin-client-actions">
                                                    <?php if ($canManageClients): ?>
                                                        <button type="button" class="ticket-link-btn" data-open-client-modal="editClientCompanyModal<?= $clientId ?>">
                                                            Editar
                                                        </button>
                                                        <a href="/helpdesk-php/toggle-client-company.php?id=<?= $clientId ?>" class="<?= (int)($client['status'] ?? 0) === 1 ? 'btn-secondary' : 'btn-primary' ?>">
                                                            <?= (int)($client['status'] ?? 0) === 1 ? 'Desactivar' : 'Activar' ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="metric-pill neutral-pill">Solo lectura</span>
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

            <form action="/helpdesk-php/create-client-company.php" method="POST" class="client-company-form" autocomplete="off">
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
                        <label for="client_sla_contract_type">Contrato SLA</label>
                        <select id="client_sla_contract_type" name="sla_contract_type" required>
                            <option value="8_5">8/5 - Horario laboral</option>
                            <option value="24_7">24/7 - Atención continua</option>
                        </select>
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

                <form action="/helpdesk-php/update-client-company.php" method="POST" class="client-company-form" autocomplete="off">
                    <input type="hidden" name="id" value="<?= $clientId ?>">
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
                            <label for="edit_sla_contract_type_<?= $clientId ?>">Contrato SLA</label>
                            <select id="edit_sla_contract_type_<?= $clientId ?>" name="sla_contract_type" required>
                                <option value="8_5" <?= ($client['sla_contract_type'] ?? '') === '8_5' ? 'selected' : '' ?>>8/5 - Horario laboral</option>
                                <option value="24_7" <?= ($client['sla_contract_type'] ?? '') === '24_7' ? 'selected' : '' ?>>24/7 - Atención continua</option>
                            </select>
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
