<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_sla.php';

$redirectUrl = '/helpdesk-php/admin-system-sla.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit;
}

if (!systemSlaModuleReady($pdo)) {
    $_SESSION['settings_error'] = 'Primero ejecuta database/system_sla.sql en phpMyAdmin.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (!systemSlaVerifyCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['settings_error'] = 'La sesión del formulario venció. Vuelve a intentarlo.';
    header('Location: ' . $redirectUrl);
    exit;
}

$currentUser = (array) user();
$currentUserId = (int) ($currentUser['id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? 'save_profile')));
$profileId = (int) ($_POST['profile_id'] ?? 0);

function systemSlaRedirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

try {
    if ($action === 'set_default') {
        if ($profileId <= 0) {
            throw new RuntimeException('Perfil SLA no válido.');
        }

        $pdo->beginTransaction();
        $pdo->exec('UPDATE sla_profiles SET is_default = 0');
        $stmt = $pdo->prepare(
            'UPDATE sla_profiles
             SET is_default = 1, is_active = 1, updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute(['updated_by' => $currentUserId ?: null, 'id' => $profileId]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('No se encontró el perfil SLA.');
        }

        systemSlaLog($pdo, 'DEFAULT_CHANGED', 'Se estableció un nuevo perfil SLA predeterminado.', $profileId, $currentUserId);
        $pdo->commit();
        $_SESSION['settings_success'] = 'El perfil SLA predeterminado fue actualizado.';
        systemSlaRedirect($redirectUrl . '?profile_id=' . $profileId);
    }

    if ($action === 'toggle_active') {
        if ($profileId <= 0) {
            throw new RuntimeException('Perfil SLA no válido.');
        }

        $stmt = $pdo->prepare('SELECT is_active, is_default FROM sla_profiles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $profileId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) {
            throw new RuntimeException('No se encontró el perfil SLA.');
        }

        if ((int) ($profile['is_default'] ?? 0) === 1 && (int) ($profile['is_active'] ?? 0) === 1) {
            throw new RuntimeException('El perfil predeterminado no puede desactivarse. Define otro perfil predeterminado primero.');
        }

        $newState = (int) ($profile['is_active'] ?? 0) === 1 ? 0 : 1;
        $stmt = $pdo->prepare(
            'UPDATE sla_profiles SET is_active = :is_active, updated_by = :updated_by WHERE id = :id'
        );
        $stmt->execute([
            'is_active' => $newState,
            'updated_by' => $currentUserId ?: null,
            'id' => $profileId,
        ]);

        systemSlaLog(
            $pdo,
            $newState === 1 ? 'PROFILE_ENABLED' : 'PROFILE_DISABLED',
            $newState === 1 ? 'Se activó un perfil SLA.' : 'Se desactivó un perfil SLA.',
            $profileId,
            $currentUserId
        );

        $_SESSION['settings_success'] = $newState === 1
            ? 'El perfil SLA fue activado.'
            : 'El perfil SLA fue desactivado.';
        systemSlaRedirect($redirectUrl . '?profile_id=' . $profileId);
    }

    if ($action === 'delete_profile') {
        if ($profileId <= 0) {
            throw new RuntimeException('Perfil SLA no válido.');
        }

        $stmt = $pdo->prepare(
            'SELECT p.is_default,
                    (SELECT COUNT(*) FROM client_companies c WHERE c.sla_profile_id = p.id) AS companies_count,
                    (SELECT COUNT(*) FROM tickets t WHERE t.sla_profile_id = p.id) AS tickets_count
             FROM sla_profiles p
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $profileId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            throw new RuntimeException('No se encontró el perfil SLA.');
        }

        if ((int) ($profile['is_default'] ?? 0) === 1) {
            throw new RuntimeException('No se puede eliminar el perfil predeterminado.');
        }

        if ((int) ($profile['companies_count'] ?? 0) > 0 || (int) ($profile['tickets_count'] ?? 0) > 0) {
            throw new RuntimeException('No se puede eliminar un perfil que está vinculado a empresas o tickets. Puedes desactivarlo.');
        }

        systemSlaLog($pdo, 'PROFILE_DELETED', 'Se eliminó un perfil SLA.', $profileId, $currentUserId);
        $stmt = $pdo->prepare('DELETE FROM sla_profiles WHERE id = :id');
        $stmt->execute(['id' => $profileId]);

        $_SESSION['settings_success'] = 'El perfil SLA fue eliminado.';
        systemSlaRedirect($redirectUrl);
    }

    if ($action === 'restore_defaults') {
        $pdo->beginTransaction();

        $profiles = [
            [
                'name' => 'SLA Estándar 8/5',
                'description' => 'Atención de lunes a viernes dentro del horario laboral.',
                'schedule_type' => 'BUSINESS',
                'work_start' => '08:00:00',
                'work_end' => '17:00:00',
                'work_days' => '1,2,3,4,5',
                'is_default' => 1,
            ],
            [
                'name' => 'SLA Continuo 24/7',
                'description' => 'Atención continua durante todos los días y horas.',
                'schedule_type' => '24_7',
                'work_start' => '00:00:00',
                'work_end' => '23:59:59',
                'work_days' => '1,2,3,4,5,6,7',
                'is_default' => 0,
            ],
        ];

        $pdo->exec('UPDATE sla_profiles SET is_default = 0');
        $defaultTargets = systemSlaDefaultTargets();

        foreach ($profiles as $profile) {
            $stmt = $pdo->prepare(
                'INSERT INTO sla_profiles
                    (name, description, schedule_type, timezone_name, work_start, work_end, work_days,
                     warning_percent, critical_percent, is_default, is_active, updated_by)
                 VALUES
                    (:name, :description, :schedule_type, \'America/Lima\', :work_start, :work_end, :work_days,
                     75, 90, :is_default, 1, :updated_by)
                 ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    schedule_type = VALUES(schedule_type),
                    work_start = VALUES(work_start),
                    work_end = VALUES(work_end),
                    work_days = VALUES(work_days),
                    warning_percent = 75,
                    critical_percent = 90,
                    is_default = VALUES(is_default),
                    is_active = 1,
                    updated_by = VALUES(updated_by)'
            );
            $stmt->execute([
                'name' => $profile['name'],
                'description' => $profile['description'],
                'schedule_type' => $profile['schedule_type'],
                'work_start' => $profile['work_start'],
                'work_end' => $profile['work_end'],
                'work_days' => $profile['work_days'],
                'is_default' => $profile['is_default'],
                'updated_by' => $currentUserId ?: null,
            ]);

            $stmt = $pdo->prepare('SELECT id FROM sla_profiles WHERE name = :name LIMIT 1');
            $stmt->execute(['name' => $profile['name']]);
            $restoredId = (int) ($stmt->fetchColumn() ?: 0);

            foreach ($defaultTargets as $priorityCode => $target) {
                $targetStmt = $pdo->prepare(
                    'INSERT INTO sla_priority_targets (profile_id, priority_code, tta_minutes, ttr_minutes)
                     VALUES (:profile_id, :priority_code, :tta_minutes, :ttr_minutes)
                     ON DUPLICATE KEY UPDATE
                        tta_minutes = VALUES(tta_minutes),
                        ttr_minutes = VALUES(ttr_minutes)'
                );
                $targetStmt->execute([
                    'profile_id' => $restoredId,
                    'priority_code' => $priorityCode,
                    'tta_minutes' => $target['tta_minutes'],
                    'ttr_minutes' => $target['ttr_minutes'],
                ]);
            }

            $pdo->prepare('DELETE FROM sla_pause_statuses WHERE profile_id = :profile_id')
                ->execute(['profile_id' => $restoredId]);
            $pdo->prepare(
                'INSERT INTO sla_pause_statuses (profile_id, status_code) VALUES (:profile_id, \'RESPONDIDO\')'
            )->execute(['profile_id' => $restoredId]);
        }

        systemSlaLog($pdo, 'DEFAULTS_RESTORED', 'Se restauraron los perfiles SLA predeterminados.', null, $currentUserId);
        $pdo->commit();
        $_SESSION['settings_success'] = 'Los perfiles SLA predeterminados fueron restaurados.';
        systemSlaRedirect($redirectUrl);
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $scheduleType = strtoupper(trim((string) ($_POST['schedule_type'] ?? 'BUSINESS')));
    $workStart = trim((string) ($_POST['work_start'] ?? '08:00'));
    $workEnd = trim((string) ($_POST['work_end'] ?? '17:00'));
    $workDays = systemSlaNormalizeDays($_POST['work_days'] ?? []);
    $warningPercent = (int) ($_POST['warning_percent'] ?? 75);
    $criticalPercent = (int) ($_POST['critical_percent'] ?? 90);
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $pauseStatuses = array_values(array_intersect(
        ['ABIERTO', 'EN_PROCESO', 'RESPONDIDO'],
        array_map('strtoupper', (array) ($_POST['pause_statuses'] ?? []))
    ));

    if ($name === '' || mb_strlen($name) > 120) {
        throw new RuntimeException('Ingresa un nombre de perfil válido de hasta 120 caracteres.');
    }

    if (!in_array($scheduleType, ['24_7', 'BUSINESS'], true)) {
        throw new RuntimeException('Selecciona un tipo de horario válido.');
    }

    if ($scheduleType === 'BUSINESS') {
        if (!preg_match('/^\d{2}:\d{2}$/', $workStart) || !preg_match('/^\d{2}:\d{2}$/', $workEnd)) {
            throw new RuntimeException('Ingresa un horario laboral válido.');
        }

        if (strtotime('1970-01-01 ' . $workEnd) <= strtotime('1970-01-01 ' . $workStart)) {
            throw new RuntimeException('La hora de término debe ser posterior a la hora de inicio.');
        }

        if ($workDays === '') {
            throw new RuntimeException('Selecciona al menos un día de atención.');
        }
    } else {
        $workStart = '00:00';
        $workEnd = '23:59';
        $workDays = '1,2,3,4,5,6,7';
    }

    if ($warningPercent < 25 || $warningPercent > 95) {
        throw new RuntimeException('La alerta preventiva debe estar entre 25% y 95%.');
    }

    if ($criticalPercent <= $warningPercent || $criticalPercent > 99) {
        throw new RuntimeException('La alerta crítica debe ser mayor que la preventiva y menor que 100%.');
    }

    $targets = [];
    foreach (['ALTA', 'MEDIA', 'BAJA'] as $priorityCode) {
        $tta = (int) ($_POST['tta_' . strtolower($priorityCode)] ?? 0);
        $ttr = (int) ($_POST['ttr_' . strtolower($priorityCode)] ?? 0);

        if ($tta < 1 || $ttr < 1) {
            throw new RuntimeException('Todos los objetivos TTA y TTR deben ser mayores que cero.');
        }

        if ($ttr < $tta) {
            throw new RuntimeException('El TTR de ' . ucfirst(strtolower($priorityCode)) . ' no puede ser menor que su TTA.');
        }

        $targets[$priorityCode] = ['tta_minutes' => $tta, 'ttr_minutes' => $ttr];
    }

    $pdo->beginTransaction();

    if ($isDefault === 1) {
        $pdo->exec('UPDATE sla_profiles SET is_default = 0');
        $isActive = 1;
    }

    if ($profileId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE sla_profiles
             SET name = :name,
                 description = :description,
                 schedule_type = :schedule_type,
                 timezone_name = \'America/Lima\',
                 work_start = :work_start,
                 work_end = :work_end,
                 work_days = :work_days,
                 warning_percent = :warning_percent,
                 critical_percent = :critical_percent,
                 is_default = :is_default,
                 is_active = :is_active,
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'schedule_type' => $scheduleType,
            'work_start' => $workStart . ':00',
            'work_end' => $workEnd . ':00',
            'work_days' => $workDays,
            'warning_percent' => $warningPercent,
            'critical_percent' => $criticalPercent,
            'is_default' => $isDefault,
            'is_active' => $isActive,
            'updated_by' => $currentUserId ?: null,
            'id' => $profileId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO sla_profiles
                (name, description, schedule_type, timezone_name, work_start, work_end, work_days,
                 warning_percent, critical_percent, is_default, is_active, updated_by)
             VALUES
                (:name, :description, :schedule_type, \'America/Lima\', :work_start, :work_end, :work_days,
                 :warning_percent, :critical_percent, :is_default, :is_active, :updated_by)'
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'schedule_type' => $scheduleType,
            'work_start' => $workStart . ':00',
            'work_end' => $workEnd . ':00',
            'work_days' => $workDays,
            'warning_percent' => $warningPercent,
            'critical_percent' => $criticalPercent,
            'is_default' => $isDefault,
            'is_active' => $isActive,
            'updated_by' => $currentUserId ?: null,
        ]);
        $profileId = (int) $pdo->lastInsertId();
    }

    foreach ($targets as $priorityCode => $target) {
        $stmt = $pdo->prepare(
            'INSERT INTO sla_priority_targets (profile_id, priority_code, tta_minutes, ttr_minutes)
             VALUES (:profile_id, :priority_code, :tta_minutes, :ttr_minutes)
             ON DUPLICATE KEY UPDATE
                tta_minutes = VALUES(tta_minutes),
                ttr_minutes = VALUES(ttr_minutes)'
        );
        $stmt->execute([
            'profile_id' => $profileId,
            'priority_code' => $priorityCode,
            'tta_minutes' => $target['tta_minutes'],
            'ttr_minutes' => $target['ttr_minutes'],
        ]);
    }

    $pdo->prepare('DELETE FROM sla_pause_statuses WHERE profile_id = :profile_id')
        ->execute(['profile_id' => $profileId]);

    foreach ($pauseStatuses as $statusCode) {
        $stmt = $pdo->prepare(
            'INSERT INTO sla_pause_statuses (profile_id, status_code) VALUES (:profile_id, :status_code)'
        );
        $stmt->execute(['profile_id' => $profileId, 'status_code' => $statusCode]);
    }

    if ($isDefault === 0) {
        $defaultExists = (int) $pdo->query('SELECT COUNT(*) FROM sla_profiles WHERE is_default = 1 AND is_active = 1')->fetchColumn();
        if ($defaultExists === 0) {
            $stmt = $pdo->prepare('UPDATE sla_profiles SET is_default = 1, is_active = 1 WHERE id = :id');
            $stmt->execute(['id' => $profileId]);
        }
    }

    systemSlaLog(
        $pdo,
        $action === 'save_profile' && !empty($_POST['profile_id']) ? 'PROFILE_UPDATED' : 'PROFILE_CREATED',
        !empty($_POST['profile_id']) ? 'Se actualizó un perfil SLA.' : 'Se creó un perfil SLA.',
        $profileId,
        $currentUserId,
        ['name' => $name, 'targets' => $targets, 'pause_statuses' => $pauseStatuses]
    );

    $pdo->commit();
    $_SESSION['settings_success'] = 'El perfil SLA fue guardado correctamente.';
    systemSlaRedirect($redirectUrl . '?profile_id=' . $profileId);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['settings_error'] = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'No se pudieron guardar los cambios del SLA. Revisa la base de datos e inténtalo nuevamente.';
    systemSlaRedirect($redirectUrl . ($profileId > 0 ? '?profile_id=' . $profileId : ''));
}
