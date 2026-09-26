ALTER TABLE talk_channels
    ADD COLUMN display_name VARCHAR(160) NULL AFTER phone_number,
    ADD COLUMN connected_jid VARCHAR(190) NULL AFTER display_name,
    ADD COLUMN default_queue_id BIGINT UNSIGNED NULL AFTER session_key,
    ADD COLUMN last_disconnected_at DATETIME NULL AFTER last_connected_at,
    ADD COLUMN last_error_at DATETIME NULL AFTER last_error,
    ADD COLUMN metadata JSON NULL AFTER config;

UPDATE talk_channels
SET session_key=CASE
    WHEN external_id REGEXP '^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$' THEN external_id
    ELSE CONCAT('channel-',id,'-',LEFT(SHA2(CONCAT(external_id,'-',id),256),16))
END
WHERE session_key IS NULL OR session_key='' OR session_key NOT REGEXP '^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$';

ALTER TABLE talk_channels
    MODIFY session_key VARCHAR(128) NOT NULL,
    ADD UNIQUE KEY talk_channels_session_key (session_key),
    ADD KEY talk_channels_tenant_connection (tenant_id,connection_status),
    ADD KEY talk_channels_default_queue (tenant_id,default_queue_id),
    ADD CONSTRAINT talk_channels_default_queue_tenant_fk
        FOREIGN KEY (tenant_id,default_queue_id)
        REFERENCES talk_queues(tenant_id,id)
        ON DELETE RESTRICT;
