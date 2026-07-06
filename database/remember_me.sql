-- =========================================================
-- RECORDAR SESIÓN: USUARIOS Y PORTAL CORPORATIVO
-- Ejecutar una sola vez sobre helpdesk_db.
-- Es seguro volver a ejecutarlo porque usa IF NOT EXISTS.
-- =========================================================

USE `helpdesk_db`;

CREATE TABLE IF NOT EXISTS `user_remember_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `selector` char(48) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `credential_fingerprint` char(64) NOT NULL,
  `user_agent_hash` char(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoke_reason` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_remember_selector` (`selector`),
  KEY `idx_user_remember_user` (`user_id`),
  KEY `idx_user_remember_expiry` (`expires_at`),
  CONSTRAINT `fk_user_remember_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_portal_remember_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `selector` char(48) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `credential_fingerprint` char(64) NOT NULL,
  `user_agent_hash` char(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoke_reason` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_remember_selector` (`selector`),
  KEY `idx_company_remember_account` (`account_id`),
  KEY `idx_company_remember_expiry` (`expires_at`),
  CONSTRAINT `fk_company_remember_account`
    FOREIGN KEY (`account_id`) REFERENCES `company_portal_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
