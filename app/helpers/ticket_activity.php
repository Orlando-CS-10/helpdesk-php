<?php

function createTicketActivity(
    PDO $pdo,
    int $ticketId,
    ?int $userId,
    ?string $actorName,
    ?string $actorRole,
    string $activityType,
    string $description,
    ?string $oldValue = null,
    ?string $newValue = null
): void {
    $sql = "INSERT INTO ticket_activity (
                ticket_id,
                user_id,
                actor_name,
                actor_role,
                activity_type,
                description,
                old_value,
                new_value
            ) VALUES (
                :ticket_id,
                :user_id,
                :actor_name,
                :actor_role,
                :activity_type,
                :description,
                :old_value,
                :new_value
            )";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);

    if ($userId === null) {
        $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }

    $stmt->bindValue(':actor_name', $actorName);
    $stmt->bindValue(':actor_role', $actorRole);
    $stmt->bindValue(':activity_type', $activityType);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':old_value', $oldValue);
    $stmt->bindValue(':new_value', $newValue);

    $stmt->execute();
}