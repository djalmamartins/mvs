ALTER TABLE talk_messages
    ADD COLUMN read_at DATETIME NULL AFTER sent_at,
    ADD KEY talk_messages_unread (conversation_id, direction, read_at, id);
