CREATE TABLE IF NOT EXISTS mst_onboarding (
    tenant_id BIGINT UNSIGNED PRIMARY KEY,
    step VARCHAR(40) NOT NULL DEFAULT 'created',
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT mst_onboarding_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO mst_onboarding(tenant_id,step,completed_at)
SELECT tenant_id,'ready',CURRENT_TIMESTAMP FROM mst_administrators;