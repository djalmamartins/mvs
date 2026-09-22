-- Moves Talk Foundation v0.1
-- Domain tables are channel-agnostic; external transports are isolated by channel/external_id.

CREATE TABLE IF NOT EXISTS talk_departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_queues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    auto_assign_after_seconds INT UNSIGNED NOT NULL DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT talk_queues_department FOREIGN KEY (department_id) REFERENCES talk_departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_queue_members (
    queue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'agent',
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY (queue_id, user_id),
    CONSTRAINT talk_queue_members_queue FOREIGN KEY (queue_id) REFERENCES talk_queues(id) ON DELETE CASCADE,
    CONSTRAINT talk_queue_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    external_id VARCHAR(190) NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'simulation',
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY talk_contacts_channel_external (channel, external_id),
    KEY talk_contacts_phone (phone)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'simulation',
    external_id VARCHAR(190) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    last_message_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY talk_conversations_channel_external (channel, external_id),
    KEY talk_conversations_contact (contact_id, status),
    CONSTRAINT talk_conversations_contact FOREIGN KEY (contact_id) REFERENCES talk_contacts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    protocol VARCHAR(40) NOT NULL UNIQUE,
    conversation_id BIGINT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'queued',
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    subject VARCHAR(190) NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'simulation',
    queued_at DATETIME NULL,
    assigned_at DATETIME NULL,
    closed_at DATETIME NULL,
    closed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY talk_tickets_queue_status (queue_id, status, priority, created_at),
    KEY talk_tickets_assignee_status (assigned_user_id, status),
    CONSTRAINT talk_tickets_conversation FOREIGN KEY (conversation_id) REFERENCES talk_conversations(id) ON DELETE RESTRICT,
    CONSTRAINT talk_tickets_queue FOREIGN KEY (queue_id) REFERENCES talk_queues(id) ON DELETE SET NULL,
    CONSTRAINT talk_tickets_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT talk_tickets_closed_by FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NULL,
    sender_type VARCHAR(20) NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    external_id VARCHAR(190) NULL,
    direction VARCHAR(20) NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'text',
    body TEXT NULL,
    media_url VARCHAR(500) NULL,
    metadata JSON NULL,
    sent_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY talk_messages_conversation (conversation_id, sent_at, id),
    KEY talk_messages_ticket (ticket_id, sent_at, id),
    CONSTRAINT talk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES talk_conversations(id) ON DELETE CASCADE,
    CONSTRAINT talk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE SET NULL,
    CONSTRAINT talk_messages_sender_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    from_user_id BIGINT UNSIGNED NULL,
    from_queue_id BIGINT UNSIGNED NULL,
    to_user_id BIGINT UNSIGNED NULL,
    to_queue_id BIGINT UNSIGNED NULL,
    reason VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT talk_transfers_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE,
    CONSTRAINT talk_transfers_from_user FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT talk_transfers_to_user FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT talk_transfers_from_queue FOREIGN KEY (from_queue_id) REFERENCES talk_queues(id) ON DELETE SET NULL,
    CONSTRAINT talk_transfers_to_queue FOREIGN KEY (to_queue_id) REFERENCES talk_queues(id) ON DELETE SET NULL,
    CONSTRAINT talk_transfers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT talk_notes_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE,
    CONSTRAINT talk_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_ticket_tags (
    ticket_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (ticket_id, tag_id),
    CONSTRAINT talk_ticket_tags_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE,
    CONSTRAINT talk_ticket_tags_tag FOREIGN KEY (tag_id) REFERENCES talk_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(20) NOT NULL DEFAULT 'system',
    event_type VARCHAR(60) NOT NULL,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY talk_events_ticket (ticket_id, created_at, id),
    CONSTRAINT talk_events_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE,
    CONSTRAINT talk_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_presence (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'offline',
    last_seen_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT talk_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talk_jack_interactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    summary TEXT NULL,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY talk_jack_ticket (ticket_id, created_at),
    CONSTRAINT talk_jack_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE,
    CONSTRAINT talk_jack_message FOREIGN KEY (message_id) REFERENCES talk_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
