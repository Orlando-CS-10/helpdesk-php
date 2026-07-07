<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_security.php';

requireLogin();

$currentUser = user();
$currentRole = $currentUser['role'] ?? '';
$managerRoles = ['ADMIN', 'TECH'];

if (!in_array($currentRole, $managerRoles, true)) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

function usersPageTableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table_name");
        $stmt->execute(['table_name' => $table]);
        return (bool) $stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $e) {
        return false;
    }
}

function usersPageColumnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
        $stmt->execute(['column' => $column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

$role = trim($_GET['role'] ?? '');
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$companyId = trim($_GET['company_id'] ?? '');

$allowedRoles = ['CLIENT', 'TECH', 'ADMIN'];
$allowedStatuses = ['1', '0'];
$createAllowedRoles = $currentRole === 'ADMIN' ? ['CLIENT', 'TECH', 'ADMIN'] : ['CLIENT'];
$canCreateUsers = true;
$isTechManager = $currentRole === 'TECH';

$hasClientCompanies = usersPageTableExists($pdo, 'client_companies');
$hasCompanyIdColumn = usersPageColumnExists($pdo, 'users', 'company_id');
$hasCanViewCompanyTicketsColumn = usersPageColumnExists($pdo, 'users', 'can_view_company_tickets');
$hasTechLevelColumn = usersPageColumnExists($pdo, 'users', 'tech_level');
$hasProfilePhotoColumn = usersPageColumnExists($pdo, 'users', 'profile_photo');
$companyModuleReady = $hasClientCompanies && $hasCompanyIdColumn;

$companyOptions = [];
if ($hasClientCompanies) {
    $stmtCompanies = $pdo->query("SELECT id, ruc, business_name, trade_name, sla_contract_type
                                  FROM client_companies
                                  WHERE status = 1
                                  ORDER BY COALESCE(NULLIF(trade_name, ''), business_name) ASC");
    $companyOptions = $stmtCompanies->fetchAll(PDO::FETCH_ASSOC);
}

$summary = [
    'total' => 0,
    'clients' => 0,
    'techs' => 0,
    'admins' => 0,
    'active' => 0,
];

try {
    $summaryStmt = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN role = 'CLIENT' THEN 1 ELSE 0 END) AS clients,
        SUM(CASE WHEN role = 'TECH' THEN 1 ELSE 0 END) AS techs,
        SUM(CASE WHEN role = 'ADMIN' THEN 1 ELSE 0 END) AS admins,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active
        FROM users");
    $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($summary as $key => $value) {
        $summary[$key] = (int)($summaryRow[$key] ?? 0);
    }
} catch (Throwable $e) {
    // La vista seguirá funcionando aunque no se puedan calcular los contadores.
}

$selectCompanyColumns = $companyModuleReady
    ? "u.company_id,
       cc.ruc AS company_ruc,
       cc.business_name AS company_business_name,
       cc.trade_name AS company_trade_name,
       cc.sla_contract_type AS sla_contract_type,"
    : "NULL AS company_id,
       NULL AS company_ruc,
       NULL AS company_business_name,
       NULL AS company_trade_name,
       NULL AS sla_contract_type,";

$sql = "SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            u.status,
            u.phone,
            u.position,
            u.company,
            " . ($hasProfilePhotoColumn ? "u.profile_photo" : "NULL AS profile_photo") . ",
            " . ($hasTechLevelColumn ? "u.tech_level" : "NULL AS tech_level") . ",
            " . ($hasCanViewCompanyTicketsColumn ? "u.can_view_company_tickets" : "0 AS can_view_company_tickets") . ",
            u.created_at,
            $selectCompanyColumns
            1 AS row_marker
        FROM users u";

if ($companyModuleReady) {
    $sql .= " LEFT JOIN client_companies cc ON cc.id = u.company_id";
}

$sql .= " WHERE 1=1";

$params = [];

if ($role !== '' && in_array($role, $allowedRoles, true)) {
    $sql .= " AND u.role = :role";
    $params['role'] = $role;
}

if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $sql .= " AND u.status = :status";
    $params['status'] = (int)$status;
}

if ($companyModuleReady && $companyId !== '' && ctype_digit($companyId)) {
    $sql .= " AND u.company_id = :company_id";
    $params['company_id'] = (int)$companyId;
}

if ($search !== '') {
    if ($companyModuleReady) {
        $sql .= " AND (
            u.name LIKE :search
            OR u.email LIKE :search
            OR u.company LIKE :search
            OR u.position LIKE :search
            OR cc.business_name LIKE :search
            OR cc.trade_name LIKE :search
            OR cc.ruc LIKE :search
        )";
    } else {
        $sql .= " AND (
            u.name LIKE :search
            OR u.email LIKE :search
            OR u.company LIKE :search
            OR u.position LIKE :search
        )";
    }
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY
            FIELD(u.role, 'ADMIN', 'TECH', 'CLIENT'),
            u.created_at DESC,
            u.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$securitySettings = getSystemSecuritySettings($pdo);
