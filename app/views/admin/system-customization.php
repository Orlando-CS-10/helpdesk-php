<?php
$systemCustomization = $systemCustomization ?? [];
$systemCustomizationReady = (bool) ($systemCustomizationReady ?? false);
$systemCustomizationCsrfToken = $systemCustomizationCsrfToken ?? '';

$title = 'Personalización del sistema';
$activePage = 'system-customization';
$pageTitle = 'Personalización del sistema';
$pageSubtitle = 'Configura la identidad visual y el comportamiento inicial del panel administrativo.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-settings.php',
        'class' => 'btn-secondary',
        'text' => 'Volver a Ajustes',
    ],
];

if (!function_exists('systemCustomizationViewSafe')) {
    function systemCustomizationViewSafe(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$primaryColor = (string) ($systemCustomization['primary_color'] ?? '#0f3d2e');
$secondaryColor = (string) ($systemCustomization['secondary_color'] ?? '#ff7a00');
$accentColor = (string) ($systemCustomization['accent_color'] ?? '#1f7a5a');
$theme = (string) ($systemCustomization['theme'] ?? 'light');
$sidebarDefault = (string) ($systemCustomization['sidebar_default'] ?? 'expanded');
$updatedAt = trim((string) ($systemCustomization['updated_at'] ?? ''));
$updatedByName = trim((string) ($systemCustomization['updated_by_name'] ?? ''));

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-settings-content system-customization-content">
            <section class="system-customization-hero">
                <div>
                    <span class="settings-eyebrow">Identidad visual</span>
                    <h2>Haz que el sistema vista los colores de la organización</h2>
                    <p>La paleta, el tema y el estado inicial del menú se aplicarán al panel desde un único lugar, sin editar cada pantalla manualmente.</p>
                </div>

                <div class="system-customization-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-palette"></i>
                </div>
            </section>

            <?php if (!$systemCustomizationReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Falta preparar la base de datos</strong>
                        <p>Ejecuta el archivo <code>database/system_customization.sql</code> en phpMyAdmin. Después recarga esta página para habilitar el formulario.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-customization-layout">
                <form
                    action="/helpdesk-php/update-system-customization.php"
                    method="POST"
                    class="system-customization-form"
                    id="systemCustomizationForm">

                    <input type="hidden" name="csrf_token" value="<?= systemCustomizationViewSafe($systemCustomizationCsrfToken) ?>">

                    <fieldset <?= !$systemCustomizationReady ? 'disabled' : '' ?>>
                        <section class="system-customization-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">01</span>
                                <div>
                                    <h3>Paleta institucional</h3>
                                    <p>Selecciona los colores que identificarán botones, navegación y elementos destacados.</p>
                                </div>
                            </div>

                            <div class="system-customization-color-grid">
                                <div class="system-customization-color-field">
                                    <div class="system-customization-color-heading">
                                        <span class="system-customization-color-dot" data-color-dot="primary_color"></span>
                                        <div>
                                            <label for="primary_color">Color principal</label>
                                            <small>Menú lateral, elementos institucionales y contraste.</small>
                                        </div>
                                    </div>

                                    <div class="system-customization-color-control">
                                        <input
                                            type="color"
                                            id="primary_color_picker"
                                            value="<?= systemCustomizationViewSafe($primaryColor) ?>"
                                            data-color-picker="primary_color"
                                            aria-label="Seleccionar color principal">
                                        <input
                                            type="text"
                                            id="primary_color"
                                            name="primary_color"
                                            maxlength="7"
                                            pattern="^#[0-9A-Fa-f]{6}$"
                                            value="<?= systemCustomizationViewSafe($primaryColor) ?>"
                                            data-color-text="primary_color"
                                            spellcheck="false"
                                            required>
                                    </div>
                                </div>

                                <div class="system-customization-color-field">
                                    <div class="system-customization-color-heading">
                                        <span class="system-customization-color-dot" data-color-dot="secondary_color"></span>
                                        <div>
                                            <label for="secondary_color">Color secundario</label>
                                            <small>Botones principales, estados activos y acciones.</small>
                                        </div>
                                    </div>

                                    <div class="system-customization-color-control">
                                        <input
                                            type="color"
                                            id="secondary_color_picker"
                                            value="<?= systemCustomizationViewSafe($secondaryColor) ?>"
                                            data-color-picker="secondary_color"
                                            aria-label="Seleccionar color secundario">
                                        <input
                                            type="text"
                                            id="secondary_color"
                                            name="secondary_color"
                                            maxlength="7"
                                            pattern="^#[0-9A-Fa-f]{6}$"
                                            value="<?= systemCustomizationViewSafe($secondaryColor) ?>"
                                            data-color-text="secondary_color"
                                            spellcheck="false"
                                            required>
                                    </div>
                                </div>

                                <div class="system-customization-color-field">
                                    <div class="system-customization-color-heading">
                                        <span class="system-customization-color-dot" data-color-dot="accent_color"></span>
                                        <div>
                                            <label for="accent_color">Color de acento</label>
                                            <small>Indicadores, apoyos visuales y detalles complementarios.</small>
                                        </div>
                                    </div>

                                    <div class="system-customization-color-control">
                                        <input
                                            type="color"
                                            id="accent_color_picker"
                                            value="<?= systemCustomizationViewSafe($accentColor) ?>"
                                            data-color-picker="accent_color"
                                            aria-label="Seleccionar color de acento">
                                        <input
                                            type="text"
                                            id="accent_color"
                                            name="accent_color"
                                            maxlength="7"
                                            pattern="^#[0-9A-Fa-f]{6}$"
                                            value="<?= systemCustomizationViewSafe($accentColor) ?>"
                                            data-color-text="accent_color"
                                            spellcheck="false"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="system-customization-presets" aria-label="Paletas predeterminadas">
                                <span>Paletas rápidas</span>
                                <div>
                                    <button type="button" data-preset-primary="#0f3d2e" data-preset-secondary="#ff7a00" data-preset-accent="#1f7a5a">
                                        <i style="--preset-a:#0f3d2e;--preset-b:#ff7a00;--preset-c:#1f7a5a"></i>
                                        Original
                                    </button>
                                    <button type="button" data-preset-primary="#123b5d" data-preset-secondary="#14b8a6" data-preset-accent="#38bdf8">
                                        <i style="--preset-a:#123b5d;--preset-b:#14b8a6;--preset-c:#38bdf8"></i>
                                        Océano
                                    </button>
                                    <button type="button" data-preset-primary="#312e81" data-preset-secondary="#8b5cf6" data-preset-accent="#ec4899">
                                        <i style="--preset-a:#312e81;--preset-b:#8b5cf6;--preset-c:#ec4899"></i>
                                        Violeta
                                    </button>
                                    <button type="button" data-preset-primary="#17324d" data-preset-secondary="#2563eb" data-preset-accent="#0ea5e9">
                                        <i style="--preset-a:#17324d;--preset-b:#2563eb;--preset-c:#0ea5e9"></i>
                                        Corporativa
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section class="system-customization-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">02</span>
                                <div>
                                    <h3>Apariencia del panel</h3>
                                    <p>Define cómo se presentarán fondos, tarjetas y textos.</p>
                                </div>
                            </div>

                            <div class="system-customization-option-grid theme-options">
                                <label class="system-customization-option" data-option-card="theme">
                                    <input type="radio" name="theme" value="light" <?= $theme === 'light' ? 'checked' : '' ?>>
                                    <span class="system-customization-option-icon light-option"><i class="fa-solid fa-sun"></i></span>
                                    <span>
                                        <strong>Claro</strong>
                                        <small>Fondos luminosos y alto contraste para el uso diario.</small>
                                    </span>
                                    <i class="fa-solid fa-circle-check system-customization-check"></i>
                                </label>

                                <label class="system-customization-option" data-option-card="theme">
                                    <input type="radio" name="theme" value="dark" <?= $theme === 'dark' ? 'checked' : '' ?>>
                                    <span class="system-customization-option-icon dark-option"><i class="fa-solid fa-moon"></i></span>
                                    <span>
                                        <strong>Oscuro</strong>
                                        <small>Reduce el brillo y utiliza superficies oscuras en el panel.</small>
                                    </span>
                                    <i class="fa-solid fa-circle-check system-customization-check"></i>
                                </label>

                                <label class="system-customization-option" data-option-card="theme">
                                    <input type="radio" name="theme" value="auto" <?= $theme === 'auto' ? 'checked' : '' ?>>
                                    <span class="system-customization-option-icon auto-option"><i class="fa-solid fa-circle-half-stroke"></i></span>
                                    <span>
                                        <strong>Automático</strong>
                                        <small>Respeta el modo claro u oscuro configurado en el dispositivo.</small>
                                    </span>
                                    <i class="fa-solid fa-circle-check system-customization-check"></i>
                                </label>
                            </div>
                        </section>

                        <section class="system-customization-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">03</span>
                                <div>
                                    <h3>Menú administrativo</h3>
                                    <p>Selecciona el estado inicial para usuarios que todavía no hayan guardado una preferencia.</p>
                                </div>
                            </div>

                            <div class="system-customization-option-grid sidebar-options">
                                <label class="system-customization-option compact-option" data-option-card="sidebar_default">
                                    <input type="radio" name="sidebar_default" value="expanded" <?= $sidebarDefault === 'expanded' ? 'checked' : '' ?>>
                                    <span class="system-customization-sidebar-symbol expanded-symbol"><i></i><b></b></span>
                                    <span>
                                        <strong>Expandido</strong>
                                        <small>Muestra iconos y nombres de las secciones.</small>
                                    </span>
                                    <i class="fa-solid fa-circle-check system-customization-check"></i>
                                </label>

                                <label class="system-customization-option compact-option" data-option-card="sidebar_default">
                                    <input type="radio" name="sidebar_default" value="collapsed" <?= $sidebarDefault === 'collapsed' ? 'checked' : '' ?>>
                                    <span class="system-customization-sidebar-symbol collapsed-symbol"><i></i><b></b></span>
                                    <span>
                                        <strong>Contraído</strong>
                                        <small>Inicia mostrando únicamente los iconos del menú.</small>
                                    </span>
                                    <i class="fa-solid fa-circle-check system-customization-check"></i>
                                </label>
                            </div>

                            <div class="system-customization-browser-note">
                                <i class="fa-solid fa-circle-info"></i>
                                <p>La elección manual de cada usuario en su navegador tendrá prioridad sobre este valor predeterminado.</p>
                            </div>
                        </section>

                        <div class="system-customization-actions">
                            <button
                                type="submit"
                                name="action"
                                value="restore"
                                formnovalidate
                                class="system-customization-restore-button"
                                id="restoreSystemCustomization">
                                <i class="fa-solid fa-arrow-rotate-left"></i>
                                Restaurar diseño original
                            </button>

                            <div>
                                <button type="reset" class="btn-secondary" id="resetSystemCustomizationForm">
                                    <i class="fa-solid fa-rotate-left"></i>
                                    Deshacer cambios
                                </button>
                                <button type="submit" name="action" value="save" class="btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Guardar personalización
                                </button>
                            </div>
                        </div>
                    </fieldset>
                </form>

                <aside class="system-customization-aside">
                    <section class="system-customization-preview-card">
                        <div class="system-customization-preview-heading">
                            <div>
                                <span class="settings-eyebrow">Vista previa en vivo</span>
                                <strong id="previewThemeLabel">Tema claro</strong>
                            </div>
                            <span class="system-customization-live-dot">En vivo</span>
                        </div>

                        <div
                            class="system-customization-preview"
                            id="systemCustomizationPreview"
                            data-preview-theme="<?= systemCustomizationViewSafe($theme) ?>"
                            data-preview-sidebar="<?= systemCustomizationViewSafe($sidebarDefault) ?>"
                            style="--preview-primary:<?= systemCustomizationViewSafe($primaryColor) ?>;--preview-secondary:<?= systemCustomizationViewSafe($secondaryColor) ?>;--preview-accent:<?= systemCustomizationViewSafe($accentColor) ?>;">

                            <div class="customization-preview-sidebar">
                                <span class="customization-preview-logo"><i class="fa-solid fa-headset"></i></span>
                                <div class="customization-preview-nav">
                                    <span class="active"><i class="fa-solid fa-house"></i><b>Panel</b></span>
                                    <span><i class="fa-solid fa-ticket"></i><b>Tickets</b></span>
                                    <span><i class="fa-solid fa-users"></i><b>Usuarios</b></span>
                                    <span><i class="fa-solid fa-gear"></i><b>Ajustes</b></span>
                                </div>
                            </div>

                            <div class="customization-preview-main">
                                <div class="customization-preview-topbar">
                                    <span><i></i><i></i><i></i></span>
                                    <b><i class="fa-solid fa-user"></i></b>
                                </div>
                                <div class="customization-preview-body">
                                    <small>RESUMEN DEL SISTEMA</small>
                                    <h4>Panel administrativo</h4>
                                    <p>Los componentes principales adoptarán la paleta seleccionada.</p>

                                    <div class="customization-preview-kpis">
                                        <span><b>24</b><small>Tickets</small></span>
                                        <span><b>08</b><small>Pendientes</small></span>
                                    </div>

                                    <div class="customization-preview-actions">
                                        <button type="button">Acción principal</button>
                                        <span>Acción secundaria</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="system-profile-meta-card">
                        <div class="system-profile-meta-heading">
                            <span><i class="fa-solid fa-clock-rotate-left"></i></span>
                            <div>
                                <strong>Última actualización</strong>
                                <small>Control de cambios visuales</small>
                            </div>
                        </div>

                        <dl>
                            <div>
                                <dt>Fecha</dt>
                                <dd><?= $updatedAt !== '' ? systemCustomizationViewSafe(date('d/m/Y H:i', strtotime($updatedAt))) : 'Aún no registrado' ?></dd>
                            </div>
                            <div>
                                <dt>Responsable</dt>
                                <dd><?= systemCustomizationViewSafe($updatedByName !== '' ? $updatedByName : 'Sin modificaciones') ?></dd>
                            </div>
                            <div>
                                <dt>Estado</dt>
                                <dd><span class="system-profile-state <?= $systemCustomizationReady ? 'is-ready' : '' ?>"><?= $systemCustomizationReady ? 'Personalización activa' : 'Pendiente de instalación' ?></span></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="system-profile-info-card">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <div>
                            <strong>Aplicación global</strong>
                            <p>Los colores y el tema se cargan desde el encabezado común. Así, el diseño se mantiene coherente en las pantallas administrativas.</p>
                        </div>
                    </section>
                </aside>
            </section>
        </main>
    </div>
</div>

<script src="/helpdesk-php/public/assets/js/admin-system-customization.js?v=20260624-1"></script>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
