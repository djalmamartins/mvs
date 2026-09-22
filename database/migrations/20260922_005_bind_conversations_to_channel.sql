-- Bind every conversation to the exact inbound/outbound channel instance.
ALTER TABLE talk_conversations
    ADD COLUMN channel_id BIGINT UNSIGNED NULL AFTER tenant_id,
    ADD KEY talk_conversations_channel (tenant_id,channel_id),
    ADD CONSTRAINT talk_conversations_channel_fk FOREIGN KEY(channel_id) REFERENCES talk_channels(id) ON DELETE RESTRICT;
