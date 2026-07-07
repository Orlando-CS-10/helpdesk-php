<?php
$userItem = $userItem ?? [];
if (!is_array($userItem) || empty($userItem)) {
    header('Location: /helpdesk-php/admin-users.php');
    exit;
}

$title = 'Editar usuario';
$activePage = 'users';
$pageTitle = 'Editar usuario';
$pageSubtitle = 'Actualiza los datos del usuario, su empresa cliente y los permisos corporativos.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-users.php',
        'class' => 'btn-secondary',
        'text' => 'Volver a usuarios'
    ]
];

function editUserCompanyName(array $userItem): string
{
    $tradeName = trim((string)($userItem['company_trade_name'] ?? ''));
    $businessName = trim((string)($userItem['company_business_name'] ?? ''));
    $legacyCompany = trim((string)($userItem['company'] ?? ''));

    if ($tradeName !== '') {
        return $tradeName;
    }

    if ($businessName !== '') {
        return $businessName;
    }

    return $legacyCompany !== '' ? $legacyCompany : '-';
}

function editUserViewSlaContractLabel(?string $contract): string
{
    return match ($contract) {
        '24_7' => '24/7 - Atención continua',
        '8_5' => '8/5 - Horario laboral',
        default => '-',
    };
}

$companyModuleReady = $companyModuleReady ?? false;
$companyOptions = $companyOptions ?? [];
$allowedRoles = $allowedRoles ?? ['CLIENT'];
$currentRole = $currentRole ?? '';
$hasTechLevelColumn = $hasTechLevelColumn ?? false;
$hasCanViewCompanyTicketsColumn = $hasCanViewCompanyTicketsColumn ?? false;

$roleLabels = [
    'CLIENT' => 'Cliente',
    'TECH' => 'Técnico',
    'ADMIN' => 'Administrador',
];

$selectedRole = $userItem['role'] ?? 'CLIENT';
$selectedCompanyId = (int)($userItem['company_id'] ?? 0);
$selectedTechLevel = (int)($userItem['tech_level'] ?? 1);
if ($selectedTechLevel < 1 || $selectedTechLevel > 3) {
    $selectedTechLevel = 1;
}

function editUserDetectPhoneCountry(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone) ?? '';

    return match (strlen($digits)) {
        8 => 'BO',
        9 => 'PE',
        10 => 'CO',
        default => 'PE',
    };
}

$selectedPhoneCountry = editUserDetectPhoneCountry($userItem['phone'] ?? '');


function editUserPhotoUrl(?string $photo): ?string
{
    $photo = trim((string)$photo);

    if ($photo === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $photo)) {
        return $photo;
    }

    if (str_starts_with($photo, '/')) {
        return $photo;
    }

    $photo = ltrim($photo, '/');

    if (str_starts_with($photo, 'public/')) {
        return '/helpdesk-php/' . $photo;
    }

    return '/helpdesk-php/public/uploads/users/' . $photo;
}

$editUserProfilePhotoUrl = editUserPhotoUrl($userItem['profile_photo'] ?? null);
$editUserInitial = strtoupper(substr((string)($userItem['name'] ?? 'U'), 0, 1));

require_once __DIR__ . '/../layouts/header.php';
?>

<link rel="stylesheet" href="/helpdesk-php/public/assets/css/create-user-modal.css">
<link rel="stylesheet" href="/helpdesk-php/public/assets/css/edit-user-empresa.css">

