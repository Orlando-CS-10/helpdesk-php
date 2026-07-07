CREATE TABLE IF NOT EXISTS `login_two_factor_codes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `invalidated_at` DATETIME NULL DEFAULT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `delivery_status` ENUM('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
  `sent_at` DATETIME NULL DEFAULT NULL,
  `last_error` VARCHAR(500) NULL DEFAULT NULL,
  `request_ip` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_two_factor_user_status` (`user_id`, `used_at`, `invalidated_at`),
  KEY `idx_two_factor_expiration` (`expires_at`),
  CONSTRAINT `fk_two_factor_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
