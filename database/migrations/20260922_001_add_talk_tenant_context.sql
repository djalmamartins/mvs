-- Talk SaaS tenant foundation. Existing installations are backfilled into the default tenant.
CREATE TABLE IF NOT EXISTS talk_tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    logo_url VARCHAR(500) NULL,
    brand_color VARCHAR(20) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO talk_tenants(id,name,slug,status) VALUES(1,'Administradora principal','principal','active');

CREATE TABLE IF NOT EXISTS talk_tenant_users (
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'member',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY(tenant_id,user_id),
    KEY talk_tenant_users_user(user_id,status),
    CONSTRAINT talk_tenant_users_tenant FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE,
    CONSTRAINT talk_tenant_users_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO talk_tenant_users(tenant_id,user_id,role,status)
SELECT 1,id,IF(role='admin','admin','member'),'active' FROM users WHERE status='active';

ALTER TABLE talk_departments ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY talk_departments_tenant(tenant_id,status), ADD CONSTRAINT talk_departments_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT;
ALTER TABLE talk_queues ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY talk_queues_tenant(tenant_id,status), ADD CONSTRAINT talk_queues_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT;
ALTER TABLE talk_contacts ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY talk_contacts_tenant(tenant_id,updated_at), ADD CONSTRAINT talk_contacts_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT;
ALTER TABLE talk_conversations ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY talk_conversations_tenant(tenant_id,status), ADD CONSTRAINT talk_conversations_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT;
ALTER TABLE talk_tickets ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY talk_tickets_tenant(tenant_id,status,updated_at), ADD CONSTRAINT talk_tickets_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT;
ALTER TABLE talk_tags ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY talk_tags_tenant(tenant_id,name), ADD CONSTRAINT talk_tags_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT;
