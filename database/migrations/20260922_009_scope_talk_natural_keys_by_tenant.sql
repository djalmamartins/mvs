-- Scope legacy Talk natural keys per tenant for true multi-administradora isolation.
ALTER TABLE talk_departments
    DROP INDEX slug,
    ADD UNIQUE KEY talk_departments_tenant_slug (tenant_id,slug);

ALTER TABLE talk_queues
    DROP INDEX slug,
    ADD UNIQUE KEY talk_queues_tenant_slug (tenant_id,slug);

ALTER TABLE talk_contacts
    DROP INDEX talk_contacts_channel_external,
    ADD UNIQUE KEY talk_contacts_tenant_channel_external (tenant_id,channel,external_id);

ALTER TABLE talk_conversations
    DROP INDEX talk_conversations_channel_external,
    ADD UNIQUE KEY talk_conversations_tenant_channel_external (tenant_id,channel,external_id);

ALTER TABLE talk_tags
    DROP INDEX slug,
    ADD UNIQUE KEY talk_tags_tenant_slug (tenant_id,slug);
