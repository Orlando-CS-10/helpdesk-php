SET NAMES utf8mb4;
SET time_zone = '-05:00';

CREATE TABLE IF NOT EXISTS `sla_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `schedule_type` enum('24_7','BUSINESS') NOT NULL DEFAULT 'BUSINESS',
  `timezone_name` varchar(64) NOT NULL DEFAULT 'America/Lima',
  `work_start` time NOT NULL DEFAULT '08:00:00',
  `work_end` time NOT NULL DEFAULT '17:00:00',
  `work_days` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `warning_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 75,
  `critical_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT 90,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sla_profiles_name` (`name`),
  KEY `idx_sla_profiles_default_active` (`is_default`,`is_active`),
  KEY `fk_sla_profiles_updated_by` (`updated_by`),
  CONSTRAINT `fk_sla_profiles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sla_priority_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL,
  `priority_code` enum('BAJA','MEDIA','ALTA') NOT NULL,
  `tta_minutes` int(10) UNSIGNED NOT NULL DEFAULT 60,
  `ttr_minutes` int(10) UNSIGNED NOT NULL DEFAULT 480,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sla_priority_target` (`profile_id`,`priority_code`),
  CONSTRAINT `fk_sla_priority_target_profile` FOREIGN KEY (`profile_id`) REFERENCES `sla_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sla_pause_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL,
  `status_code` enum('ABIERTO','EN_PROCESO','RESPONDIDO','CERRADO') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sla_pause_status` (`profile_id`,`status_code`),
  CONSTRAINT `fk_sla_pause_status_profile` FOREIGN KEY (`profile_id`) REFERENCES `sla_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sla_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `action_type` varchar(80) NOT NULL,
  `description` varchar(500) NOT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sla_audit_created` (`created_at`),
  KEY `fk_sla_audit_profile` (`profile_id`),
  KEY `fk_sla_audit_actor` (`actor_user_id`),
  CONSTRAINT `fk_sla_audit_profile` FOREIGN KEY (`profile_id`) REFERENCES `sla_profiles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sla_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `client_companies`
  ADD COLUMN IF NOT EXISTS `sla_profile_id` int(11) DEFAULT NULL AFTER `sla_contract_type`;


ALTER TABLE `tickets`
  ADD COLUMN IF NOT EXISTS `sla_profile_id` int(11) DEFAULT NULL AFTER `sla_hours`,
  ADD COLUMN IF NOT EXISTS `sla_profile_name` varchar(120) DEFAULT NULL AFTER `sla_profile_id`,
  ADD COLUMN IF NOT EXISTS `sla_schedule_type` varchar(20) DEFAULT NULL AFTER `sla_profile_name`,
  ADD COLUMN IF NOT EXISTS `sla_work_start` time DEFAULT NULL AFTER `sla_schedule_type`,
  ADD COLUMN IF NOT EXISTS `sla_work_end` time DEFAULT NULL AFTER `sla_work_start`,
  ADD COLUMN IF NOT EXISTS `sla_work_days` varchar(20) DEFAULT NULL AFTER `sla_work_end`,
  ADD COLUMN IF NOT EXISTS `sla_warning_percent` tinyint(3) UNSIGNED DEFAULT NULL AFTER `sla_work_days`,
  ADD COLUMN IF NOT EXISTS `sla_critical_percent` tinyint(3) UNSIGNED DEFAULT NULL AFTER `sla_warning_percent`,
  ADD COLUMN IF NOT EXISTS `sla_tta_minutes` int(10) UNSIGNED DEFAULT NULL AFTER `sla_critical_percent`,
  ADD COLUMN IF NOT EXISTS `sla_ttr_minutes` int(10) UNSIGNED DEFAULT NULL AFTER `sla_tta_minutes`,
  ADD COLUMN IF NOT EXISTS `sla_tta_due_at` datetime DEFAULT NULL AFTER `sla_ttr_minutes`,
  ADD COLUMN IF NOT EXISTS `sla_ttr_due_at` datetime DEFAULT NULL AFTER `sla_tta_due_at`,
  ADD COLUMN IF NOT EXISTS `sla_tta_met` tinyint(1) DEFAULT NULL AFTER `sla_ttr_due_at`,
  ADD COLUMN IF NOT EXISTS `sla_ttr_met` tinyint(1) DEFAULT NULL AFTER `sla_tta_met`,
  ADD COLUMN IF NOT EXISTS `sla_paused_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `sla_ttr_met`,
  ADD COLUMN IF NOT EXISTS `sla_pause_started_at` datetime DEFAULT NULL AFTER `sla_paused_minutes`;

INSERT INTO `sla_profiles`
(`name`,`description`,`schedule_type`,`timezone_name`,`work_start`,`work_end`,`work_days`,`warning_percent`,`critical_percent`,`is_default`,`is_active`)
SELECT 'SLA Estándar 8/5','Atención de lunes a viernes dentro del horario laboral.','BUSINESS','America/Lima','08:00:00','17:00:00','1,2,3,4,5',75,90,1,1
WHERE NOT EXISTS (SELECT 1 FROM `sla_profiles` WHERE `name` = 'SLA Estándar 8/5');

INSERT INTO `sla_profiles`
(`name`,`description`,`schedule_type`,`timezone_name`,`work_start`,`work_end`,`work_days`,`warning_percent`,`critical_percent`,`is_default`,`is_active`)
SELECT 'SLA Continuo 24/7','Atención continua durante todos los días y horas.','24_7','America/Lima','00:00:00','23:59:59','1,2,3,4,5,6,7',75,90,0,1
WHERE NOT EXISTS (SELECT 1 FROM `sla_profiles` WHERE `name` = 'SLA Continuo 24/7');

UPDATE `sla_profiles`
SET `is_default` = CASE WHEN `name` = 'SLA Estándar 8/5' THEN 1 ELSE 0 END
WHERE `is_default` = 1 OR `name` = 'SLA Estándar 8/5';

INSERT INTO `sla_priority_targets` (`profile_id`,`priority_code`,`tta_minutes`,`ttr_minutes`)
SELECT p.id, x.priority_code, x.tta_minutes, x.ttr_minutes
FROM `sla_profiles` p
CROSS JOIN (
  SELECT 'ALTA' AS priority_code, 30 AS tta_minutes, 480 AS ttr_minutes
  UNION ALL SELECT 'MEDIA', 120, 1440
  UNION ALL SELECT 'BAJA', 240, 2880
) x
WHERE p.name IN ('SLA Estándar 8/5','SLA Continuo 24/7')
ON DUPLICATE KEY UPDATE
  tta_minutes = VALUES(tta_minutes),
  ttr_minutes = VALUES(ttr_minutes);

INSERT INTO `sla_pause_statuses` (`profile_id`,`status_code`)
SELECT p.id, 'RESPONDIDO'
FROM `sla_profiles` p
WHERE p.name IN ('SLA Estándar 8/5','SLA Continuo 24/7')
ON DUPLICATE KEY UPDATE status_code = VALUES(status_code);

UPDATE `client_companies` cc
SET cc.sla_profile_id = (
  SELECT p.id
  FROM `sla_profiles` p
  WHERE p.name = CASE
    WHEN cc.sla_contract_type = '24_7' THEN 'SLA Continuo 24/7'
    ELSE 'SLA Estándar 8/5'
  END
  LIMIT 1
)
WHERE cc.sla_profile_id IS NULL;
