-- =========================================================
-- LOGO PARA EMPRESAS CLIENTE
-- Base de datos: helpdesk_db
-- =========================================================

SET @logo_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'client_companies'
      AND COLUMN_NAME = 'logo_path'
);

SET @logo_column_sql := IF(
    @logo_column_exists = 0,
    'ALTER TABLE client_companies ADD COLUMN logo_path VARCHAR(255) NULL AFTER email',
    'SELECT ''La columna logo_path ya existe'' AS mensaje'
);

PREPARE logo_column_statement FROM @logo_column_sql;
EXECUTE logo_column_statement;
DEALLOCATE PREPARE logo_column_statement;
