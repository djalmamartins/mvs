CREATE TABLE IF NOT EXISTS talk_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body VARCHAR(500) NULL,
    payload JSON NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY talk_notifications_recipient (recipient_id, read_at, created_at),
    KEY talk_notifications_ticket (ticket_id, created_at),
    CONSTRAINT talk_notifications_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT talk_notifications_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
