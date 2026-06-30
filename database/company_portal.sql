-- =========================================================
-- PORTAL CORPORATIVO DE EMPRESAS
-- HelpDesk Pronet System
-- Ejecutar una sola vez sobre la base de datos helpdesk_db.
-- El script es seguro para reejecución: usa IF NOT EXISTS e INSERT condicional.
-- =========================================================

USE `helpdesk_db`;

CREATE TABLE IF NOT EXISTS `company_portal_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `failed_login_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `failed_login_at` datetime DEFAULT NULL,
  `locked_until` datetime DEFAULT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 1,
  `password_changed_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_portal_accounts_email` (`email`),
  KEY `idx_company_portal_accounts_company` (`company_id`),
  KEY `idx_company_portal_accounts_status` (`status`),
  CONSTRAINT `fk_company_portal_accounts_company`
    FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_company_portal_accounts_creator`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_portal_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id` bigint(20) UNSIGNED NOT NULL,
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
  UNIQUE KEY `uq_company_portal_sessions_token` (`session_token`),
  KEY `idx_company_portal_sessions_account` (`account_id`),
  KEY `idx_company_portal_sessions_active` (`revoked_at`, `expires_at`),
  CONSTRAINT `fk_company_portal_sessions_account`
    FOREIGN KEY (`account_id`) REFERENCES `company_portal_accounts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_portal_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(80) NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `description` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_portal_audit_company_date` (`company_id`, `created_at`),
  KEY `idx_company_portal_audit_account_date` (`account_id`, `created_at`),
  KEY `idx_company_portal_audit_event` (`event_type`),
  CONSTRAINT `fk_company_portal_audit_company`
    FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_company_portal_audit_account`
    FOREIGN KEY (`account_id`) REFERENCES `company_portal_accounts` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permisos que la cuenta corporativa podrá administrar para los contactos.
CREATE TABLE IF NOT EXISTS `company_contact_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `can_create_tickets` tinyint(1) NOT NULL DEFAULT 1,
  `can_view_company_tickets` tinyint(1) NOT NULL DEFAULT 0,
  `can_reply_tickets` tinyint(1) NOT NULL DEFAULT 1,
  `can_download_attachments` tinyint(1) NOT NULL DEFAULT 1,
  `can_view_reports` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_contacts` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_contact_permissions_user` (`user_id`),
  KEY `idx_company_contact_permissions_company` (`company_id`),
  CONSTRAINT `fk_company_contact_permissions_company`
    FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_company_contact_permissions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_company_contact_permissions_updater`
    FOREIGN KEY (`updated_by_account_id`) REFERENCES `company_portal_accounts` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Normaliza contactos antiguos que tenían el nombre de empresa, pero no company_id.
UPDATE `users` AS u
INNER JOIN `client_companies` AS c
  ON LOWER(REPLACE(TRIM(COALESCE(u.`company`, '')), ' ', '')) IN (
       LOWER(REPLACE(TRIM(COALESCE(c.`trade_name`, '')), ' ', '')),
       LOWER(REPLACE(TRIM(COALESCE(c.`business_name`, '')), ' ', ''))
     )
SET u.`company_id` = c.`id`
WHERE u.`role` = 'CLIENT'
  AND u.`company_id` IS NULL
  AND TRIM(COALESCE(u.`company`, '')) <> '';

-- Completa la empresa de tickets antiguos a partir de su solicitante.
UPDATE `tickets` AS t
INNER JOIN `users` AS u ON u.`id` = t.`requester_id`
SET t.`company_id` = u.`company_id`
WHERE t.`company_id` IS NULL
  AND u.`company_id` IS NOT NULL;

-- Crea la configuración inicial de permisos para contactos existentes.
INSERT INTO `company_contact_permissions`
  (`company_id`, `user_id`, `can_create_tickets`, `can_view_company_tickets`,
   `can_reply_tickets`, `can_download_attachments`, `can_view_reports`,
   `can_manage_contacts`, `created_at`)
SELECT
  u.`company_id`,
  u.`id`,
  1,
  COALESCE(u.`can_view_company_tickets`, 0),
  1,
  1,
  COALESCE(u.`can_view_company_tickets`, 0),
  0,
  NOW()
FROM `users` AS u
WHERE u.`role` = 'CLIENT'
  AND u.`company_id` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `company_contact_permissions` AS cp
    WHERE cp.`user_id` = u.`id`
  );

-- Cuenta inicial de demostración para Parque Arauco.
-- Correo: portal.parquearauco@demo.com
-- Contraseña temporal: Parque@2026
-- El sistema obligará a cambiarla en el primer ingreso.
INSERT INTO `company_portal_accounts`
  (`company_id`, `name`, `email`, `password_hash`, `is_primary`, `status`,
   `force_password_change`, `created_at`)
SELECT
  c.`id`,
  'Administrador corporativo',
  'portal.parquearauco@demo.com',
  '$2y$12$ll.0GVFGiiqBlg2IZmslousw2HwGNxjB5qTUr7wF9CuNbqJ7pc0/e',
  1,
  1,
  1,
  NOW()
FROM `client_companies` AS c
WHERE (
    LOWER(TRIM(COALESCE(c.`trade_name`, ''))) = 'parque arauco'
    OR LOWER(REPLACE(TRIM(c.`business_name`), ' ', '')) = 'parquearauco'
  )
  AND NOT EXISTS (
    SELECT 1
    FROM `company_portal_accounts` AS a
    WHERE a.`email` = 'portal.parquearauco@demo.com'
  )
LIMIT 1;
