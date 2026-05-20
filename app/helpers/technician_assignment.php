<?php

function getAvailableTechnicianByLevel(PDO $pdo, int $level = 1): ?array
{
    $sql = "
        SELECT 
            u.id,
            u.name,
            COALESCE(u.tech_level, 1) AS tech_level,
            COUNT(t.id) AS active_tickets,
            COALESCE(SUM(
                CASE 
                    WHEN t.priority = 'ALTA' THEN 3
                    WHEN t.priority = 'MEDIA' THEN 2
                    WHEN t.priority = 'BAJA' THEN 1
                    ELSE 1
                END
            ), 0) AS workload_score
        FROM users u
        LEFT JOIN tickets t 
            ON t.assigned_to = u.id
            AND t.status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO')
        WHERE u.role = 'TECH'
          AND u.status = 1
          AND COALESCE(u.tech_level, 1) = :level
        GROUP BY u.id, u.name, u.tech_level
        ORDER BY workload_score ASC, active_tickets ASC, u.id ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'level' => $level
    ]);

    $technician = $stmt->fetch(PDO::FETCH_ASSOC);

    return $technician ?: null;
}

function getSmartTechnicianAssignment(PDO $pdo, int $preferredLevel = 1): ?array
{
    /*
     * Regla principal:
     * El ticket debe iniciar en Nivel 1.
     * Si no hay técnicos disponibles en Nivel 1,
     * se busca Nivel 2 y luego Nivel 3.
     */

    $levelsToCheck = [$preferredLevel, 2, 3];
    $levelsToCheck = array_values(array_unique($levelsToCheck));

    foreach ($levelsToCheck as $level) {
        $technician = getAvailableTechnicianByLevel($pdo, (int)$level);

        if ($technician) {
            return $technician;
        }
    }

    return null;
}