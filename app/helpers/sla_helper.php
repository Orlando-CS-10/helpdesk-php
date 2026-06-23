<?php

if (date_default_timezone_get() !== 'America/Lima') {
    date_default_timezone_set('America/Lima');
}

require_once __DIR__ . '/business_hours.php';

if (!function_exists('getTicketSlaContractType')) {
    function getTicketSlaContractType(array $ticket): string
    {
        return normalizeSlaContractType(
            $ticket['sla_contract_type']
            ?? $ticket['company_sla_contract_type']
            ?? $ticket['contract_type']
            ?? '8_5'
        );
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

if (!function_exists('getSlaElapsedHoursForTicket')) {
    function getSlaElapsedHoursForTicket(
        array $ticket,
        ?string $endDateTime = null
    ): float {
        if (empty($ticket['created_at'])) {
            return 0;
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
        $slaHours = (float)($ticket['sla_hours'] ?? 0);

        if (empty($ticket['created_at']) || $slaHours <= 0) {
            return null;
        }

        return addSlaHoursToDateTime(
            $ticket['created_at'],
            $slaHours,
            getTicketSlaContractType($ticket)
        );
    }
}

if (!function_exists('getSlaProgressPercent')) {
    function getSlaProgressPercent(array $ticket): float
    {
        $slaHours = (float)($ticket['sla_hours'] ?? 0);

        if ($slaHours <= 0) {
            return 0;
        }

        return max(0, (getSlaElapsedHoursForTicket($ticket) / $slaHours) * 100);
    }
}

if (!function_exists('getSlaStatusLabel')) {
    function getSlaStatusLabel(array $ticket): string
    {
        $isClosed = ($ticket['status'] ?? '') === 'CERRADO';
        $slaHours = (float)($ticket['sla_hours'] ?? 0);

        if ($slaHours <= 0 || empty($ticket['created_at'])) {
            return 'SLA no definido';
        }

        if ($isClosed && array_key_exists('sla_met', $ticket) && $ticket['sla_met'] !== null) {
            return (int)$ticket['sla_met'] === 1
                ? 'Cerrado con SLA cumplido'
                : 'Cerrado fuera del SLA';
        }

        $progress = getSlaProgressPercent($ticket);

        if ($isClosed) {
            return $progress <= 100
                ? 'Cerrado con SLA cumplido'
                : 'Cerrado fuera del SLA';
        }

        if ($progress >= 100) {
            return 'SLA vencido';
        }

        if ($progress >= 75) {
            return 'Próximo a vencer';
        }

        if ($progress >= 50) {
            return 'En seguimiento';
        }

        return 'Dentro del SLA';
    }
}

if (!function_exists('getSlaStatusClass')) {
    function getSlaStatusClass(array $ticket): string
    {
        return match (getSlaStatusLabel($ticket)) {
            'Dentro del SLA', 'Cerrado con SLA cumplido' => 'success-pill',
            'En seguimiento', 'Próximo a vencer' => 'pending-pill',
            'SLA vencido', 'Cerrado fuera del SLA' => 'danger-pill',
            default => 'neutral-pill',
        };
    }
}

if (!function_exists('getSlaTimerData')) {
    function getSlaTimerData(array $ticket): array
    {
        $contractType = getTicketSlaContractType($ticket);
        $slaHours = (float)($ticket['sla_hours'] ?? 0);
        $elapsedHours = getSlaElapsedHoursForTicket($ticket);
        $remainingSigned = $slaHours - $elapsedHours;
        $isClosed = ($ticket['status'] ?? '') === 'CERRADO';
        $isPaused = !$isClosed
            && $contractType === '8_5'
            && !isWithinSlaSchedule(date('Y-m-d H:i:s'), $contractType);
        $progressRaw = $slaHours > 0 ? ($elapsedHours / $slaHours) * 100 : 0;
        $statusLabel = getSlaStatusLabel($ticket);
        $deadline = getSlaDeadlineForTicket($ticket);

        $phaseClass = match ($statusLabel) {
            'Dentro del SLA', 'Cerrado con SLA cumplido' => 'sla-phase-green',
            'En seguimiento', 'Próximo a vencer' => 'sla-phase-yellow',
            'SLA vencido', 'Cerrado fuera del SLA' => 'sla-phase-red',
            default => 'sla-phase-paused',
        };

        if ($isPaused && !$isClosed) {
            $phaseClass = 'sla-phase-paused';
        }

        $note = match (true) {
            $slaHours <= 0 => 'Este ticket no tiene un SLA objetivo configurado.',
            $isClosed && $statusLabel === 'Cerrado con SLA cumplido'
                => 'El ticket finalizó dentro del tiempo establecido.',
            $isClosed && $statusLabel === 'Cerrado fuera del SLA'
                => 'El ticket finalizó después del tiempo establecido.',
            $isPaused
                => 'El contrato 8/5 está fuera del horario de lunes a viernes, 08:00 a 17:00.',
            $progressRaw >= 100
                => 'El tiempo objetivo fue superado y el ticket requiere atención prioritaria.',
            $progressRaw >= 75
                => 'El ticket está cerca de consumir el tiempo objetivo.',
            $progressRaw >= 50
                => 'El ticket consumió más de la mitad del SLA.',
            default
                => 'El ticket se encuentra dentro del tiempo objetivo.',
        };

        return [
            'contract_type' => $contractType,
            'contract_label' => getSlaContractLabel($contractType),
            'sla_hours' => $slaHours,
            'elapsed_hours' => $elapsedHours,
            'remaining_hours' => max(0, $remainingSigned),
            'remaining_signed_hours' => $remainingSigned,
            'overtime_hours' => max(0, -$remainingSigned),
            'progress_percent' => min(100, max(0, $progressRaw)),
            'progress_raw_percent' => max(0, $progressRaw),
            'phase_class' => $phaseClass,
            'phase_label' => $isPaused && !$isClosed ? 'Conteo pausado' : $statusLabel,
            'status_label' => $statusLabel,
            'tooltip' => $note,
            'note' => $note,
            'is_paused' => $isPaused,
            'is_running' => !$isClosed && !$isPaused && $slaHours > 0,
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
        $firstResponse = $ticket['level_first_response_at']
            ?? $ticket['first_response_at']
            ?? null;

        if (empty($firstResponse) || empty($ticket['created_at'])) {
            return null;
        }

        return calculateSlaElapsedHours(
            $ticket['created_at'],
            $firstResponse,
            getTicketSlaContractType($ticket)
        );
    }
}

if (!function_exists('getTicketTtrHours')) {
    function getTicketTtrHours(array $ticket): ?float
    {
        if (($ticket['status'] ?? '') !== 'CERRADO') {
            return null;
        }

        $closedAt = $ticket['closed_at']
            ?? $ticket['updated_at']
            ?? null;

        if (empty($closedAt) || empty($ticket['created_at'])) {
            return null;
        }

        return calculateSlaElapsedHours(
            $ticket['created_at'],
            $closedAt,
            getTicketSlaContractType($ticket)
        );
    }
}
