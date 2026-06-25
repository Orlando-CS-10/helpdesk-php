<?php

if (date_default_timezone_get() !== 'America/Lima') {
    date_default_timezone_set('America/Lima');
}

require_once __DIR__ . '/business_hours.php';
require_once __DIR__ . '/system_sla.php';

if (!function_exists('getTicketSlaContractType')) {
    function getTicketSlaContractType(array $ticket): string
    {
        $scheduleType = strtoupper((string)($ticket['sla_schedule_type'] ?? ''));
        if ($scheduleType === '24_7') {
            return '24_7';
        }
        if ($scheduleType === 'BUSINESS') {
            return '8_5';
        }

        return normalizeSlaContractType(
            $ticket['sla_contract_type']
            ?? $ticket['company_sla_contract_type']
            ?? $ticket['contract_type']
            ?? '8_5'
        );
    }
}

if (!function_exists('getTicketSlaProfileLabel')) {
    function getTicketSlaProfileLabel(array $ticket): string
    {
        $name = trim((string)($ticket['sla_profile_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return getSlaContractLabel(getTicketSlaContractType($ticket));
    }
}

if (!function_exists('getTicketSlaEndDateTime')) {
    function getTicketSlaEndDateTime(array $ticket): string
    {
        if (($ticket['status'] ?? '') === 'CERRADO') {
            return (string)(
                $ticket['closed_at']
                ?? $ticket['updated_at']
                ?? date('Y-m-d H:i:s')
            );
        }

        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('getTicketSlaTargetMinutes')) {
    function getTicketSlaTargetMinutes(array $ticket): int
    {
        $minutes = (int)($ticket['sla_ttr_minutes'] ?? 0);
        if ($minutes > 0) {
            return $minutes;
        }

        return max(0, (int)round(((float)($ticket['sla_hours'] ?? 0)) * 60));
    }
}

if (!function_exists('getSlaElapsedHoursForTicket')) {
    function getSlaElapsedHoursForTicket(array $ticket, ?string $endDateTime = null): float
    {
        if (empty($ticket['created_at'])) {
            return 0;
        }

        if (function_exists('systemSlaTicketElapsedMinutes')) {
            return round(systemSlaTicketElapsedMinutes($ticket, $endDateTime) / 60, 4);
        }

        return calculateSlaElapsedHours(
            $ticket['created_at'],
            $endDateTime ?? getTicketSlaEndDateTime($ticket),
            getTicketSlaContractType($ticket)
        );
    }
}

if (!function_exists('getSlaDeadlineForTicket')) {
    function getSlaDeadlineForTicket(array $ticket): ?string
    {
        if (!empty($ticket['sla_ttr_due_at'])) {
            return (string)$ticket['sla_ttr_due_at'];
        }

        $targetMinutes = getTicketSlaTargetMinutes($ticket);
        if (empty($ticket['created_at']) || $targetMinutes <= 0) {
            return null;
        }

        if (function_exists('systemSlaAddMinutes')) {
            return systemSlaAddMinutes(
                $ticket['created_at'],
                $targetMinutes,
                systemSlaTicketSchedule($ticket)
            );
        }

        return addSlaHoursToDateTime(
            $ticket['created_at'],
            $targetMinutes / 60,
            getTicketSlaContractType($ticket)
        );
    }
}

if (!function_exists('getSlaProgressPercent')) {
    function getSlaProgressPercent(array $ticket): float
    {
        $targetMinutes = getTicketSlaTargetMinutes($ticket);
        if ($targetMinutes <= 0) {
            return 0;
        }

        return max(0, (getSlaElapsedHoursForTicket($ticket) * 60 / $targetMinutes) * 100);
    }
}

if (!function_exists('getTicketSlaThresholds')) {
    function getTicketSlaThresholds(array $ticket): array
    {
        $warning = (int)($ticket['sla_warning_percent'] ?? 75);
        $critical = (int)($ticket['sla_critical_percent'] ?? 90);

        $warning = max(25, min(95, $warning));
        $critical = max($warning + 1, min(99, $critical));

        return ['warning' => $warning, 'critical' => $critical];
    }
}

if (!function_exists('getSlaStatusLabel')) {
    function getSlaStatusLabel(array $ticket): string
    {
        $isClosed = ($ticket['status'] ?? '') === 'CERRADO';
        $targetMinutes = getTicketSlaTargetMinutes($ticket);

        if ($targetMinutes <= 0 || empty($ticket['created_at'])) {
            return 'SLA no definido';
        }

        if ($isClosed && array_key_exists('sla_ttr_met', $ticket) && $ticket['sla_ttr_met'] !== null) {
            return (int)$ticket['sla_ttr_met'] === 1
                ? 'Cerrado con SLA cumplido'
                : 'Cerrado fuera del SLA';
        }

        if ($isClosed && array_key_exists('sla_met', $ticket) && $ticket['sla_met'] !== null) {
            return (int)$ticket['sla_met'] === 1
                ? 'Cerrado con SLA cumplido'
                : 'Cerrado fuera del SLA';
        }

        $progress = getSlaProgressPercent($ticket);
        $thresholds = getTicketSlaThresholds($ticket);

        if ($isClosed) {
            return $progress <= 100 ? 'Cerrado con SLA cumplido' : 'Cerrado fuera del SLA';
        }
        if ($progress >= 100) {
            return 'SLA vencido';
        }
        if ($progress >= $thresholds['critical']) {
            return 'Alerta crítica';
        }
        if ($progress >= $thresholds['warning']) {
            return 'Próximo a vencer';
        }

        return 'Dentro del SLA';
    }
}

if (!function_exists('getSlaStatusClass')) {
    function getSlaStatusClass(array $ticket): string
    {
        return match (getSlaStatusLabel($ticket)) {
            'Dentro del SLA', 'Cerrado con SLA cumplido' => 'success-pill',
            'Próximo a vencer' => 'pending-pill',
            'Alerta crítica', 'SLA vencido', 'Cerrado fuera del SLA' => 'danger-pill',
            default => 'neutral-pill',
        };
    }
}

if (!function_exists('getSlaTimerData')) {
    function getSlaTimerData(array $ticket): array
    {
        $contractType = getTicketSlaContractType($ticket);
        $targetMinutes = getTicketSlaTargetMinutes($ticket);
        $slaHours = $targetMinutes / 60;
        $elapsedHours = getSlaElapsedHoursForTicket($ticket);
        $remainingSigned = $slaHours - $elapsedHours;
        $isClosed = ($ticket['status'] ?? '') === 'CERRADO';
        $schedule = systemSlaTicketSchedule($ticket);
        $isPausedByStatus = !$isClosed && !empty($ticket['sla_pause_started_at']);
        $isPausedBySchedule = !$isClosed
            && $schedule['schedule_type'] !== '24_7'
            && !systemSlaIsWorkingMoment(new DateTimeImmutable('now', new DateTimeZone('America/Lima')), $schedule);
        $isPaused = $isPausedByStatus || $isPausedBySchedule;
        $progressRaw = $slaHours > 0 ? ($elapsedHours / $slaHours) * 100 : 0;
        $statusLabel = getSlaStatusLabel($ticket);
        $deadline = getSlaDeadlineForTicket($ticket);
        $thresholds = getTicketSlaThresholds($ticket);

        $phaseClass = match ($statusLabel) {
            'Dentro del SLA', 'Cerrado con SLA cumplido' => 'sla-phase-green',
            'Próximo a vencer' => 'sla-phase-yellow',
            'Alerta crítica', 'SLA vencido', 'Cerrado fuera del SLA' => 'sla-phase-red',
            default => 'sla-phase-paused',
        };

        if ($isPaused && !$isClosed) {
            $phaseClass = 'sla-phase-paused';
        }

        $note = match (true) {
            $targetMinutes <= 0 => 'Este ticket no tiene un objetivo TTR configurado.',
            $isClosed && $statusLabel === 'Cerrado con SLA cumplido' => 'El ticket finalizó dentro del tiempo establecido.',
            $isClosed && $statusLabel === 'Cerrado fuera del SLA' => 'El ticket finalizó después del tiempo establecido.',
            $isPausedByStatus => 'El contador se encuentra pausado por el estado actual del ticket.',
            $isPausedBySchedule => 'El contador está fuera del horario de atención configurado.',
            $progressRaw >= 100 => 'El tiempo objetivo fue superado y el ticket requiere atención prioritaria.',
            $progressRaw >= $thresholds['critical'] => 'El ticket alcanzó el nivel crítico del SLA.',
            $progressRaw >= $thresholds['warning'] => 'El ticket está próximo a consumir el tiempo objetivo.',
            default => 'El ticket se encuentra dentro del tiempo objetivo.',
        };

        return [
            'contract_type' => $contractType,
            'contract_label' => getTicketSlaProfileLabel($ticket),
            'sla_hours' => $slaHours,
            'sla_minutes' => $targetMinutes,
            'tta_minutes' => (int)($ticket['sla_tta_minutes'] ?? 0),
            'elapsed_hours' => $elapsedHours,
            'remaining_hours' => max(0, $remainingSigned),
            'remaining_signed_hours' => $remainingSigned,
            'overtime_hours' => max(0, -$remainingSigned),
            'progress_percent' => min(100, max(0, $progressRaw)),
            'progress_raw_percent' => max(0, $progressRaw),
            'warning_percent' => $thresholds['warning'],
            'critical_percent' => $thresholds['critical'],
            'phase_class' => $phaseClass,
            'phase_label' => $isPaused && !$isClosed ? 'Conteo pausado' : $statusLabel,
            'status_label' => $statusLabel,
            'tooltip' => $note,
            'note' => $note,
            'is_paused' => $isPaused,
            'is_running' => !$isClosed && !$isPaused && $targetMinutes > 0,
            'is_closed' => $isClosed,
            'deadline' => $deadline,
            'started_at' => $ticket['created_at'] ?? null,
            'ended_at' => $isClosed ? getTicketSlaEndDateTime($ticket) : null,
        ];
    }
}

if (!function_exists('getTicketTtaHours')) {
    function getTicketTtaHours(array $ticket): ?float
    {
        $firstResponse = $ticket['first_response_at']
            ?? $ticket['level_first_response_at']
            ?? null;

        if (empty($firstResponse) || empty($ticket['created_at'])) {
            return null;
        }

        return round(systemSlaElapsedMinutes(
            $ticket['created_at'],
            $firstResponse,
            systemSlaTicketSchedule($ticket)
        ) / 60, 4);
    }
}

if (!function_exists('getTicketTtrHours')) {
    function getTicketTtrHours(array $ticket): ?float
    {
        if (($ticket['status'] ?? '') !== 'CERRADO') {
            return null;
        }

        $closedAt = $ticket['closed_at'] ?? $ticket['updated_at'] ?? null;
        if (empty($closedAt) || empty($ticket['created_at'])) {
            return null;
        }

        return round(systemSlaTicketElapsedMinutes($ticket, $closedAt) / 60, 4);
    }
}
