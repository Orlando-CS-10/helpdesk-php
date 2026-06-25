<?php

if (date_default_timezone_get() !== 'America/Lima') {
    date_default_timezone_set('America/Lima');
}

if (!function_exists('systemSlaTableExists')) {
    function systemSlaTableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
            $stmt->execute(['table_name' => $table]);
            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('systemSlaColumnExists')) {
    function systemSlaColumnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column_name");
            $stmt->execute(['column_name' => $column]);
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('systemSlaModuleReady')) {
    function systemSlaModuleReady(PDO $pdo): bool
    {
        return systemSlaTableExists($pdo, 'sla_profiles')
            && systemSlaTableExists($pdo, 'sla_priority_targets')
            && systemSlaTableExists($pdo, 'sla_pause_statuses');
    }
}

if (!function_exists('systemSlaCsrfToken')) {
    function systemSlaCsrfToken(): string
    {
        if (empty($_SESSION['system_sla_csrf'])) {
            $_SESSION['system_sla_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['system_sla_csrf'];
    }
}

if (!function_exists('systemSlaVerifyCsrf')) {
    function systemSlaVerifyCsrf(?string $token): bool
    {
        $sessionToken = (string) ($_SESSION['system_sla_csrf'] ?? '');
        $token = (string) $token;

        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('systemSlaDefaultTargets')) {
    function systemSlaDefaultTargets(): array
    {
        return [
            'ALTA' => ['tta_minutes' => 30, 'ttr_minutes' => 480],
            'MEDIA' => ['tta_minutes' => 120, 'ttr_minutes' => 1440],
            'BAJA' => ['tta_minutes' => 240, 'ttr_minutes' => 2880],
        ];
    }
}

if (!function_exists('systemSlaDefaultProfile')) {
    function systemSlaDefaultProfile(): array
    {
        return [
            'id' => null,
            'name' => 'SLA Estándar 8/5',
            'description' => 'Atención de lunes a viernes dentro del horario laboral.',
            'schedule_type' => 'BUSINESS',
            'timezone_name' => 'America/Lima',
            'work_start' => '08:00:00',
            'work_end' => '17:00:00',
            'work_days' => '1,2,3,4,5',
            'warning_percent' => 75,
            'critical_percent' => 90,
            'is_default' => 1,
            'is_active' => 1,
            'updated_by' => null,
            'updated_by_name' => '',
            'created_at' => null,
            'updated_at' => null,
            'targets' => systemSlaDefaultTargets(),
            'pause_statuses' => ['RESPONDIDO'],
            'companies_count' => 0,
        ];
    }
}

if (!function_exists('systemSlaNormalizeDays')) {
    function systemSlaNormalizeDays(array|string|null $days): string
    {
        $values = is_array($days) ? $days : explode(',', (string) $days);
        $normalized = [];

        foreach ($values as $day) {
            $number = (int) $day;
            if ($number >= 1 && $number <= 7) {
                $normalized[$number] = $number;
            }
        }

        ksort($normalized);
        return implode(',', array_values($normalized));
    }
}

if (!function_exists('systemSlaDaysArray')) {
    function systemSlaDaysArray(array|string|null $days): array
    {
        $normalized = systemSlaNormalizeDays($days);
        if ($normalized === '') {
            return [];
        }

        return array_map('intval', explode(',', $normalized));
    }
}

if (!function_exists('systemSlaScheduleLabel')) {
    function systemSlaScheduleLabel(array $profile): string
    {
        if (($profile['schedule_type'] ?? 'BUSINESS') === '24_7') {
            return 'Atención continua 24/7';
        }

        $start = substr((string) ($profile['work_start'] ?? '08:00'), 0, 5);
        $end = substr((string) ($profile['work_end'] ?? '17:00'), 0, 5);
        $days = systemSlaDaysArray($profile['work_days'] ?? '1,2,3,4,5');
        $dayLabel = $days === [1, 2, 3, 4, 5] ? 'Lun–Vie' : count($days) . ' días/semana';

        return $dayLabel . ' · ' . $start . '–' . $end;
    }
}

if (!function_exists('systemSlaGetTargets')) {
    function systemSlaGetTargets(PDO $pdo, int $profileId): array
    {
        $targets = systemSlaDefaultTargets();

        if ($profileId <= 0 || !systemSlaTableExists($pdo, 'sla_priority_targets')) {
            return $targets;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT priority_code, tta_minutes, ttr_minutes
                 FROM sla_priority_targets
                 WHERE profile_id = :profile_id'
            );
            $stmt->execute(['profile_id' => $profileId]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = strtoupper((string) ($row['priority_code'] ?? ''));
                if (isset($targets[$code])) {
                    $targets[$code] = [
                        'tta_minutes' => max(1, (int) ($row['tta_minutes'] ?? 1)),
                        'ttr_minutes' => max(1, (int) ($row['ttr_minutes'] ?? 1)),
                    ];
                }
            }
        } catch (Throwable $exception) {
            return $targets;
        }

        return $targets;
    }
}

if (!function_exists('systemSlaGetPauseStatuses')) {
    function systemSlaGetPauseStatuses(PDO $pdo, int $profileId): array
    {
        if ($profileId <= 0 || !systemSlaTableExists($pdo, 'sla_pause_statuses')) {
            return ['RESPONDIDO'];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT status_code
                 FROM sla_pause_statuses
                 WHERE profile_id = :profile_id
                 ORDER BY status_code'
            );
            $stmt->execute(['profile_id' => $profileId]);
            return array_values(array_filter(array_map(
                static fn (array $row): string => strtoupper((string) ($row['status_code'] ?? '')),
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            )));
        } catch (Throwable $exception) {
            return ['RESPONDIDO'];
        }
    }
}

if (!function_exists('systemSlaHydrateProfile')) {
    function systemSlaHydrateProfile(PDO $pdo, array $profile): array
    {
        $defaults = systemSlaDefaultProfile();
        $profile = array_merge($defaults, $profile);
        $profileId = (int) ($profile['id'] ?? 0);
        $profile['targets'] = systemSlaGetTargets($pdo, $profileId);
        $profile['pause_statuses'] = systemSlaGetPauseStatuses($pdo, $profileId);
        $profile['schedule_label'] = systemSlaScheduleLabel($profile);
        return $profile;
    }
}

if (!function_exists('systemSlaProfiles')) {
    function systemSlaProfiles(PDO $pdo, bool $onlyActive = false): array
    {
        if (!systemSlaModuleReady($pdo)) {
            return [];
        }

        try {
            $sql = "SELECT
                        p.*,
                        u.name AS updated_by_name,
                        (SELECT COUNT(*) FROM client_companies c WHERE c.sla_profile_id = p.id) AS companies_count
                    FROM sla_profiles p
                    LEFT JOIN users u ON u.id = p.updated_by";

            if ($onlyActive) {
                $sql .= ' WHERE p.is_active = 1';
            }

            $sql .= ' ORDER BY p.is_default DESC, p.is_active DESC, p.name ASC';
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn (array $row): array => systemSlaHydrateProfile($pdo, $row), $rows);
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSlaGetProfile')) {
    function systemSlaGetProfile(PDO $pdo, ?int $profileId = null): array
    {
        if (!systemSlaModuleReady($pdo)) {
            return systemSlaDefaultProfile();
        }

        try {
            if ($profileId !== null && $profileId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT p.*, u.name AS updated_by_name,
                        (SELECT COUNT(*) FROM client_companies c WHERE c.sla_profile_id = p.id) AS companies_count
                     FROM sla_profiles p
                     LEFT JOIN users u ON u.id = p.updated_by
                     WHERE p.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $profileId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return systemSlaHydrateProfile($pdo, $row);
                }
            }

            $stmt = $pdo->query(
                'SELECT p.*, u.name AS updated_by_name,
                    (SELECT COUNT(*) FROM client_companies c WHERE c.sla_profile_id = p.id) AS companies_count
                 FROM sla_profiles p
                 LEFT JOIN users u ON u.id = p.updated_by
                 WHERE p.is_default = 1 AND p.is_active = 1
                 ORDER BY p.id ASC
                 LIMIT 1'
            );
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

            if (!$row) {
                $stmt = $pdo->query(
                    'SELECT p.*, u.name AS updated_by_name,
                        (SELECT COUNT(*) FROM client_companies c WHERE c.sla_profile_id = p.id) AS companies_count
                     FROM sla_profiles p
                     LEFT JOIN users u ON u.id = p.updated_by
                     WHERE p.is_active = 1
                     ORDER BY p.id ASC
                     LIMIT 1'
                );
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            }

            return $row ? systemSlaHydrateProfile($pdo, $row) : systemSlaDefaultProfile();
        } catch (Throwable $exception) {
            return systemSlaDefaultProfile();
        }
    }
}

if (!function_exists('systemSlaLog')) {
    function systemSlaLog(
        PDO $pdo,
        string $actionType,
        string $description,
        ?int $profileId = null,
        ?int $actorUserId = null,
        ?array $metadata = null
    ): void {
        if (!systemSlaTableExists($pdo, 'sla_audit_logs')) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO sla_audit_logs
                    (profile_id, actor_user_id, action_type, description, metadata_json, created_at)
                 VALUES
                    (:profile_id, :actor_user_id, :action_type, :description, :metadata_json, NOW())'
            );
            $stmt->execute([
                'profile_id' => $profileId,
                'actor_user_id' => $actorUserId,
                'action_type' => $actionType,
                'description' => $description,
                'metadata_json' => $metadata !== null
                    ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
            ]);
        } catch (Throwable $exception) {
            error_log('[system_sla] No se pudo registrar auditoría: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('systemSlaRecentAudit')) {
    function systemSlaRecentAudit(PDO $pdo, int $limit = 5): array
    {
        if (!systemSlaTableExists($pdo, 'sla_audit_logs')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        try {
            $stmt = $pdo->query(
                "SELECT l.*, p.name AS profile_name, u.name AS actor_name
                 FROM sla_audit_logs l
                 LEFT JOIN sla_profiles p ON p.id = l.profile_id
                 LEFT JOIN users u ON u.id = l.actor_user_id
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT {$limit}"
            );
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            return [];
        }
    }
}

if (!function_exists('systemSlaDateTime')) {
    function systemSlaDateTime(?string $value, string $timezone = 'America/Lima'): ?DateTimeImmutable
    {
        if (empty($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone($timezone));
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('systemSlaTimeToMinutes')) {
    function systemSlaTimeToMinutes(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));
        return (($parts[0] ?? 0) * 60) + ($parts[1] ?? 0);
    }
}

if (!function_exists('systemSlaNormalizeSchedule')) {
    function systemSlaNormalizeSchedule(array $source): array
    {
        $scheduleType = strtoupper((string) ($source['schedule_type'] ?? $source['sla_schedule_type'] ?? 'BUSINESS'));
        if ($scheduleType !== '24_7') {
            $scheduleType = 'BUSINESS';
        }

        return [
            'schedule_type' => $scheduleType,
            'timezone_name' => (string) ($source['timezone_name'] ?? 'America/Lima'),
            'work_start' => (string) ($source['work_start'] ?? $source['sla_work_start'] ?? '08:00:00'),
            'work_end' => (string) ($source['work_end'] ?? $source['sla_work_end'] ?? '17:00:00'),
            'work_days' => systemSlaNormalizeDays($source['work_days'] ?? $source['sla_work_days'] ?? '1,2,3,4,5'),
        ];
    }
}

if (!function_exists('systemSlaIsWorkingMoment')) {
    function systemSlaIsWorkingMoment(DateTimeImmutable $moment, array $schedule): bool
    {
        $schedule = systemSlaNormalizeSchedule($schedule);
        if ($schedule['schedule_type'] === '24_7') {
            return true;
        }

        $days = systemSlaDaysArray($schedule['work_days']);
        if (!in_array((int) $moment->format('N'), $days, true)) {
            return false;
        }

        $minutes = ((int) $moment->format('H') * 60) + (int) $moment->format('i');
        $start = systemSlaTimeToMinutes($schedule['work_start']);
        $end = systemSlaTimeToMinutes($schedule['work_end']);

        return $minutes >= $start && $minutes < $end;
    }
}

if (!function_exists('systemSlaNextWorkingMoment')) {
    function systemSlaNextWorkingMoment(DateTimeImmutable $moment, array $schedule): DateTimeImmutable
    {
        $schedule = systemSlaNormalizeSchedule($schedule);
        if ($schedule['schedule_type'] === '24_7') {
            return $moment;
        }

        $startMinutes = systemSlaTimeToMinutes($schedule['work_start']);
        $endMinutes = systemSlaTimeToMinutes($schedule['work_end']);
        $startHour = intdiv($startMinutes, 60);
        $startMinute = $startMinutes % 60;

        $candidate = $moment;
        for ($guard = 0; $guard < 370; $guard++) {
            $dayAllowed = in_array((int) $candidate->format('N'), systemSlaDaysArray($schedule['work_days']), true);
            $currentMinutes = ((int) $candidate->format('H') * 60) + (int) $candidate->format('i');

            if ($dayAllowed && $currentMinutes < $startMinutes) {
                return $candidate->setTime($startHour, $startMinute, 0);
            }

            if ($dayAllowed && $currentMinutes >= $startMinutes && $currentMinutes < $endMinutes) {
                return $candidate;
            }

            $candidate = $candidate->modify('+1 day')->setTime($startHour, $startMinute, 0);
        }

        return $candidate;
    }
}

if (!function_exists('systemSlaAddMinutes')) {
    function systemSlaAddMinutes(?string $startDateTime, int $minutes, array $schedule): ?string
    {
        $schedule = systemSlaNormalizeSchedule($schedule);
        $start = systemSlaDateTime($startDateTime, $schedule['timezone_name']);

        if (!$start || $minutes < 0) {
            return null;
        }

        if ($schedule['schedule_type'] === '24_7') {
            return $start->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s');
        }

        $cursor = systemSlaNextWorkingMoment($start, $schedule);
        $remaining = $minutes;
        $endMinutes = systemSlaTimeToMinutes($schedule['work_end']);
        $endHour = intdiv($endMinutes, 60);
        $endMinute = $endMinutes % 60;

        while ($remaining > 0) {
            $cursor = systemSlaNextWorkingMoment($cursor, $schedule);
            $workEnd = $cursor->setTime($endHour, $endMinute, 0);
            $available = max(0, (int) floor(($workEnd->getTimestamp() - $cursor->getTimestamp()) / 60));

            if ($available <= 0) {
                $cursor = systemSlaNextWorkingMoment($cursor->modify('+1 day'), $schedule);
                continue;
            }

            if ($remaining <= $available) {
                return $cursor->modify('+' . $remaining . ' minutes')->format('Y-m-d H:i:s');
            }

            $remaining -= $available;
            $cursor = systemSlaNextWorkingMoment($cursor->modify('+1 day'), $schedule);
        }

        return $cursor->format('Y-m-d H:i:s');
    }
}

if (!function_exists('systemSlaElapsedMinutes')) {
    function systemSlaElapsedMinutes(?string $startDateTime, ?string $endDateTime, array $schedule): int
    {
        $schedule = systemSlaNormalizeSchedule($schedule);
        $start = systemSlaDateTime($startDateTime, $schedule['timezone_name']);
        $end = systemSlaDateTime($endDateTime, $schedule['timezone_name']);

        if (!$start || !$end || $end <= $start) {
            return 0;
        }

        if ($schedule['schedule_type'] === '24_7') {
            return max(0, (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60));
        }

        $totalSeconds = 0;
        $cursor = $start->setTime(0, 0, 0);
        $startMinutes = systemSlaTimeToMinutes($schedule['work_start']);
        $endMinutes = systemSlaTimeToMinutes($schedule['work_end']);
        $startHour = intdiv($startMinutes, 60);
        $startMinute = $startMinutes % 60;
        $endHour = intdiv($endMinutes, 60);
        $endMinute = $endMinutes % 60;
        $allowedDays = systemSlaDaysArray($schedule['work_days']);

        while ($cursor < $end) {
            if (in_array((int) $cursor->format('N'), $allowedDays, true)) {
                $workStart = $cursor->setTime($startHour, $startMinute, 0);
                $workEnd = $cursor->setTime($endHour, $endMinute, 0);
                $periodStart = $start > $workStart ? $start : $workStart;
                $periodEnd = $end < $workEnd ? $end : $workEnd;

                if ($periodEnd > $periodStart) {
                    $totalSeconds += $periodEnd->getTimestamp() - $periodStart->getTimestamp();
                }
            }

            $cursor = $cursor->modify('+1 day')->setTime(0, 0, 0);
        }

        return max(0, (int) floor($totalSeconds / 60));
    }
}

if (!function_exists('systemSlaResolveForRequester')) {
    function systemSlaResolveForRequester(
        PDO $pdo,
        int $requesterId,
        string $priority,
        ?string $createdAt = null
    ): array {
        $priority = strtoupper(trim($priority));
        $targets = systemSlaDefaultTargets();
        $fallbackProfile = systemSlaDefaultProfile();
        $companyId = null;
        $legacyContract = '8_5';

        try {
            $stmt = $pdo->prepare(
                'SELECT u.company_id, c.sla_contract_type, c.sla_profile_id
                 FROM users u
                 LEFT JOIN client_companies c ON c.id = u.company_id
                 WHERE u.id = :user_id
                 LIMIT 1'
            );
            $stmt->execute(['user_id' => $requesterId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $companyId = !empty($row['company_id']) ? (int) $row['company_id'] : null;
            $legacyContract = ($row['sla_contract_type'] ?? '8_5') === '24_7' ? '24_7' : '8_5';
            $profileId = !empty($row['sla_profile_id']) ? (int) $row['sla_profile_id'] : null;
        } catch (Throwable $exception) {
            $profileId = null;
        }

        if (systemSlaModuleReady($pdo)) {
            $profile = systemSlaGetProfile($pdo, $profileId);
            if (empty($profile['is_active'])) {
                $profile = systemSlaGetProfile($pdo, null);
            }
        } else {
            $profile = $fallbackProfile;
            if ($legacyContract === '24_7') {
                $profile['name'] = 'SLA Continuo 24/7';
                $profile['schedule_type'] = '24_7';
                $profile['work_start'] = '00:00:00';
                $profile['work_end'] = '23:59:59';
                $profile['work_days'] = '1,2,3,4,5,6,7';
            }
        }

        $targets = $profile['targets'] ?? $targets;
        $target = $targets[$priority] ?? $targets['MEDIA'];
        $createdAt = $createdAt ?: date('Y-m-d H:i:s');
        $schedule = systemSlaNormalizeSchedule($profile);
        $ttaMinutes = max(1, (int) ($target['tta_minutes'] ?? 120));
        $ttrMinutes = max($ttaMinutes, (int) ($target['ttr_minutes'] ?? 1440));

        return [
            'company_id' => $companyId,
            'profile_id' => !empty($profile['id']) ? (int) $profile['id'] : null,
            'profile_name' => (string) ($profile['name'] ?? 'SLA Estándar 8/5'),
            'schedule_type' => $schedule['schedule_type'],
            'work_start' => $schedule['work_start'],
            'work_end' => $schedule['work_end'],
            'work_days' => $schedule['work_days'],
            'warning_percent' => (int) ($profile['warning_percent'] ?? 75),
            'critical_percent' => (int) ($profile['critical_percent'] ?? 90),
            'tta_minutes' => $ttaMinutes,
            'ttr_minutes' => $ttrMinutes,
            'tta_due_at' => systemSlaAddMinutes($createdAt, $ttaMinutes, $schedule),
            'ttr_due_at' => systemSlaAddMinutes($createdAt, $ttrMinutes, $schedule),
            'legacy_contract_type' => $schedule['schedule_type'] === '24_7' ? '24_7' : '8_5',
            'pause_statuses' => $profile['pause_statuses'] ?? ['RESPONDIDO'],
        ];
    }
}

if (!function_exists('systemSlaTicketSchedule')) {
    function systemSlaTicketSchedule(array $ticket): array
    {
        $scheduleType = strtoupper((string) ($ticket['sla_schedule_type'] ?? ''));
        if ($scheduleType === '') {
            $scheduleType = (($ticket['sla_contract_type'] ?? '8_5') === '24_7') ? '24_7' : 'BUSINESS';
        }

        return systemSlaNormalizeSchedule([
            'schedule_type' => $scheduleType,
            'work_start' => $ticket['sla_work_start'] ?? '08:00:00',
            'work_end' => $ticket['sla_work_end'] ?? '17:00:00',
            'work_days' => $ticket['sla_work_days'] ?? ($scheduleType === '24_7' ? '1,2,3,4,5,6,7' : '1,2,3,4,5'),
        ]);
    }
}

if (!function_exists('systemSlaTicketElapsedMinutes')) {
    function systemSlaTicketElapsedMinutes(array $ticket, ?string $endAt = null): int
    {
        $endAt = $endAt ?: (($ticket['status'] ?? '') === 'CERRADO'
            ? (string) ($ticket['closed_at'] ?? $ticket['updated_at'] ?? date('Y-m-d H:i:s'))
            : date('Y-m-d H:i:s'));

        $elapsed = systemSlaElapsedMinutes(
            $ticket['created_at'] ?? null,
            $endAt,
            systemSlaTicketSchedule($ticket)
        );

        $paused = max(0, (int) ($ticket['sla_paused_minutes'] ?? 0));
        if (!empty($ticket['sla_pause_started_at']) && ($ticket['status'] ?? '') !== 'CERRADO') {
            $paused += systemSlaElapsedMinutes(
                $ticket['sla_pause_started_at'],
                $endAt,
                systemSlaTicketSchedule($ticket)
            );
        }

        return max(0, $elapsed - $paused);
    }
}

if (!function_exists('systemSlaPauseStatusesForTicket')) {
    function systemSlaPauseStatusesForTicket(PDO $pdo, array $ticket): array
    {
        $profileId = (int) ($ticket['sla_profile_id'] ?? 0);
        return $profileId > 0 ? systemSlaGetPauseStatuses($pdo, $profileId) : ['RESPONDIDO'];
    }
}

if (!function_exists('systemSlaSyncPauseState')) {
    function systemSlaSyncPauseState(
        PDO $pdo,
        int $ticketId,
        string $oldStatus,
        string $newStatus,
        ?string $changedAt = null
    ): void {
        if (!systemSlaColumnExists($pdo, 'tickets', 'sla_pause_started_at')) {
            return;
        }

        $changedAt = $changedAt ?: date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            return;
        }

        $pauseStatuses = systemSlaPauseStatusesForTicket($pdo, $ticket);
        $wasPaused = in_array(strtoupper($oldStatus), $pauseStatuses, true);
        $shouldPause = in_array(strtoupper($newStatus), $pauseStatuses, true);
        $pauseStartedAt = $ticket['sla_pause_started_at'] ?? null;

        if (!$wasPaused && $shouldPause && empty($pauseStartedAt)) {
            $stmt = $pdo->prepare(
                'UPDATE tickets SET sla_pause_started_at = :changed_at WHERE id = :id'
            );
            $stmt->execute(['changed_at' => $changedAt, 'id' => $ticketId]);
            return;
        }

        if (($wasPaused || !empty($pauseStartedAt)) && !$shouldPause && !empty($pauseStartedAt)) {
            $extraPaused = systemSlaElapsedMinutes(
                $pauseStartedAt,
                $changedAt,
                systemSlaTicketSchedule($ticket)
            );
            $ttaDueAt = !empty($ticket['sla_tta_due_at'])
                ? systemSlaAddMinutes((string)$ticket['sla_tta_due_at'], $extraPaused, systemSlaTicketSchedule($ticket))
                : null;
            $ttrDueAt = !empty($ticket['sla_ttr_due_at'])
                ? systemSlaAddMinutes((string)$ticket['sla_ttr_due_at'], $extraPaused, systemSlaTicketSchedule($ticket))
                : null;

            $stmt = $pdo->prepare(
                'UPDATE tickets
                 SET sla_paused_minutes = COALESCE(sla_paused_minutes, 0) + :extra_paused,
                     sla_pause_started_at = NULL,
                     sla_tta_due_at = COALESCE(:tta_due_at, sla_tta_due_at),
                     sla_ttr_due_at = COALESCE(:ttr_due_at, sla_ttr_due_at)
                 WHERE id = :id'
            );
            $stmt->execute([
                'extra_paused' => $extraPaused,
                'tta_due_at' => $ttaDueAt,
                'ttr_due_at' => $ttrDueAt,
                'id' => $ticketId,
            ]);
        }
    }
}

if (!function_exists('systemSlaMarkFirstResponse')) {
    function systemSlaMarkFirstResponse(PDO $pdo, int $ticketId, array $ticket, ?string $respondedAt = null): void
    {
        $respondedAt = $respondedAt ?: date('Y-m-d H:i:s');
        $ttaTarget = (int) ($ticket['sla_tta_minutes'] ?? 0);
        $elapsed = systemSlaElapsedMinutes(
            $ticket['created_at'] ?? null,
            $respondedAt,
            systemSlaTicketSchedule($ticket)
        );
        $ttaMet = $ttaTarget > 0 ? ($elapsed <= $ttaTarget ? 1 : 0) : null;

        $sql = 'UPDATE tickets SET first_response_at = :responded_at';
        $params = ['responded_at' => $respondedAt, 'id' => $ticketId];

        if (systemSlaColumnExists($pdo, 'tickets', 'sla_tta_met')) {
            $sql .= ', sla_tta_met = :tta_met';
            $params['tta_met'] = $ttaMet;
        }

        $sql .= ' WHERE id = :id AND first_response_at IS NULL';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if (!function_exists('systemSlaCloseMetrics')) {
    function systemSlaCloseMetrics(array $ticket, ?string $closedAt = null): array
    {
        $closedAt = $closedAt ?: date('Y-m-d H:i:s');
        $elapsed = systemSlaTicketElapsedMinutes($ticket, $closedAt);
        $target = (int) ($ticket['sla_ttr_minutes'] ?? 0);

        if ($target <= 0) {
            $target = max(1, (int) round(((float) ($ticket['sla_hours'] ?? 24)) * 60));
        }

        $met = $elapsed <= $target ? 1 : 0;
        return ['elapsed_minutes' => $elapsed, 'target_minutes' => $target, 'met' => $met];
    }
}

if (!function_exists('formatSlaMinutes')) {
    function formatSlaMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $days = intdiv($minutes, 1440);
        $remaining = $minutes % 1440;
        $hours = intdiv($remaining, 60);
        $mins = $remaining % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' d';
        }
        if ($hours > 0) {
            $parts[] = $hours . ' h';
        }
        if ($mins > 0 || !$parts) {
            $parts[] = $mins . ' min';
        }

        return implode(' ', $parts);
    }
}
