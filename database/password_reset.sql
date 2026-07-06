-- =========================================================
-- RECUPERACIÓN DE CONTRASEÑA POR CORREO
-- Ejecutar sobre la base de datos helpdesk_db.
-- Compatible con MariaDB 10.4+ / MySQL 8+.
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = '-05:00';

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(120) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `invalidated_at` datetime DEFAULT NULL,
  `delivery_status` enum('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
  `sent_at` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_email` (`email`),
  KEY `idx_password_reset_validity` (`expires_at`, `used_at`, `invalidated_at`),
  KEY `idx_password_reset_created` (`created_at`),
  CONSTRAINT `fk_password_reset_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
