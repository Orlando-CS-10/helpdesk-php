-- ============================================================
-- Cierre estructurado de tickets
-- Motivos: Herramientas > Motivos de cierre
-- ============================================================

CREATE TABLE IF NOT EXISTS `ticket_closures` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` int(11) NOT NULL,
    `closure_reason_id` int(11) DEFAULT NULL,
    `reason_code` varchar(50) NOT NULL,
    `reason_name` varchar(120) NOT NULL,
    `comment` text DEFAULT NULL,
    `closed_by` int(11) DEFAULT NULL,
    `closed_by_name` varchar(120) NOT NULL,
    `closed_by_role` varchar(50) NOT NULL,
    `closed_at` datetime NOT NULL,
    `sla_met` tinyint(1) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_ticket_closures_ticket` (`ticket_id`, `closed_at`),
    KEY `idx_ticket_closures_reason` (`closure_reason_id`),
    KEY `idx_ticket_closures_user` (`closed_by`),
    CONSTRAINT `fk_ticket_closures_ticket`
        FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ticket_closures_reason`
        FOREIGN KEY (`closure_reason_id`) REFERENCES `closure_reasons` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ticket_closures_user`
        FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
