ALTER TABLE proposals
    ADD COLUMN assigned_to BIGINT UNSIGNED NULL AFTER status,
    ADD COLUMN response TEXT NULL AFTER assigned_to,
    ADD COLUMN responded_at DATETIME NULL AFTER response,
    ADD COLUMN converted_at DATETIME NULL AFTER responded_at,
    ADD CONSTRAINT proposals_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE proposal_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY proposal_history_listing (proposal_id, created_at),
    CONSTRAINT proposal_history_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    CONSTRAINT proposal_history_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE notifications
    ADD COLUMN recipient_id BIGINT UNSIGNED NULL AFTER message,
    ADD COLUMN source_type VARCHAR(40) NULL AFTER recipient_id,
    ADD COLUMN source_id BIGINT UNSIGNED NULL AFTER source_type,
    ADD COLUMN action_url VARCHAR(500) NULL AFTER source_id,
    ADD CONSTRAINT notifications_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE;
