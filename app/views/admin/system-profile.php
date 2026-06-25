<?php
$systemProfile = $systemProfile ?? [];
$systemProfileReady = (bool) ($systemProfileReady ?? false);
$systemProfileLogoUrl = $systemProfileLogoUrl ?? null;
$systemProfileCsrfToken = $systemProfileCsrfToken ?? '';

$title = 'Perfil del sistema';
$activePage = 'system-profile';
$pageTitle = 'Perfil del sistema';
$pageSubtitle = 'Centraliza la identidad institucional que luego utilizarán los reportes, correos y pantallas.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-settings.php',
        'class' => 'btn-secondary',
        'text' => 'Volver a Ajustes',
    ],
];

function systemProfileViewSafe(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$updatedAt = trim((string) ($systemProfile['updated_at'] ?? ''));
$updatedByName = trim((string) ($systemProfile['updated_by_name'] ?? ''));
$companyName = trim((string) ($systemProfile['company_name'] ?? ''));
$commercialName = trim((string) ($systemProfile['commercial_name'] ?? ''));
$systemName = trim((string) ($systemProfile['system_name'] ?? ''));
$slogan = trim((string) ($systemProfile['slogan'] ?? ''));

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-settings-content system-profile-content">
            <section class="system-profile-hero">
                <div>
                    <span class="settings-eyebrow">Identidad institucional</span>
                    <h2>Un solo perfil para toda la plataforma</h2>
                    <p>Los datos guardados aquí podrán reutilizarse en reportes PDF, notificaciones, correos y documentos sin modificar archivos PHP.</p>
                </div>

                <div class="system-profile-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-building-shield"></i>
                </div>
            </section>

            <?php if (!$systemProfileReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Falta preparar la base de datos</strong>
                        <p>Ejecuta el archivo <code>database/system_profile.sql</code> en phpMyAdmin. Después recarga esta página para habilitar el formulario.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-profile-layout">
                <form
                    action="/helpdesk-php/update-system-profile.php"
                    method="POST"
                    enctype="multipart/form-data"
                    class="system-profile-form"
                    id="systemProfileForm">

                    <input type="hidden" name="csrf_token" value="<?= systemProfileViewSafe($systemProfileCsrfToken) ?>">

                    <fieldset <?= !$systemProfileReady ? 'disabled' : '' ?>>
                        <section class="system-profile-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">01</span>
                                <div>
                                    <h3>Identidad institucional</h3>
                                    <p>Define cómo se reconoce la empresa y la plataforma.</p>
                                </div>
                            </div>

                            <div class="system-profile-grid two-columns">
                                <div class="form-group">
                                    <label for="company_name">Razón social <span>*</span></label>
                                    <input
                                        type="text"
                                        id="company_name"
                                        name="company_name"
                                        maxlength="180"
                                        value="<?= systemProfileViewSafe($systemProfile['company_name'] ?? '') ?>"
                                        placeholder="Ej. PRONET SYSTEM S.A.C."
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="commercial_name">Nombre comercial</label>
                                    <input
                                        type="text"
                                        id="commercial_name"
                                        name="commercial_name"
                                        maxlength="150"
                                        value="<?= systemProfileViewSafe($systemProfile['commercial_name'] ?? '') ?>"
                                        placeholder="Ej. Pronet System">
                                </div>

                                <div class="form-group">
                                    <label for="system_name">Nombre del sistema <span>*</span></label>
                                    <input
                                        type="text"
                                        id="system_name"
                                        name="system_name"
                                        maxlength="120"
                                        value="<?= systemProfileViewSafe($systemProfile['system_name'] ?? '') ?>"
                                        placeholder="Ej. Mesa de Ayuda"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="ruc">RUC</label>
                                    <input
                                        type="text"
                                        id="ruc"
                                        name="ruc"
                                        inputmode="numeric"
                                        maxlength="11"
                                        value="<?= systemProfileViewSafe($systemProfile['ruc'] ?? '') ?>"
                                        placeholder="11 dígitos">
                                </div>

                                <div class="form-group system-profile-full-field">
                                    <label for="slogan">Eslogan institucional</label>
                                    <input
                                        type="text"
                                        id="slogan"
                                        name="slogan"
                                        maxlength="180"
                                        value="<?= systemProfileViewSafe($systemProfile['slogan'] ?? '') ?>"
                                        placeholder="Ej. Tecnología que mantiene tu operación conectada">
                                </div>
                            </div>
                        </section>

                        <section class="system-profile-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">02</span>
                                <div>
                                    <h3>Contacto corporativo</h3>
                                    <p>Información oficial para documentos y comunicaciones.</p>
                                </div>
                            </div>

                            <div class="system-profile-grid two-columns">
                                <div class="form-group">
                                    <label for="corporate_email">Correo corporativo</label>
                                    <input
                                        type="email"
                                        id="corporate_email"
                                        name="corporate_email"
                                        maxlength="150"
                                        value="<?= systemProfileViewSafe($systemProfile['corporate_email'] ?? '') ?>"
                                        placeholder="soporte@empresa.com">
                                </div>

                                <div class="form-group">
                                    <label for="phone">Teléfono</label>
                                    <input
                                        type="text"
                                        id="phone"
                                        name="phone"
                                        maxlength="25"
                                        value="<?= systemProfileViewSafe($systemProfile['phone'] ?? '') ?>"
                                        placeholder="Ej. +51 987 654 321">
                                </div>

                                <div class="form-group system-profile-full-field">
                                    <label for="website">Sitio web</label>
                                    <input
                                        type="url"
                                        id="website"
                                        name="website"
                                        maxlength="255"
                                        value="<?= systemProfileViewSafe($systemProfile['website'] ?? '') ?>"
                                        placeholder="https://www.empresa.com">
                                </div>

                                <div class="form-group system-profile-full-field">
                                    <label for="address">Dirección</label>
                                    <input
                                        type="text"
                                        id="address"
                                        name="address"
                                        maxlength="255"
                                        value="<?= systemProfileViewSafe($systemProfile['address'] ?? '') ?>"
                                        placeholder="Dirección institucional">
                                </div>
                            </div>
                        </section>

                        <section class="system-profile-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">03</span>
                                <div>
                                    <h3>Presentación y logotipo</h3>
                                    <p>Agrega una descripción breve y el recurso visual principal.</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Descripción de la empresa</label>
                                <textarea
                                    id="description"
                                    name="description"
                                    maxlength="1500"
                                    rows="5"
                                    placeholder="Describe brevemente a la empresa y sus servicios."><?= systemProfileViewSafe($systemProfile['description'] ?? '') ?></textarea>
                                <small class="system-profile-help"><span id="descriptionCounter">0</span>/1500 caracteres</small>
                            </div>

                            <div class="system-profile-logo-editor">
                                <div class="system-profile-logo-preview" id="systemLogoPreviewBox">
                                    <?php if ($systemProfileLogoUrl): ?>
                                        <img
                                            src="<?= systemProfileViewSafe($systemProfileLogoUrl) ?>"
                                            alt="Logo institucional actual"
                                            id="systemLogoPreview"
                                            data-original-src="<?= systemProfileViewSafe($systemProfileLogoUrl) ?>">
                                    <?php else: ?>
                                        <div class="system-profile-logo-placeholder" id="systemLogoPlaceholder">
                                            <i class="fa-regular fa-image"></i>
                                            <span>Sin logo</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="system-profile-logo-controls">
                                    <label class="system-profile-upload-button" for="logo">
                                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                        <span>Seleccionar logo</span>
                                    </label>
                                    <input
                                        type="file"
                                        id="logo"
                                        name="logo"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="system-profile-file-input">

                                    <strong id="systemLogoFileName">Ningún archivo nuevo seleccionado</strong>
                                    <small>Formatos permitidos: PNG, JPG o WEBP. Tamaño máximo: 2 MB.</small>

                                    <?php if (!empty($systemProfile['logo_path'])): ?>
                                        <label class="system-profile-remove-logo">
                                            <input type="checkbox" name="remove_logo" id="remove_logo" value="1">
                                            Quitar el logo actual al guardar
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <div class="system-profile-actions">
                            <button type="reset" class="btn-secondary" id="resetSystemProfileForm">
                                <i class="fa-solid fa-rotate-left"></i>
                                Restablecer
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Guardar cambios
                            </button>
                        </div>
                    </fieldset>
                </form>

                <aside class="system-profile-aside">
                    <section class="system-profile-preview-card">
                        <span class="settings-eyebrow">Vista previa</span>

                        <div class="system-profile-brand-preview">
                            <div class="system-profile-brand-logo" id="brandPreviewLogo">
                                <?php if ($systemProfileLogoUrl): ?>
                                    <img src="<?= systemProfileViewSafe($systemProfileLogoUrl) ?>" alt="Logo de vista previa">
                                <?php else: ?>
                                    <i class="fa-solid fa-building"></i>
                                <?php endif; ?>
                            </div>

                            <div>
                                <strong id="brandPreviewCompany"><?= systemProfileViewSafe($companyName !== '' ? $companyName : 'Nombre de la empresa') ?></strong>
                                <span id="brandPreviewCommercial"><?= systemProfileViewSafe($commercialName !== '' ? $commercialName : 'Nombre comercial') ?></span>
                            </div>
                        </div>

                        <div class="system-profile-system-preview">
                            <small>Plataforma</small>
                            <strong id="brandPreviewSystem"><?= systemProfileViewSafe($systemName !== '' ? $systemName : 'Nombre del sistema') ?></strong>
                            <p id="brandPreviewSlogan"><?= systemProfileViewSafe($slogan !== '' ? $slogan : 'El eslogan aparecerá en este espacio.') ?></p>
                        </div>
                    </section>

                    <section class="system-profile-meta-card">
                        <div class="system-profile-meta-heading">
                            <span><i class="fa-solid fa-clock-rotate-left"></i></span>
                            <div>
                                <strong>Última actualización</strong>
                                <small>Control de cambios del perfil</small>
                            </div>
                        </div>

                        <dl>
                            <div>
                                <dt>Fecha</dt>
                                <dd><?= $updatedAt !== '' ? systemProfileViewSafe(date('d/m/Y H:i', strtotime($updatedAt))) : 'Aún no registrado' ?></dd>
                            </div>
                            <div>
                                <dt>Responsable</dt>
                                <dd><?= systemProfileViewSafe($updatedByName !== '' ? $updatedByName : 'Sin modificaciones') ?></dd>
                            </div>
                            <div>
                                <dt>Estado</dt>
                                <dd><span class="system-profile-state <?= $systemProfileReady ? 'is-ready' : '' ?>"><?= $systemProfileReady ? 'Configuración activa' : 'Pendiente de instalación' ?></span></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="system-profile-info-card">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>¿Dónde se utilizará?</strong>
                            <p>Más adelante este perfil alimentará automáticamente los reportes PDF, correos, encabezados y documentos institucionales.</p>
                        </div>
                    </section>
                </aside>
            </section>
        </main>
    </div>
</div>

<script src="/helpdesk-php/public/assets/js/admin-system-profile.js?v=20260624-1"></script>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
