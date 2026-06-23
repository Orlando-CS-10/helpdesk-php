<?php

if (date_default_timezone_get() !== 'America/Lima') {
    date_default_timezone_set('America/Lima');
}

if (!function_exists('normalizeSlaContractType')) {
    function normalizeSlaContractType(?string $contractType): string
    {
        $value = strtolower(trim((string)$contractType));

        return match ($value) {
            '24_7', '24/7', '247', '24x7', '24-7' => '24_7',
            default => '8_5',
        };
    }
}

if (!function_exists('getSlaContractLabel')) {
    function getSlaContractLabel(?string $contractType): string
    {
        return normalizeSlaContractType($contractType) === '24_7'
            ? 'Contrato 24/7'
            : 'Contrato 8/5';
    }
}

if (!function_exists('slaDateTime')) {
    function slaDateTime(?string $value): ?DateTimeImmutable
    {
        if (empty($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('America/Lima'));
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('calculateCalendarHours')) {
    function calculateCalendarHours(?string $startDateTime, ?string $endDateTime): float
    {
        $start = slaDateTime($startDateTime);
        $end = slaDateTime($endDateTime);

        if (!$start || !$end || $end <= $start) {
            return 0;
        }

        return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 4);
    }
}

if (!function_exists('calculateBusinessHours')) {
    function calculateBusinessHours(?string $startDateTime, ?string $endDateTime): float
    {
        $start = slaDateTime($startDateTime);
        $end = slaDateTime($endDateTime);

        if (!$start || !$end || $end <= $start) {
            return 0;
        }

        $businessStartHour = 8;
        $businessEndHour = 17;
        $totalSeconds = 0;
        $cursor = $start->setTime(0, 0, 0);

        while ($cursor < $end) {
            $dayOfWeek = (int)$cursor->format('N');

            if ($dayOfWeek <= 5) {
                $workStart = $cursor->setTime($businessStartHour, 0, 0);
                $workEnd = $cursor->setTime($businessEndHour, 0, 0);

                $periodStart = $start > $workStart ? $start : $workStart;
                $periodEnd = $end < $workEnd ? $end : $workEnd;

                if ($periodEnd > $periodStart) {
                    $totalSeconds += $periodEnd->getTimestamp() - $periodStart->getTimestamp();
                }
            }

            $cursor = $cursor->modify('+1 day')->setTime(0, 0, 0);
        }

        return round($totalSeconds / 3600, 4);
    }
}

if (!function_exists('calculateSlaElapsedHours')) {
    function calculateSlaElapsedHours(
        ?string $startDateTime,
        ?string $endDateTime,
        ?string $contractType = '8_5'
    ): float {
        return normalizeSlaContractType($contractType) === '24_7'
            ? calculateCalendarHours($startDateTime, $endDateTime)
            : calculateBusinessHours($startDateTime, $endDateTime);
    }
}

if (!function_exists('isWithinBusinessHours')) {
    function isWithinBusinessHours(?string $dateTime): bool
    {
        $date = slaDateTime($dateTime);

        if (!$date) {
            return false;
        }

        $dayOfWeek = (int)$date->format('N');

        if ($dayOfWeek > 5) {
            return false;
        }

        $minutes = ((int)$date->format('H') * 60) + (int)$date->format('i');

        return $minutes >= (8 * 60) && $minutes < (17 * 60);
    }
}

if (!function_exists('isWithinSlaSchedule')) {
    function isWithinSlaSchedule(
        ?string $dateTime,
        ?string $contractType = '8_5'
    ): bool {
        if (normalizeSlaContractType($contractType) === '24_7') {
            return !empty($dateTime);
        }

        return isWithinBusinessHours($dateTime);
    }
}

if (!function_exists('nextBusinessMoment')) {
    function nextBusinessMoment(DateTimeImmutable $date): DateTimeImmutable
    {
        $candidate = $date;

        for ($guard = 0; $guard < 14; $guard++) {
            $dayOfWeek = (int)$candidate->format('N');
            $minutes = ((int)$candidate->format('H') * 60) + (int)$candidate->format('i');

            if ($dayOfWeek <= 5) {
                if ($minutes < 8 * 60) {
                    return $candidate->setTime(8, 0, 0);
                }

                if ($minutes < 17 * 60) {
                    return $candidate;
                }
            }

            $candidate = $candidate->modify('+1 day')->setTime(8, 0, 0);
        }

        return $candidate;
    }
}

if (!function_exists('addBusinessHoursToDateTime')) {
    function addBusinessHoursToDateTime(
        ?string $startDateTime,
        float $hours
    ): ?string {
        $start = slaDateTime($startDateTime);

        if (!$start || $hours < 0) {
            return null;
        }

        $cursor = nextBusinessMoment($start);
        $remainingSeconds = (int)round($hours * 3600);

        while ($remainingSeconds > 0) {
            $cursor = nextBusinessMoment($cursor);
            $workEnd = $cursor->setTime(17, 0, 0);
            $availableSeconds = max(0, $workEnd->getTimestamp() - $cursor->getTimestamp());

            if ($availableSeconds <= 0) {
                $cursor = nextBusinessMoment($cursor->modify('+1 day')->setTime(8, 0, 0));
                continue;
            }

            if ($remainingSeconds <= $availableSeconds) {
                $cursor = $cursor->modify('+' . $remainingSeconds . ' seconds');
                $remainingSeconds = 0;
                break;
            }

            $remainingSeconds -= $availableSeconds;
            $cursor = nextBusinessMoment($cursor->modify('+1 day')->setTime(8, 0, 0));
        }

        return $cursor->format('Y-m-d H:i:s');
    }
}

if (!function_exists('addSlaHoursToDateTime')) {
    function addSlaHoursToDateTime(
        ?string $startDateTime,
        float $hours,
        ?string $contractType = '8_5'
    ): ?string {
        $start = slaDateTime($startDateTime);

        if (!$start || $hours < 0) {
            return null;
        }

        if (normalizeSlaContractType($contractType) === '24_7') {
            return $start
                ->modify('+' . (int)round($hours * 3600) . ' seconds')
                ->format('Y-m-d H:i:s');
        }

        return addBusinessHoursToDateTime($startDateTime, $hours);
    }
}

if (!function_exists('formatDecimalHoursToClock')) {
    function formatDecimalHoursToClock(float|int|string|null $hours): string
    {
        if ($hours === null || $hours === '' || !is_numeric($hours)) {
            return '00:00:00';
        }

        $totalSeconds = max(0, (int)round(((float)$hours) * 3600));
        $h = intdiv($totalSeconds, 3600);
        $m = intdiv($totalSeconds % 3600, 60);
        $s = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

if (!function_exists('formatBusinessTimeStatus')) {
    function formatBusinessTimeStatus(
        ?string $startDateTime,
        ?string $endDateTime,
        bool $isPending = false
    ): string {
        if ($isPending || empty($endDateTime)) {
            return 'Pendiente';
        }

        $hours = calculateBusinessHours($startDateTime, $endDateTime);

        return $hours > 0
            ? formatDecimalHoursToClock($hours)
            : 'Fuera de horario';
    }
}

if (!function_exists('formatSlaDuration')) {
    function formatSlaDuration(float|int|string|null $hours): string
    {
        if ($hours === null || $hours === '' || !is_numeric($hours)) {
            return 'Sin datos';
        }

        $totalSeconds = max(0, (int)round((float)$hours * 3600));
        $days = intdiv($totalSeconds, 86400);
        $remaining = $totalSeconds % 86400;
        $h = intdiv($remaining, 3600);
        $m = intdiv($remaining % 3600, 60);

        if ($days > 0) {
            return $days . ' d ' . $h . ' h ' . $m . ' min';
        }

        if ($h > 0) {
            return $h . ' h ' . $m . ' min';
        }

        return max(0, $m) . ' min';
    }
}
