-- Tenant-specific public ticket protocol configuration and yearly counters.
ALTER TABLE talk_tenants
    ADD COLUMN protocol_prefix VARCHAR(12) NOT NULL DEFAULT 'TALK' AFTER brand_color,
    ADD COLUMN protocol_digits TINYINT UNSIGNED NOT NULL DEFAULT 7 AFTER protocol_prefix;

CREATE TABLE IF NOT EXISTS talk_protocol_sequences (
    tenant_id BIGINT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(tenant_id,year),
    CONSTRAINT talk_protocol_sequences_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
