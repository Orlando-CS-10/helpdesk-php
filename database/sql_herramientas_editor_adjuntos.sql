-- =========================================================
-- Módulo Herramientas v3: 8 herramientas + editor enriquecido y adjuntos
-- HelpDesk Pronet System
-- =========================================================

CREATE TABLE IF NOT EXISTS ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#ff7a00',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ticket_categories (code, name, description, color, is_active)
VALUES
('RED', 'Red', 'Incidencias relacionadas con conectividad, internet o red interna.', '#ff7a00', 1),
('ACCESO', 'Acceso', 'Problemas de credenciales, permisos, usuarios o bloqueo de cuenta.', '#ff7a00', 1),
('HARDWARE', 'Hardware', 'Fallas físicas en equipos, periféricos o componentes.', '#ff7a00', 1),
('SOFTWARE', 'Software', 'Errores en programas, instalación o configuración de aplicaciones.', '#ff7a00', 1),
('SISTEMA', 'Sistema', 'Problemas asociados a plataformas internas o servicios corporativos.', '#ff7a00', 1),
('OTROS', 'Otros', 'Incidencias que no pertenecen a una categoría específica.', '#ff7a00', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    color = VALUES(color),
    is_active = VALUES(is_active);

CREATE TABLE IF NOT EXISTS response_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category_id INT NULL,
    content TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_response_templates_category
        FOREIGN KEY (category_id) REFERENCES ticket_categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO response_templates (title, category_id, content, is_active)
SELECT 'Primera atención', NULL, 'Se tomó atención del caso. Se realizará la revisión técnica y se actualizará el ticket con los avances.', 1
WHERE NOT EXISTS (SELECT 1 FROM response_templates WHERE title = 'Primera atención');

INSERT INTO response_templates (title, category_id, content, is_active)
SELECT 'Validación de conectividad', id, 'Se iniciaron pruebas de conectividad para identificar el origen de la intermitencia reportada.', 1
FROM ticket_categories
WHERE code = 'RED'
  AND NOT EXISTS (SELECT 1 FROM response_templates WHERE title = 'Validación de conectividad');

INSERT INTO response_templates (title, category_id, content, is_active)
SELECT 'Acceso y credenciales', id, 'Se revisará el acceso del usuario y la configuración de permisos asociada a la plataforma.', 1
FROM ticket_categories
WHERE code = 'ACCESO'
  AND NOT EXISTS (SELECT 1 FROM response_templates WHERE title = 'Acceso y credenciales');

INSERT INTO response_templates (title, category_id, content, is_active)
SELECT 'Solución aplicada', NULL, 'Se aplicó la corrección correspondiente y el servicio quedó operativo. Se solicita validar el funcionamiento.', 1
WHERE NOT EXISTS (SELECT 1 FROM response_templates WHERE title = 'Solución aplicada');

CREATE TABLE IF NOT EXISTS ticket_priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    sla_hours DECIMAL(8,2) NOT NULL DEFAULT 8.00,
    color VARCHAR(20) NOT NULL DEFAULT '#ff7a00',
    sort_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ticket_priorities (code, name, sla_hours, color, sort_order, is_active)
VALUES
('ALTA', 'Alta', 4.00, '#ef4444', 1, 1),
('MEDIA', 'Media', 8.00, '#f59e0b', 2, 1),
('BAJA', 'Baja', 24.00, '#22c55e', 3, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sla_hours = VALUES(sla_hours),
    color = VALUES(color),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

CREATE TABLE IF NOT EXISTS closure_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    requires_comment TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO closure_reasons (code, name, description, requires_comment, is_active)
VALUES
('SOLUCIONADO', 'Solucionado', 'El incidente fue atendido y validado.', 0, 1),
('DUPLICADO', 'Duplicado', 'El caso ya fue registrado en otro ticket.', 1, 1),
('CLIENTE_NO_RESPONDE', 'Cliente no responde', 'No se obtuvo validación del usuario solicitante.', 1, 1),
('NO_PROCEDE', 'No procede', 'La solicitud no corresponde al alcance de soporte.', 1, 1),
('DERIVADO', 'Derivado externamente', 'El caso fue derivado a un proveedor o área externa.', 1, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    requires_comment = VALUES(requires_comment),
    is_active = VALUES(is_active);

CREATE TABLE IF NOT EXISTS knowledge_base_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    category_id INT NULL,
    problem_summary TEXT NOT NULL,
    solution_steps TEXT NOT NULL,
    content_html LONGTEXT NULL,
    keywords VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_knowledge_category
        FOREIGN KEY (category_id) REFERENCES ticket_categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agrega content_html cuando la tabla ya existía antes de esta actualización.
SET @knowledge_content_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'knowledge_base_articles'
      AND COLUMN_NAME = 'content_html'
);

SET @knowledge_content_sql = IF(
    @knowledge_content_exists = 0,
    'ALTER TABLE knowledge_base_articles ADD COLUMN content_html LONGTEXT NULL AFTER solution_steps',
    'SELECT 1'
);

PREPARE knowledge_content_stmt FROM @knowledge_content_sql;
EXECUTE knowledge_content_stmt;
DEALLOCATE PREPARE knowledge_content_stmt;

CREATE TABLE IF NOT EXISTS knowledge_base_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL UNIQUE,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_image TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kb_attachments_article (article_id),
    INDEX idx_kb_attachments_image (is_image),
    CONSTRAINT fk_kb_attachments_article
        FOREIGN KEY (article_id) REFERENCES knowledge_base_articles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_kb_attachments_user
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO knowledge_base_articles (title, category_id, problem_summary, solution_steps, content_html, keywords, is_active)
SELECT 'Usuario no puede iniciar sesión', id,
       'El usuario reporta error al ingresar a una plataforma o servicio corporativo.',
       'Validar usuario y correo.\nRevisar estado de la cuenta.\nRestablecer contraseña si corresponde.\nConfirmar acceso con el usuario.',
       '<p>Antes de comenzar, confirma el correo o usuario registrado.</p><ol><li>Validar el usuario y el correo.</li><li>Revisar el estado de la cuenta.</li><li>Restablecer la contraseña si corresponde.</li><li>Confirmar el acceso con el usuario.</li></ol>',
       'login, contraseña, acceso, credenciales', 1
FROM ticket_categories
WHERE code = 'ACCESO'
  AND NOT EXISTS (SELECT 1 FROM knowledge_base_articles WHERE title = 'Usuario no puede iniciar sesión');

INSERT INTO knowledge_base_articles (title, category_id, problem_summary, solution_steps, content_html, keywords, is_active)
SELECT 'Equipo sin conexión a internet', id,
       'El usuario indica que no tiene conexión o presenta intermitencia de red.',
       'Validar conexión física o WiFi.\nProbar conectividad local.\nRevisar IP/DNS.\nEscalar si afecta a varios usuarios.',
       '<p>Realiza las siguientes comprobaciones:</p><ul><li>Validar la conexión física o WiFi.</li><li>Probar la conectividad local.</li><li>Revisar la configuración de IP y DNS.</li><li>Escalar el caso si afecta a varios usuarios.</li></ul>',
       'internet, red, conectividad, dns, ip', 1
FROM ticket_categories
WHERE code = 'RED'
  AND NOT EXISTS (SELECT 1 FROM knowledge_base_articles WHERE title = 'Equipo sin conexión a internet');

CREATE TABLE IF NOT EXISTS assignment_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category_id INT NULL,
    priority_code VARCHAR(50) NULL,
    support_level TINYINT NOT NULL DEFAULT 1,
    technician_id INT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignment_rules_category
        FOREIGN KEY (category_id) REFERENCES ticket_categories(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_assignment_rules_technician
        FOREIGN KEY (technician_id) REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO assignment_rules (name, category_id, priority_code, support_level, technician_id, description, is_active)
SELECT 'Red con prioridad alta', c.id, 'ALTA', 2, NULL, 'Sugerir técnico de nivel 2 para incidencias de red con alta prioridad.', 1
FROM ticket_categories c
WHERE c.code = 'RED'
  AND NOT EXISTS (SELECT 1 FROM assignment_rules WHERE name = 'Red con prioridad alta');

INSERT INTO assignment_rules (name, category_id, priority_code, support_level, technician_id, description, is_active)
SELECT 'Accesos y credenciales', c.id, 'MEDIA', 1, NULL, 'Sugerir técnico de nivel 1 para casos de accesos, claves o permisos.', 1
FROM ticket_categories c
WHERE c.code = 'ACCESO'
  AND NOT EXISTS (SELECT 1 FROM assignment_rules WHERE name = 'Accesos y credenciales');

-- Opcional para una fase posterior:
-- 1) Si deseas usar categorías y prioridades dinámicas en tickets, convierte los ENUM a VARCHAR.
-- ALTER TABLE tickets MODIFY category VARCHAR(50) NOT NULL DEFAULT 'OTROS';
-- ALTER TABLE tickets MODIFY priority VARCHAR(50) NOT NULL DEFAULT 'MEDIA';
--
-- 2) Si deseas registrar el motivo real de cierre dentro del ticket:
-- ALTER TABLE tickets ADD closure_reason_id INT NULL;
-- ALTER TABLE tickets ADD closure_comment TEXT NULL;
-- ALTER TABLE tickets ADD CONSTRAINT fk_tickets_closure_reason FOREIGN KEY (closure_reason_id) REFERENCES closure_reasons(id) ON DELETE SET NULL;
