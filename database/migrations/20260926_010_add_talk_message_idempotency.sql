ALTER TABLE talk_messages
    ADD UNIQUE KEY talk_messages_external_id (external_id);
