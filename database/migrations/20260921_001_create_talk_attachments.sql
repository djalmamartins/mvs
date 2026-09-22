-- Moves Talk attachments
CREATE TABLE IF NOT EXISTS talk_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    storage_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY talk_attachments_ticket (ticket_id, created_at, id),
    KEY talk_attachments_message (message_id),
    CONSTRAINT talk_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE,
    CONSTRAINT talk_attachments_message FOREIGN KEY (message_id) REFERENCES talk_messages(id) ON DELETE SET NULL,
    CONSTRAINT talk_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
