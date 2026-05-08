<?php

function calculateBusinessHours($startDateTime, $endDateTime): float
{
    if (empty($startDateTime) || empty($endDateTime)) {
        return 0;
    }

    $start = new DateTime($startDateTime);
    $end = new DateTime($endDateTime);

    if ($end <= $start) {
        return 0;
    }

    $businessStartHour = 8;
    $businessEndHour = 17.30;

    $totalMinutes = 0;
    $current = clone $start;

    while ($current < $end) {
        $dayOfWeek = (int)$current->format('N'); // 1 lunes, 7 domingo

        if ($dayOfWeek <= 6) {
            $workStart = clone $current;
            $workStart->setTime($businessStartHour, 0, 0);

            $workEnd = clone $current;
            $workEnd->setTime($businessEndHour, 0, 0);

            $periodStart = max($current, $workStart);
            $periodEnd = min($end, $workEnd);

            if ($periodEnd > $periodStart) {
                $totalMinutes += ($periodEnd->getTimestamp() - $periodStart->getTimestamp()) / 60;
            }
        }

        $current->modify('tomorrow');
        $current->setTime(0, 0, 0);
    }

    return round($totalMinutes / 60, 2);
}

function formatDecimalHoursToClock($hours): string
{
    $totalSeconds = (int) round(((float)$hours) * 3600);

    $h = intdiv($totalSeconds, 3600);
    $m = intdiv($totalSeconds % 3600, 60);
    $s = $totalSeconds % 60;

    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}