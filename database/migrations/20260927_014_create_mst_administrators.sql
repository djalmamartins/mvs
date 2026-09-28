CREATE TABLE IF NOT EXISTS mst_administrators (
    tenant_id BIGINT UNSIGNED PRIMARY KEY,
    legal_name VARCHAR(180) NOT NULL,
    trade_name VARCHAR(160) NULL,
    tax_id VARCHAR(20) NOT NULL,
    contact_name VARCHAR(120) NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    notes VARCHAR(1000) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY mst_administrators_tax_id (tax_id),
    KEY mst_administrators_status_name (status, legal_name),
    CONSTRAINT mst_administrators_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mst_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY mst_audit_tenant_created (tenant_id, created_at),
    KEY mst_audit_actor_created (actor_user_id, created_at),
    CONSTRAINT mst_audit_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE SET NULL,
    CONSTRAINT mst_audit_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO mst_administrators (tenant_id, legal_name, trade_name, tax_id, status)
SELECT id, name, name, CONCAT('LEGACY-', id), status
FROM talk_tenants;