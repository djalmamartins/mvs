ALTER TABLE talk_tickets
    ADD COLUMN first_response_at DATETIME NULL AFTER assigned_at,
    ADD COLUMN sla_due_at DATETIME NULL AFTER first_response_at,
    ADD COLUMN last_activity_at DATETIME NULL AFTER sla_due_at,
    ADD KEY talk_tickets_sla (status, sla_due_at),
    ADD KEY talk_tickets_activity (last_activity_at);

CREATE TABLE IF NOT EXISTS talk_user_settings (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    talk_role VARCHAR(20) NOT NULL DEFAULT 'agent',
    max_active_tickets SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sound_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT talk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    storage_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY talk_attachments_message (message_id),
    CONSTRAINT talk_attachments_message FOREIGN KEY (message_id) REFERENCES talk_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO talk_settings(setting_key,setting_value) VALUES
('sla.first_response_minutes','15'),
('sla.resolve_minutes','240'),
('presence.offline_after_minutes','5');
