-- WhatsApp provider message ids must be unique to keep webhook retries idempotent.
-- Existing duplicates must be resolved before this migration in legacy installations.
ALTER TABLE talk_messages
    ADD UNIQUE KEY talk_messages_external_id_unique (external_id);
