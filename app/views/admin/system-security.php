<?php
$systemSecuritySettings = $systemSecuritySettings ?? [];
$systemSecurityReady = (bool) ($systemSecurityReady ?? false);
$systemSecurityCsrfToken = (string) ($systemSecurityCsrfToken ?? '');
$systemSecurityActionCsrfToken = (string) ($systemSecurityActionCsrfToken ?? '');
$systemSecurityLevel = $systemSecurityLevel ?? ['label' => 'Básico', 'class' => 'basic', 'score' => 0];
$systemSecuritySessions = $systemSecuritySessions ?? [];
$systemSecurityCompanies = $systemSecurityCompanies ?? [];
$systemSecurityCompanyTotal = (int) ($systemSecurityCompanyTotal ?? 0);
$systemSecurityGeneralLogs = $systemSecurityGeneralLogs ?? [];
$systemSecurityGeneralTotal = (int) ($systemSecurityGeneralTotal ?? 0);
$currentSecuritySessionToken = (string) ($currentSecuritySessionToken ?? '');

$title = 'Seguridad del sistema';
$activePage = 'system-security';
$pageTitle = 'Seguridad del sistema';
$pageSubtitle = 'Administra políticas de contraseñas, accesos, sesiones y auditoría.';

$adminTopbarButtons = [
    [
        'href' => '/helpdesk-php/admin-settings.php',
        'class' => 'btn-secondary',
        'text' => 'Volver a Ajustes',
    ],
];

if (!function_exists('securityViewSafe')) {
    function securityViewSafe(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('securityViewChecked')) {
    function securityViewChecked(mixed $value): string
    {
        return !empty($value) ? 'checked' : '';
    }
}

if (!function_exists('securityViewDate')) {
    function securityViewDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'Sin registro';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
    }
}


if (!function_exists('securityTraceInitials')) {
    function securityTraceInitials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
        }
        return function_exists('mb_strtoupper') ? mb_strtoupper($initials ?: 'EM', 'UTF-8') : strtoupper($initials ?: 'EM');
    }
}

if (!function_exists('securityTraceLogoUrl')) {
    function securityTraceLogoUrl(?string $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, '/helpdesk-php/')) {
            return $path;
        }
        return str_starts_with($path, '/') ? '/helpdesk-php' . $path : '/helpdesk-php/' . ltrim($path, '/');
    }
}

if (!function_exists('securityTraceCompanyName')) {
    function securityTraceCompanyName(array $company): string
    {
        return trim((string) ($company['trade_name'] ?? '')) !== ''
            ? trim((string) $company['trade_name'])
            : trim((string) ($company['business_name'] ?? 'Empresa'));
    }
}