<div class="admin-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content">
            <?php if (!empty($_SESSION['user_success'])): ?>
                <section class="card edit-user-alert edit-user-alert-success">
                    <strong><?= htmlspecialchars($_SESSION['user_success']) ?></strong>
                </section>
                <?php unset($_SESSION['user_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['user_error'])): ?>
                <section class="card edit-user-alert edit-user-alert-error">
                    <strong><?= htmlspecialchars($_SESSION['user_error']) ?></strong>
                </section>
                <?php unset($_SESSION['user_error']); ?>
            <?php endif; ?>

            <?php if (empty($companyModuleReady)): ?>
                <section class="card edit-user-alert edit-user-alert-warning">
                    <h3>Módulo de empresas pendiente</h3>
                    <p>Ejecuta la migración SQL de empresas cliente para activar RUC, contrato SLA 24/7 o 8/5 y vinculación corporativa.</p>
                </section>
            <?php endif; ?>

            <section class="card edit-user-card">
                <div class="edit-user-header">
                    <div>
                        <span class="ticket-detail-code">Usuario #<?= (int)$userItem['id'] ?></span>
                        <h2><?= htmlspecialchars($userItem['name'] ?? 'Usuario') ?></h2>
                        <p>Modifica el contacto, rol, nivel técnico y empresa cliente vinculada.</p>
                    </div>

                    <div class="edit-user-summary-card">
                        <span>Empresa actual</span>
                        <strong><?= htmlspecialchars(editUserCompanyName($userItem)) ?></strong>
                        <?php if (!empty($userItem['company_ruc'])): ?>
                            <small>RUC: <?= htmlspecialchars($userItem['company_ruc']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($userItem['sla_contract_type'])): ?>
                            <small>SLA: <?= htmlspecialchars(editUserViewSlaContractLabel($userItem['sla_contract_type'])) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="/helpdesk-php/update-user.php" method="POST" class="create-user-form edit-user-form" autocomplete="off" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= (int)$userItem['id'] ?>">

                    <div class="edit-user-photo-panel">
                        <div class="edit-user-photo-preview <?= $editUserProfilePhotoUrl ? 'has-photo' : '' ?>" id="editUserPhotoPreview">
                            <?php if ($editUserProfilePhotoUrl): ?>
                                <img src="<?= htmlspecialchars($editUserProfilePhotoUrl) ?>" alt="Foto de <?= htmlspecialchars($userItem['name'] ?? 'Usuario') ?>" id="editUserPhotoPreviewImg">
                                <span id="editUserPhotoPreviewInitial" class="is-hidden"><?= htmlspecialchars($editUserInitial) ?></span>
                            <?php else: ?>
                                <img src="" alt="" id="editUserPhotoPreviewImg" class="is-hidden">
                                <span id="editUserPhotoPreviewInitial"><?= htmlspecialchars($editUserInitial) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="edit-user-photo-info">
                            <strong>Foto de perfil</strong>
                            <p>Sube una imagen JPG, PNG o WEBP. Tamaño máximo recomendado: 2 MB.</p>

                            <div class="edit-user-photo-actions">
                                <label class="edit-user-photo-upload-btn" for="edit_user_profile_photo">
                                    <i class="fa-solid fa-camera"></i>
                                    Seleccionar foto
                                </label>
                                <input
                                    type="file"
                                    id="edit_user_profile_photo"
                                    name="profile_photo"
                                    accept="image/jpeg,image/png,image/webp">

                                <?php if ($editUserProfilePhotoUrl): ?>
                                    <label class="edit-user-photo-remove">
                                        <input type="checkbox" name="remove_profile_photo" value="1">
                                        Quitar foto actual
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="create-user-grid">
                        <div class="create-user-field">
                            <label for="edit_user_name">Nombre completo</label>
                            <input
                                type="text"
                                id="edit_user_name"
                                name="name"
                                value="<?= htmlspecialchars($userItem['name'] ?? '') ?>"
                                required
                                maxlength="80"
                                autocomplete="name"
                                data-name-input>
                        </div>

                        <div class="create-user-field">
                            <label for="edit_user_email">Correo</label>
                            <input
                                type="email"
                                id="edit_user_email"
                                name="email"
                                value="<?= htmlspecialchars($userItem['email'] ?? '') ?>"
                                required
                                maxlength="120"
                                autocomplete="email">
                        </div>

                        <div class="create-user-field">
                            <label for="edit_user_role">Rol</label>
                            <select id="edit_user_role" name="role" required>
                                <?php foreach ($allowedRoles as $roleOption): ?>
                                    <option value="<?= htmlspecialchars($roleOption) ?>" <?= $selectedRole === $roleOption ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($roleLabels[$roleOption] ?? $roleOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($currentRole === 'TECH'): ?>
                                <small>Como técnico, solo puedes editar contactos clientes.</small>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasTechLevelColumn): ?>
                            <div class="create-user-field" id="editUserTechLevelWrap">
                                <label for="edit_user_tech_level">Nivel técnico</label>
                                <select id="edit_user_tech_level" name="tech_level">
                                    <option value="1" <?= $selectedTechLevel === 1 ? 'selected' : '' ?>>Nivel 1</option>
                                    <option value="2" <?= $selectedTechLevel === 2 ? 'selected' : '' ?>>Nivel 2</option>
                                    <option value="3" <?= $selectedTechLevel === 3 ? 'selected' : '' ?>>Nivel 3</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="create-user-field">
                            <label for="edit_user_phone_country">País del teléfono</label>
                            <select id="edit_user_phone_country" name="phone_country" data-phone-country-for="edit_user_phone">
                                <option value="PE" data-digits="9" <?= $selectedPhoneCountry === 'PE' ? 'selected' : '' ?>>Perú (+51) · 9 dígitos</option>
                                <option value="CO" data-digits="10" <?= $selectedPhoneCountry === 'CO' ? 'selected' : '' ?>>Colombia (+57) · 10 dígitos</option>
                                <option value="MX" data-digits="10" <?= $selectedPhoneCountry === 'MX' ? 'selected' : '' ?>>México (+52) · 10 dígitos</option>
                                <option value="CL" data-digits="9" <?= $selectedPhoneCountry === 'CL' ? 'selected' : '' ?>>Chile (+56) · 9 dígitos</option>
                                <option value="EC" data-digits="10" <?= $selectedPhoneCountry === 'EC' ? 'selected' : '' ?>>Ecuador (+593) · 10 dígitos</option>
                                <option value="BO" data-digits="8" <?= $selectedPhoneCountry === 'BO' ? 'selected' : '' ?>>Bolivia (+591) · 8 dígitos</option>
                                <option value="AR" data-digits="10" <?= $selectedPhoneCountry === 'AR' ? 'selected' : '' ?>>Argentina (+54) · 10 dígitos</option>
                                <option value="US" data-digits="10" <?= $selectedPhoneCountry === 'US' ? 'selected' : '' ?>>Estados Unidos (+1) · 10 dígitos</option>
                            </select>
                        </div>

                        <div class="create-user-field">
                            <label for="edit_user_phone">Teléfono</label>
                            <input
                                type="text"
                                id="edit_user_phone"
                                name="phone"
                                value="<?= htmlspecialchars(preg_replace('/\D+/', '', (string)($userItem['phone'] ?? '')) ?? '') ?>"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="9"
                                autocomplete="tel-national"
                                data-phone-input
                                data-phone-help="edit_user_phone_help"
                                placeholder="Ej. 954874584">
                        </div>

                        <div class="create-user-field">
                            <label for="edit_user_position">Cargo</label>
                            <input
                                type="text"
                                id="edit_user_position"
                                name="position"
                                value="<?= htmlspecialchars($userItem['position'] ?? '') ?>"
                                maxlength="80"
                                autocomplete="organization-title"
                                data-position-input
                                placeholder="Ej. Jefe de TI">
                        </div>

                        <?php if ($companyModuleReady): ?>
                            <div class="create-user-field create-user-full" id="editClientCompanyWrap">
                                <label for="edit_user_company_id">Empresa cliente</label>
                                <select id="edit_user_company_id" name="company_id">
                                    <option value="">Selecciona una empresa cliente</option>
                                    <?php foreach ($companyOptions as $companyOption): ?>
                                        <?php
                                        $optionText = $companyOption['business_name'] ?? '';
                                        if (!empty($companyOption['ruc'])) {
                                            $optionText .= ' - RUC ' . $companyOption['ruc'];
                                        }
                                        $optionText .= ' - SLA ' . editUserViewSlaContractLabel($companyOption['sla_contract_type'] ?? null);
                                        ?>
                                        <option value="<?= (int)$companyOption['id'] ?>" <?= $selectedCompanyId === (int)$companyOption['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optionText) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>La empresa seleccionada define si el SLA corre en 24/7 o 8/5.</small>

                                <?php if ($hasCanViewCompanyTicketsColumn): ?>
                                    <label class="create-user-check-row">
                                        <input
                                            type="checkbox"
                                            name="can_view_company_tickets"
                                            value="1"
                                            <?= (int)($userItem['can_view_company_tickets'] ?? 0) === 1 ? 'checked' : '' ?>>
                                        Este contacto puede ver todos los tickets de su empresa.
                                    </label>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="create-user-field create-user-full" id="editClientCompanyWrap">
                                <label for="edit_user_company">Empresa</label>
                                <input
                                    type="text"
                                    id="edit_user_company"
                                    name="company"
                                    value="<?= htmlspecialchars($userItem['company'] ?? '') ?>"
                                    maxlength="150"
                                    autocomplete="organization"
                                    data-business-input
                                    placeholder="Ej. Ferreyros">
                                <small>Cuando actives el módulo de empresas, este campo será reemplazado por empresa cliente vinculada.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="create-user-actions">
                        <a href="/helpdesk-php/admin-users.php" class="create-user-cancel-btn">Cancelar</a>
                        <button type="submit" class="create-user-submit-btn">
                            <i class="fa-solid fa-check"></i>
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const roleSelect = document.getElementById("edit_user_role");
    const techLevelWrap = document.getElementById("editUserTechLevelWrap");
    const companyWrap = document.getElementById("editClientCompanyWrap");
    const companySelect = document.getElementById("edit_user_company_id");
    const legacyCompanyInput = document.getElementById("edit_user_company");
    const phoneInput = document.getElementById("edit_user_phone");
    const phoneCountry = document.getElementById("edit_user_phone_country");
    const phoneHelp = document.getElementById("edit_user_phone_help");
    const nameInputs = document.querySelectorAll("[data-name-input]");
    const positionInputs = document.querySelectorAll("[data-position-input]");
    const businessInputs = document.querySelectorAll("[data-business-input]");

    function cleanNameValue(value) {
        return value
            .replace(/[^\p{L}\s'.-]/gu, "")
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
            .replace(/[^\p{L}\p{N}\s.,&'()\/#_+-]/gu, "")
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

    function setRequired(element, required) {
        if (!element) return;
        if (required) element.setAttribute("required", "required");
        else element.removeAttribute("required");
    }

    function syncRoleFields() {
        if (!roleSelect) return;

        const isTech = roleSelect.value === "TECH";
        const isClient = roleSelect.value === "CLIENT";

        if (techLevelWrap) techLevelWrap.style.display = isTech ? "grid" : "none";
        if (companyWrap) companyWrap.style.display = isClient ? "grid" : "none";

        setRequired(companySelect, isClient);
        setRequired(legacyCompanyInput, isClient);
    }

    const photoInput = document.getElementById("edit_user_profile_photo");
    const photoPreview = document.getElementById("editUserPhotoPreview");
    const photoPreviewImg = document.getElementById("editUserPhotoPreviewImg");
    const photoPreviewInitial = document.getElementById("editUserPhotoPreviewInitial");

    if (photoInput && photoPreview && photoPreviewImg && photoPreviewInitial) {
        photoInput.addEventListener("change", function () {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) return;

            if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
                alert("Selecciona una imagen JPG, PNG o WEBP.");
                this.value = "";
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert("La imagen no debe superar los 2 MB.");
                this.value = "";
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                photoPreviewImg.src = event.target.result;
                photoPreviewImg.classList.remove("is-hidden");
                photoPreviewInitial.classList.add("is-hidden");
                photoPreview.classList.add("has-photo");
            };
            reader.readAsDataURL(file);
        });
    }

    attachCleaner(nameInputs, cleanNameValue);
    attachCleaner(positionInputs, cleanPositionValue);
    attachCleaner(businessInputs, cleanBusinessValue);

    if (roleSelect) roleSelect.addEventListener("change", syncRoleFields);
    if (phoneInput) phoneInput.addEventListener("input", cleanPhoneInput);
    if (phoneCountry) phoneCountry.addEventListener("change", syncPhoneRules);
    syncRoleFields();
    syncPhoneRules();
});
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
