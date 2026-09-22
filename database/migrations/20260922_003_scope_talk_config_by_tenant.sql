-- Tenant-scope settings and channel configuration.
ALTER TABLE talk_settings
    ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 FIRST,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (tenant_id,setting_key),
    ADD CONSTRAINT talk_settings_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;

ALTER TABLE talk_channels
    ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD KEY talk_channels_tenant (tenant_id,status),
    ADD CONSTRAINT talk_channels_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
