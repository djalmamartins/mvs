-- Track message mutations such as WhatsApp delivery receipts without parsing JSON timestamps.
ALTER TABLE talk_messages
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
