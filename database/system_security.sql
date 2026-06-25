-- =========================================================
-- MÓDULO: SEGURIDAD DEL SISTEMA
-- Compatible con MariaDB 10.4+ / MySQL 8+
-- Ejecutar sobre la base de datos helpdesk_db.
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = '-05:00';

CREATE TABLE IF NOT EXISTS `system_security_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `min_password_length` tinyint(3) UNSIGNED NOT NULL DEFAULT 8,
  `require_uppercase` tinyint(1) NOT NULL DEFAULT 1,
  `require_lowercase` tinyint(1) NOT NULL DEFAULT 1,
  `require_number` tinyint(1) NOT NULL DEFAULT 1,
  `require_special` tinyint(1) NOT NULL DEFAULT 1,
  `block_common_passwords` tinyint(1) NOT NULL DEFAULT 1,
  `force_change_on_create` tinyint(1) NOT NULL DEFAULT 1,
  `password_expiry_days` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `max_failed_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `lockout_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 15,
  `failed_attempt_reset_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `session_idle_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `session_max_hours` smallint(5) UNSIGNED NOT NULL DEFAULT 12,
  `single_session` tinyint(1) NOT NULL DEFAULT 0,
  `invalidate_sessions_on_password_change` tinyint(1) NOT NULL DEFAULT 1,
  `block_inactive_users` tinyint(1) NOT NULL DEFAULT 1,
  `audit_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_security_settings_updated_by` (`updated_by`),
  CONSTRAINT `fk_security_settings_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_security_settings` (
  `id`, `min_password_length`, `require_uppercase`, `require_lowercase`,
  `require_number`, `require_special`, `block_common_passwords`,
  `force_change_on_create`, `password_expiry_days`, `max_failed_attempts`,
  `lockout_minutes`, `failed_attempt_reset_minutes`, `session_idle_minutes`,
  `session_max_hours`, `single_session`,
  `invalidate_sessions_on_password_change`, `block_inactive_users`, `audit_enabled`
) VALUES (
  1, 8, 1, 1, 1, 1, 1, 1, 0, 5, 15, 30, 30, 12, 0, 1, 1, 1
)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

CREATE TABLE IF NOT EXISTS `security_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `description` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_security_log_user` (`user_id`),
  KEY `idx_security_log_actor` (`actor_user_id`),
  KEY `idx_security_log_event` (`event_type`),
  KEY `idx_security_log_created` (`created_at`),
  CONSTRAINT `fk_security_log_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_security_log_actor`
    FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` char(64) NOT NULL,
  `php_session_hash` char(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `device_label` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoke_reason` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_sessions_token` (`session_token`),
  KEY `idx_user_sessions_user` (`user_id`),
  KEY `idx_user_sessions_active` (`revoked_at`, `expires_at`),
  KEY `idx_user_sessions_activity` (`last_activity_at`),
  CONSTRAINT `fk_user_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `failed_login_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0 AFTER `tech_level`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `failed_login_at` datetime DEFAULT NULL AFTER `failed_login_attempts`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `locked_until` datetime DEFAULT NULL AFTER `failed_login_at`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `force_password_change` tinyint(1) NOT NULL DEFAULT 0 AFTER `locked_until`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `password_changed_at` datetime DEFAULT NULL AFTER `force_password_change`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `last_login_at` datetime DEFAULT NULL AFTER `password_changed_at`;

-- Las cuentas existentes conservan como referencia su fecha de creación.
UPDATE `users`
SET `password_changed_at` = COALESCE(`password_changed_at`, `created_at`)
WHERE `password_changed_at` IS NULL;
