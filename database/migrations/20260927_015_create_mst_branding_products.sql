ALTER TABLE mst_administrators
    ADD COLUMN logo_path VARCHAR(500) NULL AFTER notes,
    ADD COLUMN primary_color VARCHAR(7) NOT NULL DEFAULT '#6E00B3' AFTER logo_path,
    ADD COLUMN secondary_color VARCHAR(7) NULL AFTER primary_color;

CREATE TABLE IF NOT EXISTS mst_tenant_products (
    tenant_id BIGINT UNSIGNED NOT NULL,
    product_key VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    enabled_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id,product_key),
    CONSTRAINT mst_products_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO mst_tenant_products(tenant_id,product_key,status)
SELECT id,'talk','active' FROM talk_tenants;

INSERT IGNORE INTO mst_tenant_products(tenant_id,product_key,status)
SELECT id,'day','inactive' FROM talk_tenants;
INSERT IGNORE INTO mst_tenant_products(tenant_id,product_key,status)
SELECT id,'support','inactive' FROM talk_tenants;
INSERT IGNORE INTO mst_tenant_products(tenant_id,product_key,status)
SELECT id,'erp','inactive' FROM talk_tenants;
INSERT IGNORE INTO mst_tenant_products(tenant_id,product_key,status)
SELECT id,'cms','inactive' FROM talk_tenants;

CREATE TABLE IF NOT EXISTS mst_security_settings (
    tenant_id BIGINT UNSIGNED PRIMARY KEY,
    require_mfa TINYINT(1) NOT NULL DEFAULT 0,
    session_timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 480,
    allowed_email_domains VARCHAR(1000) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT mst_security_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO mst_security_settings(tenant_id)
SELECT id FROM talk_tenants;