$createUserCsrfToken = systemSecurityCsrfToken('create_user');
$createUserPasswordMinimum = max(6, (int) ($securitySettings['min_password_length'] ?? 8));
$createUserPasswordRules = systemSecurityPasswordRulesText($securitySettings);

ob_start();
require __DIR__ . '/app/views/admin/users.php';
$pageContent = ob_get_clean();

if ($canCreateUsers) {
    $roleOptionsHtml = '';
    foreach ($createAllowedRoles as $optionRole) {
        $label = [
            'CLIENT' => 'Contacto cliente',
            'TECH' => 'Técnico',
            'ADMIN' => 'Administrador',
        ][$optionRole] ?? $optionRole;

        $roleOptionsHtml .= '<option value="' . htmlspecialchars($optionRole, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    $companyOptionsHtml = '<option value="">Seleccionar empresa cliente</option>';
    foreach ($companyOptions as $companyOption) {
        $companyName = trim((string)($companyOption['trade_name'] ?? ''));
        if ($companyName === '') {
            $companyName = trim((string)($companyOption['business_name'] ?? ''));
        }

        $companyText = $companyName;
        if (!empty($companyOption['ruc'])) {
            $companyText .= ' - RUC ' . $companyOption['ruc'];
        }

        $companyOptionsHtml .= '<option value="' . (int)$companyOption['id'] . '">' . htmlspecialchars($companyText, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    $fixedRoleNotice = $isTechManager
        ? '<p class="create-user-note">Como técnico, puedes crear únicamente contactos cliente.</p>'
        : '<p class="create-user-note">Como administrador, puedes crear contactos cliente, técnicos y administradores.</p>';

    $companySelectorHtml = '';
    if ($companyModuleReady) {
        $companySelectorHtml = '
                <div class="create-user-field create-user-client-only create-user-full" id="createUserCompanySelectWrap">
                    <label for="create_user_company_id">Empresa cliente</label>
                    <select id="create_user_company_id" name="company_id">
                        ' . $companyOptionsHtml . '
                    </select>
                    <small class="create-user-help">Las empresas y contratos SLA se administran desde el módulo Clientes.</small>
                </div>

                <div class="create-user-field create-user-client-only create-user-full" id="createUserCompanyScopeWrap">
                    <label class="create-user-checkbox">
                        <input type="checkbox" name="can_view_company_tickets" value="1">
                        <span>Este contacto puede ver todos los tickets de su empresa.</span>
                    </label>
                </div>';
    } else {
        $companySelectorHtml = '
                <div class="create-user-field create-user-client-only create-user-full">
                    <label for="create_user_company">Empresa</label>
                    <input type="text" id="create_user_company" name="company" maxlength="150" autocomplete="organization" placeholder="Opcional" data-business-input>
                    <small class="create-user-help">Letras, números y signos comerciales básicos. No uses caracteres raros.</small>
                </div>';
    }

    $createUserWidget = '
<link rel="stylesheet" href="/helpdesk-php/public/assets/css/create-user-modal.css">

<div class="create-user-floating-action">
    <button type="button" class="create-user-open-btn" id="openCreateUserModal">
        <i class="fa-solid fa-user-plus"></i>
        Crear usuario
    </button>
</div>

<div class="create-user-modal-backdrop" id="createUserModal" aria-hidden="true">
    <div class="create-user-modal" role="dialog" aria-modal="true" aria-labelledby="createUserModalTitle">
        <div class="create-user-modal-header">
            <div>
                <span>Gestión de cuentas</span>
                <h3 id="createUserModalTitle">Crear nuevo usuario</h3>
            </div>
            <button type="button" class="create-user-close-btn" id="closeCreateUserModal" aria-label="Cerrar">&times;</button>
        </div>

        <form action="/helpdesk-php/create-user.php" method="POST" class="create-user-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="' . htmlspecialchars($createUserCsrfToken, ENT_QUOTES, 'UTF-8') . '">
            ' . $fixedRoleNotice . '

            <div class="create-user-grid">
                <div class="create-user-field">
                    <label for="create_user_name">Nombre completo</label>
                    <input type="text" id="create_user_name" name="name" required maxlength="80" autocomplete="name" placeholder="Ej. Juan Pérez" data-name-input>
                </div>

                <div class="create-user-field">
                    <label for="create_user_email">Correo</label>
                    <input type="email" id="create_user_email" name="email" required maxlength="120" autocomplete="email" placeholder="usuario@empresa.com">
                </div>

                <div class="create-user-field">
                    <label for="create_user_role">Rol</label>
                    <select id="create_user_role" name="role" required>
                        ' . $roleOptionsHtml . '
                    </select>
                </div>

                <div class="create-user-field create-user-tech-level" id="createUserTechLevelWrap">
                    <label for="create_user_tech_level">Nivel técnico</label>
                    <select id="create_user_tech_level" name="tech_level">
                        <option value="1">Nivel 1</option>
                        <option value="2">Nivel 2</option>
                        <option value="3">Nivel 3</option>
                    </select>
                </div>

                <div class="create-user-field">
                    <label for="create_user_phone_country">País del teléfono</label>
                    <select id="create_user_phone_country" name="phone_country" data-phone-country-for="create_user_phone">
                        <option value="PE" data-digits="9" selected>Perú (+51) · 9 dígitos</option>
                        <option value="CO" data-digits="10">Colombia (+57) · 10 dígitos</option>
                        <option value="MX" data-digits="10">México (+52) · 10 dígitos</option>
                        <option value="CL" data-digits="9">Chile (+56) · 9 dígitos</option>
                        <option value="EC" data-digits="10">Ecuador (+593) · 10 dígitos</option>
                        <option value="BO" data-digits="8">Bolivia (+591) · 8 dígitos</option>
                        <option value="AR" data-digits="10">Argentina (+54) · 10 dígitos</option>
                        <option value="US" data-digits="10">Estados Unidos (+1) · 10 dígitos</option>
                    </select>
                </div>

                <div class="create-user-field">
                    <label for="create_user_phone">Teléfono</label>
                    <input type="text" id="create_user_phone" name="phone" inputmode="numeric" pattern="[0-9]*" maxlength="9" autocomplete="tel-national" placeholder="Ej. 954874584" data-phone-input data-phone-help="create_user_phone_help">
                </div>

                <div class="create-user-field">
                    <label for="create_user_position">Cargo</label>
                    <input type="text" id="create_user_position" name="position" maxlength="80" autocomplete="organization-title" placeholder="Ej. Jefe de TI" data-position-input>
                </div>

                ' . $companySelectorHtml . '

                <div class="create-user-field">
                    <label for="create_user_password">Contraseña</label>
                    <input type="password" id="create_user_password" name="password" required minlength="' . $createUserPasswordMinimum . '" maxlength="128" autocomplete="new-password" placeholder="Mínimo ' . $createUserPasswordMinimum . ' caracteres">
                </div>

                <div class="create-user-field">
                    <label for="create_user_password_confirmation">Confirmar contraseña</label>
                    <input type="password" id="create_user_password_confirmation" name="password_confirmation" required minlength="' . $createUserPasswordMinimum . '" maxlength="128" autocomplete="new-password" placeholder="Repite la contraseña">
                </div>

                <div class="create-user-field create-user-full">
                    <small class="create-user-help"><strong>Política vigente:</strong> ' . htmlspecialchars($createUserPasswordRules, ENT_QUOTES, 'UTF-8') . '</small>
                </div>
            </div>

            <div class="create-user-actions">
                <button type="button" class="create-user-cancel-btn" id="cancelCreateUserModal">Cancelar</button>
                <button type="submit" class="create-user-submit-btn">
                    <i class="fa-solid fa-check"></i>
                    Guardar usuario
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("createUserModal");
    const openBtn = document.getElementById("openCreateUserModal");
    const closeBtn = document.getElementById("closeCreateUserModal");
    const cancelBtn = document.getElementById("cancelCreateUserModal");
    const roleSelect = document.getElementById("create_user_role");
    const techLevelWrap = document.getElementById("createUserTechLevelWrap");
    const clientOnlyBlocks = document.querySelectorAll(".create-user-client-only");
    const phoneInput = document.getElementById("create_user_phone");
    const phoneCountry = document.getElementById("create_user_phone_country");
    const phoneHelp = document.getElementById("create_user_phone_help");
    const nameInputs = document.querySelectorAll("[data-name-input]");
    const positionInputs = document.querySelectorAll("[data-position-input]");
    const businessInputs = document.querySelectorAll("[data-business-input]");

    function cleanNameValue(value) {
        return value
            .replace(/[^\p{L}\s\'.-]/gu, "")
            .replace(/\s{2,}/g, " ")
            .slice(0, 80);
    }

    function cleanPositionValue(value) {
        return value
            .replace(/[^\p{L}\p{N}\s().,\/#&+_-]/gu, "")
            .replace(/\s{2,}/g, " ")
            .slice(0, 80);
    }

    function cleanBusinessValue(value) {
        return value
            .replace(/[^\p{L}\p{N}\s.,&\'()\/#_+-]/gu, "")
            .replace(/\s{2,}/g, " ")
            .slice(0, 150);
    }

    function attachCleaner(inputs, cleaner) {
        inputs.forEach(function (input) {
            input.addEventListener("input", function () {
                const cursor = input.selectionStart;
                const originalLength = input.value.length;
                input.value = cleaner(input.value);
                const diff = originalLength - input.value.length;
                if (cursor !== null) input.setSelectionRange(Math.max(cursor - diff, 0), Math.max(cursor - diff, 0));
            });
        });
    }

    function syncPhoneRules() {
        if (!phoneInput || !phoneCountry) return;

        const selectedOption = phoneCountry.options[phoneCountry.selectedIndex];
        const digits = selectedOption ? parseInt(selectedOption.dataset.digits || "9", 10) : 9;
        const countryLabel = selectedOption ? selectedOption.textContent.split("·")[0].trim() : "Perú (+51)";

        phoneInput.maxLength = digits;
        phoneInput.pattern = "[0-9]{" + digits + "}";
        phoneInput.title = "Ingresa exactamente " + digits + " números para " + countryLabel + ".";
        phoneInput.value = phoneInput.value.replace(/\D/g, "").slice(0, digits);

        if (phoneHelp) {
            phoneHelp.textContent = "Solo números. " + countryLabel + " requiere exactamente " + digits + " dígitos.";
        }
    }

    function cleanPhoneInput() {
        if (!phoneInput) return;
        const maxLength = parseInt(phoneInput.maxLength || "9", 10);
        phoneInput.value = phoneInput.value.replace(/\D/g, "").slice(0, maxLength);
    }

    function openModal() {
        if (!modal) return;
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("modal-open");
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
    }

    function syncRoleFields() {
        if (!roleSelect) return;

        const isTech = roleSelect.value === "TECH";
        const isClient = roleSelect.value === "CLIENT";

        if (techLevelWrap) {
            techLevelWrap.style.display = isTech ? "block" : "none";
        }

        clientOnlyBlocks.forEach(function (block) {
            block.style.display = isClient ? "block" : "none";
        });
    }

    attachCleaner(nameInputs, cleanNameValue);
    attachCleaner(positionInputs, cleanPositionValue);
    attachCleaner(businessInputs, cleanBusinessValue);

    if (openBtn) openBtn.addEventListener("click", openModal);
    if (closeBtn) closeBtn.addEventListener("click", closeModal);
    if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
    if (roleSelect) roleSelect.addEventListener("change", syncRoleFields);
    if (phoneInput) phoneInput.addEventListener("input", cleanPhoneInput);
    if (phoneCountry) phoneCountry.addEventListener("change", syncPhoneRules);

    if (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) closeModal();
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal && modal.classList.contains("show")) closeModal();
    });

    syncRoleFields();
    syncPhoneRules();
});
</script>
';

    if (stripos($pageContent, '</body>') !== false) {
        $pageContent = str_ireplace('</body>', $createUserWidget . '</body>', $pageContent);
    } else {
        $pageContent .= $createUserWidget;
    }
}

echo $pageContent;
