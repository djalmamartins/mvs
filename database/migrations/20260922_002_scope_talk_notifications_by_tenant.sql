-- Scope notification rows to the tenant that originated them.
ALTER TABLE talk_notifications
    ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
    ADD KEY talk_notifications_tenant_recipient (tenant_id,recipient_id,read_at,created_at),
    ADD CONSTRAINT talk_notifications_tenant_fk FOREIGN KEY(tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
