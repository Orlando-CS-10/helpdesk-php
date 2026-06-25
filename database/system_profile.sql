-- =========================================================
-- PERFIL DEL SISTEMA
-- Ejecutar una sola vez en la base de datos helpdesk_db.
-- Compatible con MariaDB 10.4+ y MySQL 8+.
-- =========================================================

USE helpdesk_db;

CREATE TABLE IF NOT EXISTS system_profile (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    company_name VARCHAR(180) NOT NULL,
    commercial_name VARCHAR(150) DEFAULT NULL,
    system_name VARCHAR(120) NOT NULL,
    ruc VARCHAR(11) DEFAULT NULL,
    corporate_email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(25) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    description TEXT NULL,
    slogan VARCHAR(180) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    updated_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_system_profile_updated_by (updated_by),
    CONSTRAINT fk_system_profile_updated_by
        FOREIGN KEY (updated_by) REFERENCES users (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_profile (
    id,
    company_name,
    commercial_name,
    system_name,
    logo_path
) VALUES (
    1,
    'PRONET SYSTEM S.A.C.',
    'Pronet System',
    'Mesa de Ayuda',
    'public/assets/img/logo.png'
)
ON DUPLICATE KEY UPDATE id = VALUES(id);
