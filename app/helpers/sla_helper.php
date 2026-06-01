<?php

// Todos los cálculos SLA se hacen con hora de Perú.
if (date_default_timezone_get() !== 'America/Lima') {
    date_default_timezone_set('America/Lima');
}

require_once __DIR__ . '/business_hours.php';

function getSlaStatusLabel(array $ticket): string
{
    if (($ticket['status'] ?? '') === 'CERRADO') {
        if (isset($ticket['sla_met']) && (int)$ticket['sla_met'] === 1) {
            return 'Cumplido';
        }

        return 'No cumplido';
    }

    if (empty($ticket['created_at']) || empty($ticket['sla_hours'])) {
        return 'Pendiente';
    }

    try {
        $createdAt = $ticket['created_at'];
        $now = (new DateTime('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');

        /*
        |--------------------------------------------------------------------------
        | SLA en horas laborales
        |--------------------------------------------------------------------------
        | Usa el horario definido en business_hours.php:
        | lunes a sábado, 08:00 am a 05:50 pm.
        */
        $elapsedHours = calculateBusinessHours($createdAt, $now);
        $slaHours = (float)$ticket['sla_hours'];

        if ($elapsedHours >= $slaHours) {
            return 'Vencido';
        }

        if ($elapsedHours >= ($slaHours * 0.75)) {
            return 'Por vencer';
        }

        return 'Dentro del SLA';

    } catch (Exception $e) {
        return 'Pendiente';
    }
}

function getSlaStatusClass(array $ticket): string
{
    $label = getSlaStatusLabel($ticket);

    return match ($label) {
        'Cumplido', 'Dentro del SLA' => 'success-pill',
        'Por vencer' => 'pending-pill',
        'Vencido', 'No cumplido' => 'danger-pill',
        default => 'neutral-pill',
    };
}