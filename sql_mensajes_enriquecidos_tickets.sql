-- ============================================================
-- MENSAJES ENRIQUECIDOS Y ARCHIVOS ADJUNTOS
-- Ejecutar una sola vez en la base de datos del helpdesk.
-- ============================================================

ALTER TABLE ticket_messages
    ADD COLUMN IF NOT EXISTS message_format VARCHAR(10) NOT NULL DEFAULT 'plain' AFTER message,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL AFTER created_at;

ALTER TABLE ticket_internal_messages
    ADD COLUMN IF NOT EXISTS message_format VARCHAR(10) NOT NULL DEFAULT 'plain' AFTER message;

CREATE TABLE IF NOT EXISTS ticket_message_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    message_scope ENUM('PUBLIC', 'INTERNAL') NOT NULL DEFAULT 'PUBLIC',
    message_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    storage_path VARCHAR(500) NOT NULL,
    is_inline TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket_message_attachment_ticket (ticket_id),
    KEY idx_ticket_message_attachment_message (message_scope, message_id),
    KEY idx_ticket_message_attachment_user (uploaded_by),
    CONSTRAINT fk_ticket_message_attachment_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ticket_message_attachment_user
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los mensajes existentes siguen siendo texto plano.
UPDATE ticket_messages
SET message_format = 'plain'
WHERE message_format IS NULL OR message_format = '';

UPDATE ticket_internal_messages
SET message_format = 'plain'
WHERE message_format IS NULL OR message_format = '';
