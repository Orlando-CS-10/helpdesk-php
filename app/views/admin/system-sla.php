<?php
$systemSlaReady = $systemSlaReady ?? false;
$systemSlaProfiles = $systemSlaProfiles ?? [];
$systemSlaSelectedProfile = $systemSlaSelectedProfile ?? systemSlaDefaultProfile();
$systemSlaRecentAudit = $systemSlaRecentAudit ?? [];
$systemSlaSummary = $systemSlaSummary ?? [];
$systemSlaCsrfToken = $systemSlaCsrfToken ?? systemSlaCsrfToken();

$title = 'SLA y reglas del sistema';
$activePage = 'system-sla';
$pageTitle = 'SLA y reglas del sistema';
$pageSubtitle = 'Configura tiempos de atención, horarios, alertas y pausas operativas por empresa.';

$profile = $systemSlaSelectedProfile;
$targets = $profile['targets'] ?? systemSlaDefaultTargets();
$pauseStatuses = $profile['pause_statuses'] ?? ['RESPONDIDO'];
$selectedDays = systemSlaDaysArray($profile['work_days'] ?? '1,2,3,4,5');
$isExistingProfile = !empty($profile['id']);

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="admin-shell admin-sla-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-sla-content">
            <section class="sla-hero-card">
                <div>
                    <span class="sla-eyebrow">Control operativo</span>
                    <h2>Define compromisos medibles para cada empresa</h2>
                    <p>Administra perfiles SLA, objetivos TTA y TTR, horarios de atención, alertas preventivas y estados que pausan el contador.</p>
                </div>
                <div class="sla-hero-icon" aria-hidden="true">
                    <i class="fa-solid fa-stopwatch"></i>
                </div>
            </section>

            <?php if (!$systemSlaReady): ?>
                <section class="settings-setup-alert">
                    <span><i class="fa-solid fa-database"></i></span>
                    <div>
                        <strong>El módulo SLA todavía no está instalado.</strong>
                        <p>Ejecuta <code>database/system_sla.sql</code> en phpMyAdmin. La configuración anterior 8/5 y 24/7 seguirá funcionando hasta completar la instalación.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="sla-summary-grid">
                <article><span>Perfiles</span><strong><?= (int)($systemSlaSummary['profiles'] ?? 0) ?></strong><small>Configuraciones creadas</small></article>
                <article><span>Activos</span><strong><?= (int)($systemSlaSummary['active'] ?? 0) ?></strong><small>Disponibles para empresas</small></article>
                <article><span>Empresas</span><strong><?= (int)($systemSlaSummary['companies'] ?? 0) ?></strong><small>Con perfil asignado</small></article>
                <article><span>Predeterminado</span><strong class="sla-summary-name"><?= htmlspecialchars((string)($systemSlaSummary['default_name'] ?? 'Sin definir')) ?></strong><small>Usado como respaldo</small></article>
            </section>

            <section class="sla-workspace">
                <aside class="sla-profile-sidebar">
                    <div class="sla-side-heading">
                        <div>
                            <span>Perfiles SLA</span>
                            <small>Selecciona uno para editarlo</small>
                        </div>
                        <a class="sla-add-profile" href="/helpdesk-php/admin-system-sla.php" title="Crear perfil">
                            <i class="fa-solid fa-plus"></i>
                        </a>
                    </div>

                    <div class="sla-profile-list">
                        <?php if (!$systemSlaProfiles): ?>
                            <div class="sla-empty-profile">No hay perfiles registrados.</div>
                        <?php endif; ?>

                        <?php foreach ($systemSlaProfiles as $item): ?>
                            <?php $selected = (int)($item['id'] ?? 0) === (int)($profile['id'] ?? 0); ?>
                            <a class="sla-profile-item <?= $selected ? 'active' : '' ?> <?= empty($item['is_active']) ? 'is-disabled' : '' ?>"
                               href="/helpdesk-php/admin-system-sla.php?profile_id=<?= (int)$item['id'] ?>">
                                <span class="sla-profile-item-icon"><i class="fa-solid <?= ($item['schedule_type'] ?? '') === '24_7' ? 'fa-earth-americas' : 'fa-business-time' ?>"></i></span>
                                <span class="sla-profile-item-copy">
                                    <strong><?= htmlspecialchars((string)$item['name']) ?></strong>
                                    <small><?= htmlspecialchars((string)($item['schedule_label'] ?? '')) ?></small>
                                </span>
                                <span class="sla-profile-badges">
                                    <?php if (!empty($item['is_default'])): ?><em>Predeterminado</em><?php endif; ?>
                                    <b><?= (int)($item['companies_count'] ?? 0) ?></b>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <div class="sla-editor-panel">
                    <form action="/helpdesk-php/update-system-sla.php" method="POST" class="sla-profile-form" id="slaProfileForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($systemSlaCsrfToken) ?>">
                        <input type="hidden" name="action" value="save_profile">
                        <input type="hidden" name="profile_id" value="<?= (int)($profile['id'] ?? 0) ?>">

                        <fieldset <?= !$systemSlaReady ? 'disabled' : '' ?>>
                            <section class="sla-form-card">
                                <div class="sla-card-heading">
                                    <span class="sla-step">01</span>
                                    <div>
                                        <h3><?= $isExistingProfile ? 'Identidad del perfil' : 'Nuevo perfil SLA' ?></h3>
                                        <p>Define cómo se identificará y cuándo estará disponible para las empresas.</p>
                                    </div>
                                </div>

                                <div class="sla-form-grid two-cols">
                                    <div class="form-group">
                                        <label for="sla_name">Nombre del perfil</label>
                                        <input id="sla_name" name="name" type="text" maxlength="120" required value="<?= htmlspecialchars((string)($profile['name'] ?? '')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="sla_schedule_type">Tipo de horario</label>
                                        <select id="sla_schedule_type" name="schedule_type" data-sla-schedule-select>
                                            <option value="BUSINESS" <?= ($profile['schedule_type'] ?? '') === 'BUSINESS' ? 'selected' : '' ?>>Horario laboral</option>
                                            <option value="24_7" <?= ($profile['schedule_type'] ?? '') === '24_7' ? 'selected' : '' ?>>Atención continua 24/7</option>
                                        </select>
                                    </div>
                                    <div class="form-group sla-full-field">
                                        <label for="sla_description">Descripción</label>
                                        <textarea id="sla_description" name="description" maxlength="255" rows="3"><?= htmlspecialchars((string)($profile['description'] ?? '')) ?></textarea>
                                    </div>
                                    <label class="sla-switch-option">
                                        <input type="checkbox" name="is_active" value="1" <?= !empty($profile['is_active']) ? 'checked' : '' ?>>
                                        <span><strong>Perfil activo</strong><small>Podrá asignarse a empresas cliente.</small></span>
                                    </label>
                                    <label class="sla-switch-option">
                                        <input type="checkbox" name="is_default" value="1" <?= !empty($profile['is_default']) ? 'checked' : '' ?>>
                                        <span><strong>Perfil predeterminado</strong><small>Se utilizará cuando una empresa no tenga perfil.</small></span>
                                    </label>
                                </div>
                            </section>

                            <section class="sla-form-card" data-sla-business-section>
                                <div class="sla-card-heading">
                                    <span class="sla-step">02</span>
                                    <div>
                                        <h3>Horario de atención</h3>
                                        <p>Selecciona los días y el rango en el que el contador debe avanzar.</p>
                                    </div>
                                </div>

                                <div class="sla-form-grid two-cols">
                                    <div class="form-group">
                                        <label for="sla_work_start">Hora de inicio</label>
                                        <input id="sla_work_start" name="work_start" type="time" value="<?= htmlspecialchars(substr((string)($profile['work_start'] ?? '08:00'), 0, 5)) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="sla_work_end">Hora de término</label>
                                        <input id="sla_work_end" name="work_end" type="time" value="<?= htmlspecialchars(substr((string)($profile['work_end'] ?? '17:00'), 0, 5)) ?>">
                                    </div>
                                </div>

                                <div class="sla-weekdays" aria-label="Días laborales">
                                    <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'] as $dayNumber => $dayName): ?>
                                        <label>
                                            <input type="checkbox" name="work_days[]" value="<?= $dayNumber ?>" <?= in_array($dayNumber, $selectedDays, true) ? 'checked' : '' ?>>
                                            <span><?= $dayName ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <section class="sla-form-card">
                                <div class="sla-card-heading">
                                    <span class="sla-step">03</span>
                                    <div>
                                        <h3>Objetivos por prioridad</h3>
                                        <p>TTA mide la primera atención y TTR el tiempo máximo hasta la resolución.</p>
                                    </div>
                                </div>

                                <div class="sla-target-table-wrap">
                                    <table class="sla-target-table">
                                        <thead><tr><th>Prioridad</th><th>TTA (minutos)</th><th>TTR (minutos)</th><th>Referencia</th></tr></thead>
                                        <tbody>
                                        <?php foreach (['ALTA' => 'Alta', 'MEDIA' => 'Media', 'BAJA' => 'Baja'] as $code => $label): ?>
                                            <?php $target = $targets[$code] ?? ['tta_minutes' => 60, 'ttr_minutes' => 480]; ?>
                                            <tr>
                                                <td><span class="sla-priority-pill priority-<?= strtolower($code) ?>"><?= $label ?></span></td>
                                                <td><input type="number" min="1" max="525600" name="tta_<?= strtolower($code) ?>" value="<?= (int)$target['tta_minutes'] ?>" required></td>
                                                <td><input type="number" min="1" max="525600" name="ttr_<?= strtolower($code) ?>" value="<?= (int)$target['ttr_minutes'] ?>" required></td>
                                                <td><small>TTA <?= htmlspecialchars(formatSlaMinutes((int)$target['tta_minutes'])) ?> · TTR <?= htmlspecialchars(formatSlaMinutes((int)$target['ttr_minutes'])) ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="sla-form-card">
                                <div class="sla-card-heading">
                                    <span class="sla-step">04</span>
                                    <div>
                                        <h3>Alertas y pausas</h3>
                                        <p>Configura cuándo advertir al equipo y en qué estados detener el contador.</p>
                                    </div>
                                </div>

                                <div class="sla-form-grid two-cols">
                                    <div class="form-group">
                                        <label for="sla_warning_percent">Alerta preventiva (%)</label>
                                        <input id="sla_warning_percent" name="warning_percent" type="number" min="25" max="95" value="<?= (int)($profile['warning_percent'] ?? 75) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="sla_critical_percent">Alerta crítica (%)</label>
                                        <input id="sla_critical_percent" name="critical_percent" type="number" min="26" max="99" value="<?= (int)($profile['critical_percent'] ?? 90) ?>">
                                    </div>
                                </div>

                                <div class="sla-pause-options">
                                    <?php foreach (['ABIERTO' => 'Abierto', 'EN_PROCESO' => 'En proceso', 'RESPONDIDO' => 'Respondido / esperando cliente'] as $code => $label): ?>
                                        <label>
                                            <input type="checkbox" name="pause_statuses[]" value="<?= $code ?>" <?= in_array($code, $pauseStatuses, true) ? 'checked' : '' ?>>
                                            <span><strong><?= $label ?></strong><small>El tiempo no avanzará mientras el ticket permanezca aquí.</small></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <div class="sla-form-actions">
                                <?php if ($isExistingProfile): ?>
                                    <a class="btn-secondary" href="/helpdesk-php/admin-system-sla.php"><i class="fa-solid fa-plus"></i> Nuevo perfil</a>
                                <?php endif; ?>
                                <button class="btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar perfil</button>
                            </div>
                        </fieldset>
                    </form>
                </div>

                <aside class="sla-preview-aside">
                    <section class="sla-preview-card">
                        <span class="sla-preview-label">Vista previa</span>
                        <div class="sla-preview-header">
                            <span><i class="fa-solid fa-clock"></i></span>
                            <div><strong data-sla-preview-name><?= htmlspecialchars((string)($profile['name'] ?? 'Nuevo perfil')) ?></strong><small data-sla-preview-schedule><?= htmlspecialchars(systemSlaScheduleLabel($profile)) ?></small></div>
                        </div>
                        <div class="sla-preview-progress"><span style="width: <?= (int)($profile['warning_percent'] ?? 75) ?>%"></span></div>
                        <div class="sla-preview-meta"><span>Advertencia</span><strong><?= (int)($profile['warning_percent'] ?? 75) ?>%</strong></div>
                        <div class="sla-preview-meta"><span>Crítico</span><strong><?= (int)($profile['critical_percent'] ?? 90) ?>%</strong></div>
                        <div class="sla-preview-targets">
                            <div><span>Alta</span><strong><?= htmlspecialchars(formatSlaMinutes((int)($targets['ALTA']['tta_minutes'] ?? 30))) ?> / <?= htmlspecialchars(formatSlaMinutes((int)($targets['ALTA']['ttr_minutes'] ?? 480))) ?></strong></div>
                            <div><span>Media</span><strong><?= htmlspecialchars(formatSlaMinutes((int)($targets['MEDIA']['tta_minutes'] ?? 120))) ?> / <?= htmlspecialchars(formatSlaMinutes((int)($targets['MEDIA']['ttr_minutes'] ?? 1440))) ?></strong></div>
                            <div><span>Baja</span><strong><?= htmlspecialchars(formatSlaMinutes((int)($targets['BAJA']['tta_minutes'] ?? 240))) ?> / <?= htmlspecialchars(formatSlaMinutes((int)($targets['BAJA']['ttr_minutes'] ?? 2880))) ?></strong></div>
                        </div>
                    </section>

                    <?php if ($isExistingProfile && $systemSlaReady): ?>
                        <section class="sla-actions-card">
                            <h3>Acciones del perfil</h3>
                            <form action="/helpdesk-php/update-system-sla.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($systemSlaCsrfToken) ?>">
                                <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                                <button name="action" value="set_default" class="btn-secondary" type="submit" <?= !empty($profile['is_default']) ? 'disabled' : '' ?>><i class="fa-solid fa-star"></i> Definir como predeterminado</button>
                                <button name="action" value="toggle_active" class="btn-secondary" type="submit"><i class="fa-solid fa-power-off"></i> <?= !empty($profile['is_active']) ? 'Desactivar' : 'Activar' ?></button>
                                <button name="action" value="delete_profile" class="sla-danger-button" type="submit" onclick="return confirm('¿Eliminar este perfil SLA?');"><i class="fa-regular fa-trash-can"></i> Eliminar perfil</button>
                            </form>
                        </section>
                    <?php endif; ?>

                    <section class="sla-audit-card">
                        <div class="sla-audit-heading"><div><span>Cambios recientes</span><h3>Trazabilidad SLA</h3></div><i class="fa-solid fa-list-check"></i></div>
                        <?php if (!$systemSlaRecentAudit): ?>
                            <p class="sla-audit-empty">Todavía no hay cambios registrados.</p>
                        <?php else: ?>
                            <div class="sla-audit-list">
                                <?php foreach ($systemSlaRecentAudit as $log): ?>
                                    <div><span></span><p><strong><?= htmlspecialchars((string)($log['actor_name'] ?? 'Sistema')) ?></strong><?= htmlspecialchars((string)$log['description']) ?><small><?= date('d/m/Y H:i', strtotime((string)$log['created_at'])) ?></small></p></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <form action="/helpdesk-php/update-system-sla.php" method="POST" class="sla-restore-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($systemSlaCsrfToken) ?>">
                        <button type="submit" name="action" value="restore_defaults" onclick="return confirm('¿Restaurar los perfiles SLA predeterminados?');"><i class="fa-solid fa-rotate-left"></i> Restaurar valores predeterminados</button>
                    </form>
                </aside>
            </section>
        </main>
    </div>
</div>

<script src="/helpdesk-php/public/assets/js/admin-system-sla.js?v=20260625-1"></script>
<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
