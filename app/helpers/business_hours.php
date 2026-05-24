<?php

function calculateBusinessHours(?string $startDateTime, ?string $endDateTime): float
{
    if (empty($startDateTime) || empty($endDateTime)) {
        return 0;
    }

    try {
        $start = new DateTime($startDateTime);
        $end = new DateTime($endDateTime);
    } catch (Exception $e) {
        return 0;
    }

    if ($end <= $start) {
        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Horario laboral
    |--------------------------------------------------------------------------
    | Lunes a sábado
    | Desde 08:00 am hasta 05:50 pm
    */
    $businessStartHour = 8;
    $businessStartMinute = 0;

    $businessEndHour = 17;
    $businessEndMinute = 50;

    $totalMinutes = 0;
    $current = clone $start;

    while ($current < $end) {
        $dayOfWeek = (int)$current->format('N'); // 1 = lunes, 7 = domingo

        // Solo cuenta de lunes a sábado
        if ($dayOfWeek <= 6) {
            $workStart = clone $current;
            $workStart->setTime($businessStartHour, $businessStartMinute, 0);

            $workEnd = clone $current;
            $workEnd->setTime($businessEndHour, $businessEndMinute, 0);

            $periodStart = $current > $workStart ? clone $current : clone $workStart;
            $periodEnd = $end < $workEnd ? clone $end : clone $workEnd;

            if ($periodEnd > $periodStart) {
                $totalMinutes += ($periodEnd->getTimestamp() - $periodStart->getTimestamp()) / 60;
            }
        }

        $current->modify('tomorrow');
        $current->setTime(0, 0, 0);
    }

    return round($totalMinutes / 60, 2);
}

/**
 * Convierte horas decimales a formato HH:MM:SS.
 *
 * Ejemplo:
 * 1.5 => 01:30:00
 * 0.25 => 00:15:00
 */
function formatDecimalHoursToClock(float|int|string|null $hours): string
{
    if ($hours === null || $hours === '' || !is_numeric($hours)) {
        return '00:00:00';
    }

    $totalSeconds = (int) round(((float)$hours) * 3600);

    $h = intdiv($totalSeconds, 3600);
    $m = intdiv($totalSeconds % 3600, 60);
    $s = $totalSeconds % 60;

    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}