$updatedAt = trim((string) ($systemSecuritySettings['updated_at'] ?? ''));
$updatedByName = trim((string) ($systemSecuritySettings['updated_by_name'] ?? ''));
$rulesText = function_exists('systemSecurityPasswordRulesText')
    ? systemSecurityPasswordRulesText($systemSecuritySettings)
    : 'mínimo 8 caracteres.';

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell admin-settings-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-settings-content system-security-content">
            <section class="system-security-hero">
                <div>
                    <span class="settings-eyebrow">Protección centralizada</span>
                    <h2>Refuerza la puerta de entrada del sistema</h2>
                    <p>Define reglas comunes para contraseñas, intentos fallidos, sesiones activas y eventos de auditoría desde un único panel.</p>
                </div>

                <div class="system-security-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </section>

            <?php if (!$systemSecurityReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>Falta preparar la base de datos</strong>
                        <p>Ejecuta <code>database/system_security.sql</code> en phpMyAdmin y recarga la página.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="system-security-layout">
                <form action="/helpdesk-php/update-system-security.php" method="POST" class="system-security-form" id="systemSecurityForm">
                    <input type="hidden" name="csrf_token" value="<?= securityViewSafe($systemSecurityCsrfToken) ?>">

                    <fieldset <?= !$systemSecurityReady ? 'disabled' : '' ?>>
                        <section class="system-security-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">01</span>
                                <div>
                                    <h3>Política de contraseñas</h3>
                                    <p>Establece el nivel mínimo que deben cumplir las nuevas contraseñas.</p>
                                </div>
                            </div>

                            <div class="system-security-grid two-columns">
                                <div class="form-group">
                                    <label for="min_password_length">Longitud mínima</label>
                                    <input type="number" id="min_password_length" name="min_password_length" min="6" max="64" value="<?= (int) ($systemSecuritySettings['min_password_length'] ?? 8) ?>" required>
                                    <small>Entre 6 y 64 caracteres.</small>
                                </div>

                                <div class="form-group">
                                    <label for="password_expiry_days">Renovación periódica</label>
                                    <div class="system-security-input-suffix">
                                        <input type="number" id="password_expiry_days" name="password_expiry_days" min="0" max="365" value="<?= (int) ($systemSecuritySettings['password_expiry_days'] ?? 0) ?>">
                                        <span>días</span>
                                    </div>
                                    <small>Usa 0 para no exigir vencimiento.</small>
                                </div>
                            </div>

                            <div class="system-security-switch-grid">
                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="require_uppercase" value="1" <?= securityViewChecked($systemSecuritySettings['require_uppercase'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Exigir mayúscula</strong><small>Al menos una letra en mayúscula.</small></span>
                                </label>

                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="require_lowercase" value="1" <?= securityViewChecked($systemSecuritySettings['require_lowercase'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Exigir minúscula</strong><small>Al menos una letra en minúscula.</small></span>
                                </label>

                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="require_number" value="1" <?= securityViewChecked($systemSecuritySettings['require_number'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Exigir número</strong><small>Al menos un dígito numérico.</small></span>
                                </label>

                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="require_special" value="1" <?= securityViewChecked($systemSecuritySettings['require_special'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Exigir carácter especial</strong><small>Por ejemplo: !, @, # o $.</small></span>
                                </label>

                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="block_common_passwords" value="1" <?= securityViewChecked($systemSecuritySettings['block_common_passwords'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Bloquear claves comunes</strong><small>Evita contraseñas predecibles.</small></span>
                                </label>

                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="force_change_on_create" value="1" <?= securityViewChecked($systemSecuritySettings['force_change_on_create'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Cambio al crear cuenta</strong><small>El usuario deberá definir una nueva clave al ingresar.</small></span>
                                </label>
                            </div>
                        </section>

                        <section class="system-security-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">02</span>
                                <div>
                                    <h3>Protección del inicio de sesión</h3>
                                    <p>Reduce ataques por repetición y bloquea temporalmente los accesos sospechosos.</p>
                                </div>
                            </div>

                            <div class="system-security-grid three-columns">
                                <div class="form-group">
                                    <label for="max_failed_attempts">Intentos permitidos</label>
                                    <input type="number" id="max_failed_attempts" name="max_failed_attempts" min="3" max="20" value="<?= (int) ($systemSecuritySettings['max_failed_attempts'] ?? 5) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="lockout_minutes">Tiempo de bloqueo</label>
                                    <div class="system-security-input-suffix">
                                        <input type="number" id="lockout_minutes" name="lockout_minutes" min="1" max="1440" value="<?= (int) ($systemSecuritySettings['lockout_minutes'] ?? 15) ?>" required>
                                        <span>min</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="failed_attempt_reset_minutes">Reiniciar contador</label>
                                    <div class="system-security-input-suffix">
                                        <input type="number" id="failed_attempt_reset_minutes" name="failed_attempt_reset_minutes" min="5" max="1440" value="<?= (int) ($systemSecuritySettings['failed_attempt_reset_minutes'] ?? 30) ?>" required>
                                        <span>min</span>
                                    </div>
                                </div>
                            </div>

                            <label class="system-security-switch-card is-wide">
                                <input type="checkbox" name="block_inactive_users" value="1" <?= securityViewChecked($systemSecuritySettings['block_inactive_users'] ?? 0) ?>>
                                <span class="system-security-switch"></span>
                                <span><strong>Bloquear cuentas inactivas</strong><small>Las cuentas deshabilitadas no podrán iniciar sesión.</small></span>
                            </label>
                        </section>

                        <section class="system-security-card">
                            <div class="system-profile-card-header">
                                <span class="system-profile-step">03</span>
                                <div>
                                    <h3>Administración de sesiones</h3>
                                    <p>Controla la duración, la inactividad y la coexistencia de sesiones.</p>
                                </div>
                            </div>

                            <div class="system-security-grid two-columns">
                                <div class="form-group">
                                    <label for="session_idle_minutes">Cierre por inactividad</label>
                                    <div class="system-security-input-suffix">
                                        <input type="number" id="session_idle_minutes" name="session_idle_minutes" min="5" max="1440" value="<?= (int) ($systemSecuritySettings['session_idle_minutes'] ?? 30) ?>" required>
                                        <span>min</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="session_max_hours">Duración máxima</label>
                                    <div class="system-security-input-suffix">
                                        <input type="number" id="session_max_hours" name="session_max_hours" min="1" max="168" value="<?= (int) ($systemSecuritySettings['session_max_hours'] ?? 12) ?>" required>
                                        <span>horas</span>
                                    </div>
                                </div>
                            </div>

                            <div class="system-security-switch-grid">
                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="single_session" value="1" <?= securityViewChecked($systemSecuritySettings['single_session'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Una sesión por usuario</strong><small>Un nuevo ingreso cerrará las sesiones anteriores.</small></span>
                                </label>

                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="invalidate_sessions_on_password_change" value="1" <?= securityViewChecked($systemSecuritySettings['invalidate_sessions_on_password_change'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Cerrar sesiones al cambiar clave</strong><small>Conserva únicamente la sesión que realizó el cambio.</small></span>
                                </label>

                                <label class="system-security-switch-card">
                                    <input type="checkbox" name="audit_enabled" value="1" <?= securityViewChecked($systemSecuritySettings['audit_enabled'] ?? 0) ?>>
                                    <span class="system-security-switch"></span>
                                    <span><strong>Auditoría de seguridad</strong><small>Registra accesos, bloqueos y acciones administrativas.</small></span>
                                </label>
                            </div>
                        </section>

                        <div class="system-security-actions">
                            <a href="/helpdesk-php/admin-settings.php" class="btn-secondary">Cancelar</a>
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Guardar seguridad
                            </button>
                        </div>
                    </fieldset>
                </form>

                <aside class="system-security-aside">
                    <section class="system-security-summary-card" id="systemSecuritySummary">
                        <div class="system-security-summary-heading">
                            <span><i class="fa-solid fa-shield"></i></span>
                            <div>
                                <small>Nivel de protección</small>
                                <strong id="securityLevelLabel"><?= securityViewSafe($systemSecurityLevel['label'] ?? 'Básico') ?></strong>
                            </div>
                        </div>

                        <div class="system-security-score">
                            <div><span id="securityScoreBar" style="width: <?= (int) ($systemSecurityLevel['score'] ?? 0) ?>%"></span></div>
                            <strong id="securityScoreValue"><?= (int) ($systemSecurityLevel['score'] ?? 0) ?>%</strong>
                        </div>

                        <dl>
                            <div><dt>Contraseña</dt><dd id="summaryPasswordLength"><?= (int) ($systemSecuritySettings['min_password_length'] ?? 8) ?> caracteres</dd></div>
                            <div><dt>Intentos</dt><dd id="summaryAttempts"><?= (int) ($systemSecuritySettings['max_failed_attempts'] ?? 5) ?></dd></div>
                            <div><dt>Bloqueo</dt><dd id="summaryLockout"><?= (int) ($systemSecuritySettings['lockout_minutes'] ?? 15) ?> min</dd></div>
                            <div><dt>Inactividad</dt><dd id="summaryIdle"><?= (int) ($systemSecuritySettings['session_idle_minutes'] ?? 30) ?> min</dd></div>
                            <div><dt>Sesión única</dt><dd id="summarySingleSession"><?= !empty($systemSecuritySettings['single_session']) ? 'Activada' : 'Desactivada' ?></dd></div>
                        </dl>

                        <div class="system-security-policy-note">
                            <i class="fa-solid fa-key"></i>
                            <span><?= securityViewSafe($rulesText) ?></span>
                        </div>
                    </section>

                    <section class="system-security-meta-card">
                        <div class="system-profile-meta-heading">
                            <span><i class="fa-solid fa-clock-rotate-left"></i></span>
                            <div>
                                <strong>Última configuración</strong>
                                <small>Control de cambios</small>
                            </div>
                        </div>
                        <dl>
                            <div><dt>Estado</dt><dd><span class="system-profile-state <?= $systemSecurityReady ? 'is-ready' : '' ?>"><?= $systemSecurityReady ? 'Operativo' : 'Pendiente' ?></span></dd></div>
                            <div><dt>Actualizado por</dt><dd><?= securityViewSafe($updatedByName !== '' ? $updatedByName : 'Sin registro') ?></dd></div>
                            <div><dt>Fecha</dt><dd><?= securityViewSafe(securityViewDate($updatedAt)) ?></dd></div>
                            <div><dt>Sesiones activas</dt><dd><?= count($systemSecuritySessions) ?></dd></div>
                        </dl>
                    </section>
                </aside>
            </section>

            <section class="system-security-card system-security-emergency">
                <div class="system-security-section-heading">
                    <div>
                        <span>Acciones protegidas</span>
                        <h3>Herramientas administrativas de emergencia</h3>
                        <p>Cada operación requiere confirmación y queda registrada en la auditoría.</p>
                    </div>
                </div>

                <div class="system-security-emergency-grid">
                    <form action="/helpdesk-php/security-actions.php" method="POST" data-security-confirm="Se cerrarán todas las sesiones excepto la tuya. ¿Continuar?">
                        <input type="hidden" name="csrf_token" value="<?= securityViewSafe($systemSecurityActionCsrfToken) ?>">
                        <input type="hidden" name="security_action" value="revoke_other_sessions">
                        <button type="submit" class="system-security-emergency-card">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span><strong>Cerrar otras sesiones</strong><small>Mantiene abierta tu sesión actual.</small></span>
                        </button>
                    </form>

                    <form action="/helpdesk-php/security-actions.php" method="POST" data-security-confirm="Se limpiarán todos los bloqueos e intentos fallidos. ¿Continuar?">
                        <input type="hidden" name="csrf_token" value="<?= securityViewSafe($systemSecurityActionCsrfToken) ?>">
                        <input type="hidden" name="security_action" value="unlock_all_users">
                        <button type="submit" class="system-security-emergency-card">
                            <i class="fa-solid fa-unlock-keyhole"></i>
                            <span><strong>Desbloquear cuentas</strong><small>Reinicia todos los intentos fallidos.</small></span>
                        </button>
                    </form>

                    <form action="/helpdesk-php/security-actions.php" method="POST" data-security-confirm="Todos los usuarios activos, incluido tú, deberán cambiar su contraseña. ¿Continuar?">
                        <input type="hidden" name="csrf_token" value="<?= securityViewSafe($systemSecurityActionCsrfToken) ?>">
                        <input type="hidden" name="security_action" value="force_password_change_all">
                        <button type="submit" class="system-security-emergency-card is-danger">
                            <i class="fa-solid fa-key"></i>
                            <span><strong>Forzar cambio global</strong><small>Solicita una nueva contraseña en el próximo acceso.</small></span>
                        </button>
                    </form>

                    <form action="/helpdesk-php/security-actions.php" method="POST" data-security-confirm="Se restaurarán las políticas recomendadas. ¿Continuar?">
                        <input type="hidden" name="csrf_token" value="<?= securityViewSafe($systemSecurityActionCsrfToken) ?>">
                        <input type="hidden" name="security_action" value="restore_defaults">
                        <button type="submit" class="system-security-emergency-card">
                            <i class="fa-solid fa-rotate-left"></i>
                            <span><strong>Restaurar valores</strong><small>Recupera la configuración recomendada.</small></span>
                        </button>
                    </form>
                </div>
            </section>

            <section class="system-security-card">
                <div class="system-security-section-heading">
                    <div>
                        <span>Actividad actual</span>
                        <h3>Sesiones activas</h3>
                        <p>Revisa desde dónde están conectadas las cuentas del sistema.</p>
                    </div>
                    <strong><?= count($systemSecuritySessions) ?> activas</strong>
                </div>

                <div class="system-security-table-wrap">
                    <table class="system-security-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Dispositivo</th>
                                <th>IP</th>
                                <th>Última actividad</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$systemSecuritySessions): ?>
                                <tr><td colspan="5" class="system-security-empty">No hay sesiones registradas todavía.</td></tr>
                            <?php else: ?>
                                <?php foreach ($systemSecuritySessions as $sessionItem): ?>
                                    <?php $isCurrent = hash_equals($currentSecuritySessionToken, (string) ($sessionItem['session_token'] ?? '')); ?>
                                    <tr>
                                        <td>
                                            <strong><?= securityViewSafe($sessionItem['user_name'] ?? 'Usuario') ?></strong>
                                            <small><?= securityViewSafe(($sessionItem['user_email'] ?? '') . ' · ' . ($sessionItem['user_role'] ?? '')) ?></small>
                                        </td>
                                        <td><?= securityViewSafe($sessionItem['device_label'] ?? 'Dispositivo') ?></td>
                                        <td><?= securityViewSafe($sessionItem['ip_address'] ?? 'Sin IP') ?></td>
                                        <td><?= securityViewSafe(securityViewDate($sessionItem['last_activity_at'] ?? null)) ?></td>
                                        <td>
                                            <?php if ($isCurrent): ?>
                                                <span class="system-security-current-session">Tu sesión</span>
                                            <?php else: ?>
                                                <form action="/helpdesk-php/security-actions.php" method="POST" data-security-confirm="¿Cerrar esta sesión?">
                                                    <input type="hidden" name="csrf_token" value="<?= securityViewSafe($systemSecurityActionCsrfToken) ?>">
                                                    <input type="hidden" name="security_action" value="revoke_session">
                                                    <input type="hidden" name="session_id" value="<?= (int) ($sessionItem['id'] ?? 0) ?>">
                                                    <button type="submit" class="system-security-close-session">Cerrar</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="system-security-trace-overview">
                <article class="system-security-card system-security-trace-panel">
                    <div class="system-security-section-heading">
                        <div>
                            <span>Trazabilidad por empresa</span>
                            <h3>Empresas y contactos</h3>
                            <p>Selecciona una empresa y luego un contacto para consultar su historial individual.</p>
                        </div>
                        <strong><?= $systemSecurityCompanyTotal ?> empresas</strong>
                    </div>

                    <div class="system-security-company-list">
                        <?php if (!$systemSecurityCompanies): ?>
                            <div class="system-security-empty">No existen empresas con contactos registrados.</div>
                        <?php else: ?>
                            <?php foreach ($systemSecurityCompanies as $companyTrace): ?>
                                <?php
                                    $traceCompanyName = securityTraceCompanyName($companyTrace);
                                    $traceLogoUrl = securityTraceLogoUrl($companyTrace['logo_path'] ?? null);
                                ?>
                                <a class="system-security-company-row" href="/helpdesk-php/admin-security-company.php?company_id=<?= (int) ($companyTrace['company_id'] ?? 0) ?>">
                                    <span class="system-security-company-logo <?= $traceLogoUrl !== '' ? 'has-logo' : '' ?>">
                                        <?php if ($traceLogoUrl !== ''): ?>
                                            <img src="<?= securityViewSafe($traceLogoUrl) ?>" alt="Logo de <?= securityViewSafe($traceCompanyName) ?>">
                                        <?php else: ?>
                                            <?= securityViewSafe(securityTraceInitials($traceCompanyName)) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="system-security-company-copy">
                                        <strong><?= securityViewSafe($traceCompanyName) ?></strong>
                                        <small>
                                            <?= (int) ($companyTrace['contact_count'] ?? 0) ?> contactos
                                            · <?= (int) ($companyTrace['event_count'] ?? 0) ?> eventos
                                            · Última actividad: <?= securityViewSafe(securityViewDate($companyTrace['last_activity'] ?? null)) ?>
                                        </small>
                                    </span>
                                    <span class="system-security-company-open">Ver contactos <i class="fa-solid fa-chevron-right"></i></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="system-security-trace-footer">
                        <a href="/helpdesk-php/admin-security-companies.php" class="btn-secondary">
                            <i class="fa-solid fa-building-shield"></i>
                            Ver todas las empresas
                        </a>
                    </div>
                </article>

                <article class="system-security-card system-security-trace-panel">
                    <div class="system-security-section-heading">
                        <div>
                            <span>Actividad global</span>
                            <h3>Eventos generales del sistema</h3>
                            <p>Acciones administrativas, técnicas o automáticas que no pertenecen a un contacto empresarial.</p>
                        </div>
                        <strong>Últimos <?= count($systemSecurityGeneralLogs) ?></strong>
                    </div>

                    <div class="system-security-log-list system-security-general-preview">
                        <?php if (!$systemSecurityGeneralLogs): ?>
                            <div class="system-security-empty">Aún no existen eventos generales.</div>
                        <?php else: ?>
                            <?php foreach ($systemSecurityGeneralLogs as $log): ?>
                                <article class="system-security-log-item severity-<?= securityViewSafe($log['severity'] ?? 'info') ?>">
                                    <span class="system-security-log-icon"><i class="fa-solid fa-shield"></i></span>
                                    <div>
                                        <div class="system-security-log-title">
                                            <strong><?= securityViewSafe($log['description'] ?? 'Evento de seguridad') ?></strong>
                                            <span><?= securityViewSafe($log['event_type'] ?? '') ?></span>
                                        </div>
                                        <small>
                                            <?= securityViewSafe(securityViewDate($log['created_at'] ?? null)) ?>
                                            · Usuario: <?= securityViewSafe($log['user_name'] ?? 'Sistema') ?>
                                            · Actor: <?= securityViewSafe($log['actor_name'] ?? 'Sistema') ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="system-security-trace-footer">
                        <a href="/helpdesk-php/admin-security-general.php" class="btn-secondary">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            Ver eventos generales (<?= $systemSecurityGeneralTotal ?>)
                        </a>
                    </div>
                </article>
            </section>
        </main>
    </div>
</div>

<script src="/helpdesk-php/public/assets/js/admin-system-security.js"></script>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
