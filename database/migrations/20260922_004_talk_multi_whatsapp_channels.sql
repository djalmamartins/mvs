-- Allow one administrator/tenant to operate multiple independent WhatsApp numbers.
ALTER TABLE talk_channels
    ADD COLUMN phone_number VARCHAR(32) NULL AFTER name,
    ADD COLUMN external_account_id VARCHAR(190) NULL AFTER phone_number,
    ADD COLUMN provider VARCHAR(40) NOT NULL DEFAULT 'whatsapp' AFTER type,
    ADD COLUMN session_key VARCHAR(190) NULL AFTER provider,
    ADD COLUMN queue_id BIGINT UNSIGNED NULL AFTER session_key,
    ADD UNIQUE KEY talk_channels_tenant_phone (tenant_id,phone_number),
    ADD UNIQUE KEY talk_channels_tenant_session (tenant_id,session_key),
    ADD KEY talk_channels_queue (tenant_id,queue_id),
    ADD CONSTRAINT talk_channels_queue_fk FOREIGN KEY(queue_id) REFERENCES talk_queues(id) ON DELETE SET NULL;
