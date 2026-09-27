ALTER TABLE talk_channels
    ADD COLUMN connected_phone_jid VARCHAR(190) NULL AFTER connected_jid,
    ADD COLUMN connected_lid VARCHAR(190) NULL AFTER connected_phone_jid,
    ADD COLUMN bridge_seen_at DATETIME NULL AFTER last_disconnected_at,
    ADD COLUMN removed_at DATETIME NULL AFTER last_error_at;

UPDATE talk_channels
SET phone_number=NULL, connected_phone_jid=NULL
WHERE connected_jid REGEXP ':[0-9]+@s\\.whatsapp\\.net$';
