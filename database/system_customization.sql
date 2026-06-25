-- =========================================================
-- PERSONALIZACIÓN DEL SISTEMA
-- Base de datos: helpdesk_db
-- Registro único: id = 1
-- =========================================================

USE helpdesk_db;

CREATE TABLE IF NOT EXISTS system_customization (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    primary_color CHAR(7) NOT NULL DEFAULT '#0f3d2e',
    secondary_color CHAR(7) NOT NULL DEFAULT '#ff7a00',
    accent_color CHAR(7) NOT NULL DEFAULT '#1f7a5a',
    theme ENUM('light', 'dark', 'auto') NOT NULL DEFAULT 'light',
    sidebar_default ENUM('expanded', 'collapsed') NOT NULL DEFAULT 'expanded',
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_system_customization_updated_by (updated_by),
    CONSTRAINT fk_system_customization_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_customization (
    id,
    primary_color,
    secondary_color,
    accent_color,
    theme,
    sidebar_default
) VALUES (
    1,
    '#0f3d2e',
    '#ff7a00',
    '#1f7a5a',
    'light',
    'expanded'
)
ON DUPLICATE KEY UPDATE id = VALUES(id);
