<?php

function getSlaStatusLabel($ticket): string
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

    $createdAt = new DateTime($ticket['created_at']);
    $now = new DateTime();

    $elapsedHours = ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600;
    $slaHours = (float)$ticket['sla_hours'];

    if ($elapsedHours >= $slaHours) {
        return 'Vencido';
    }

    if ($elapsedHours >= ($slaHours * 0.75)) {
        return 'Por vencer';
    }

    return 'Dentro del SLA';
}

function getSlaStatusClass($ticket): string
{
    $label = getSlaStatusLabel($ticket);

    return match ($label) {
        'Cumplido', 'Dentro del SLA' => 'success-pill',
        'Por vencer' => 'pending-pill',
        'Vencido', 'No cumplido' => 'danger-pill',
        default => 'neutral-pill',
    };
}