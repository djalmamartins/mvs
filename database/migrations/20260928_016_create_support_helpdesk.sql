CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    requester_user_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    protocol VARCHAR(40) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    condominium_id BIGINT UNSIGNED NULL,
    unit_id BIGINT UNSIGNED NULL,
    due_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY support_tickets_tenant_protocol (tenant_id,protocol),
    KEY support_tickets_tenant_status (tenant_id,status,updated_at),
    KEY support_tickets_tenant_assignee (tenant_id,assigned_user_id,status),
    KEY support_tickets_tenant_due (tenant_id,due_at,status),
    CONSTRAINT support_tickets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE,
    CONSTRAINT support_tickets_requester_tenant_fk FOREIGN KEY (tenant_id,requester_user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT,
    CONSTRAINT support_tickets_assignee_tenant_fk FOREIGN KEY (tenant_id,assigned_user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT,
    CONSTRAINT support_tickets_creator_tenant_fk FOREIGN KEY (tenant_id,created_by) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT,
    CONSTRAINT support_tickets_status_check CHECK (status IN ('open','in_progress','waiting','resolved','closed')),
    CONSTRAINT support_tickets_priority_check CHECK (priority IN ('low','normal','high','urgent'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY support_ticket_events_tenant_ticket (tenant_id,ticket_id,created_at,id),
    CONSTRAINT support_ticket_events_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE,
    CONSTRAINT support_ticket_events_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES support_tickets(tenant_id,id) ON DELETE CASCADE,
    CONSTRAINT support_ticket_events_user_tenant_fk FOREIGN KEY (tenant_id,user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE support_tickets ADD UNIQUE KEY support_tickets_tenant_id (tenant_id,id);
