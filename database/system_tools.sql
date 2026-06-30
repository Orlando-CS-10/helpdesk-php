-- =========================================================
-- HERRAMIENTAS DEL SISTEMA
-- Diagnóstico, respaldos, limpieza y modo mantenimiento
-- =========================================================

CREATE TABLE IF NOT EXISTS `system_maintenance_settings` (
  `id` tinyint unsigned NOT NULL DEFAULT 1,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `message` varchar(500) NOT NULL DEFAULT 'El sistema se encuentra temporalmente en mantenimiento.',
  `estimated_return_at` datetime DEFAULT NULL,
  `allow_admin` tinyint(1) NOT NULL DEFAULT 1,
  `block_tech` tinyint(1) NOT NULL DEFAULT 1,
  `block_client` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_system_maintenance_updated_by` (`updated_by`),
  CONSTRAINT `fk_system_maintenance_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_maintenance_settings` (
  `id`, `is_enabled`, `message`, `estimated_return_at`,
  `allow_admin`, `block_tech`, `block_client`, `updated_by`
) VALUES (
  1, 0, 'El sistema se encuentra temporalmente en mantenimiento.', NULL,
  1, 1, 1, NULL
)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

CREATE TABLE IF NOT EXISTS `system_backup_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `backup_type` enum('DATABASE','FILES','FULL') NOT NULL DEFAULT 'DATABASE',
  `file_name` varchar(255) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `file_size_bytes` bigint unsigned NOT NULL DEFAULT 0,
  `status` enum('COMPLETED','FAILED') NOT NULL DEFAULT 'COMPLETED',
  `notes` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_backup_storage_path` (`storage_path`),
  KEY `idx_system_backup_created_at` (`created_at`),
  KEY `idx_system_backup_created_by` (`created_by`),
  CONSTRAINT `fk_system_backup_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_maintenance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action_type` varchar(80) NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `description` varchar(500) NOT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_system_maintenance_action` (`action_type`),
  KEY `idx_system_maintenance_created_at` (`created_at`),
  KEY `idx_system_maintenance_actor` (`actor_user_id`),
  CONSTRAINT `fk_system_maintenance_actor`
    FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_technical_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `level` enum('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `module` varchar(100) NOT NULL,
  `message` varchar(500) NOT NULL,
  `context_json` longtext DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_system_technical_level` (`level`),
  KEY `idx_system_technical_module` (`module`),
  KEY `idx_system_technical_created_at` (`created_at`),
  CONSTRAINT `fk_system_technical_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
