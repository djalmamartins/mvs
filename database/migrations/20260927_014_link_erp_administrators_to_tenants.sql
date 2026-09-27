-- talk_tenants is the canonical platform tenant table until its legacy name
-- can be changed safely. ERP administrators are profiles within that tenant.
CREATE TABLE platform_tenant_products (
    tenant_id BIGINT UNSIGNED NOT NULL,
    product VARCHAR(32) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id,product),
    CONSTRAINT platform_tenant_products_tenant_fk
        FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Existing Talk tenants retain their active product. ERP-only tenants are
-- created below, after this backfill, so Talk is not enabled for them.
INSERT INTO platform_tenant_products(tenant_id,product,enabled)
SELECT id,'talk',1 FROM talk_tenants;

ALTER TABLE erp_administrators ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;

INSERT IGNORE INTO talk_tenants(name,slug,status)
SELECT legal_name,CONCAT('erp-administrator-',id),status
FROM erp_administrators WHERE tenant_id IS NULL;

UPDATE erp_administrators a
INNER JOIN talk_tenants t ON t.slug=CONCAT('erp-administrator-',a.id)
SET a.tenant_id=t.id WHERE a.tenant_id IS NULL;

ALTER TABLE erp_administrators
    MODIFY tenant_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY erp_administrators_tenant (tenant_id),
    ADD CONSTRAINT erp_administrators_tenant_fk
        FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE RESTRICT;

INSERT INTO platform_tenant_products(tenant_id,product,enabled)
SELECT tenant_id,'erp',1 FROM erp_administrators;

-- Preserve existing ERP grants by linking their users to the canonical
-- tenant. Product activation still gates access to Talk independently.
INSERT IGNORE INTO talk_tenant_users(tenant_id,user_id,role,status,is_default)
SELECT a.tenant_id,g.user_id,'agent','active',0
FROM erp_scope_grants g
INNER JOIN erp_administrators a ON a.id=g.scope_id
WHERE g.scope_type='administrator' AND g.revoked_at IS NULL;

INSERT IGNORE INTO talk_tenant_users(tenant_id,user_id,role,status,is_default)
SELECT a.tenant_id,g.user_id,'agent','active',0
FROM erp_scope_grants g
INNER JOIN erp_condominiums c ON c.id=g.scope_id
INNER JOIN erp_administrators a ON a.id=c.administrator_id
WHERE g.scope_type='condominium' AND g.revoked_at IS NULL;
