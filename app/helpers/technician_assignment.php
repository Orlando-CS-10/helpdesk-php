<?php

function getAvailableTechnicianByLevel(PDO $pdo, int $level = 1): ?array
{
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.tech_level,
            COUNT(t.id) AS active_tickets
        FROM users u
        LEFT JOIN tickets t 
            ON t.assigned_to = u.id
            AND t.status IN ('ABIERTO', 'EN_PROCESO', 'RESPONDIDO')
        WHERE u.role = 'TECH'
          AND u.status = 1
          AND u.tech_level = :level
        GROUP BY u.id, u.name, u.tech_level
        ORDER BY active_tickets ASC, u.id ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'level' => $level
    ]);

    $technician = $stmt->fetch(PDO::FETCH_ASSOC);

    return $technician ?: null;
}