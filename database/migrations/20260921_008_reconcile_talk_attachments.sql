-- Reconcile the early Talk attachment draft with the final attachment model.
-- This migration is safe when the final table already exists and upgrades installations
-- that created talk_attachments from 20260920_005.

SET @has_ticket := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND COLUMN_NAME='ticket_id');
SET @sql := IF(@has_ticket=0,'ALTER TABLE talk_attachments ADD COLUMN ticket_id BIGINT UNSIGNED NULL AFTER id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_uploaded := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND COLUMN_NAME='uploaded_by');
SET @sql := IF(@has_uploaded=0,'ALTER TABLE talk_attachments ADD COLUMN uploaded_by BIGINT UNSIGNED NULL AFTER message_id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_stored := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND COLUMN_NAME='stored_name');
SET @sql := IF(@has_stored=0,'ALTER TABLE talk_attachments ADD COLUMN stored_name VARCHAR(255) NULL AFTER original_name','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_size := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND COLUMN_NAME='size_bytes');
SET @sql := IF(@has_size=0,'ALTER TABLE talk_attachments ADD COLUMN size_bytes BIGINT UNSIGNED NULL AFTER mime_type','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_size := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND COLUMN_NAME='file_size');
SET @sql := IF(@has_old_size>0,'UPDATE talk_attachments SET size_bytes=COALESCE(size_bytes,file_size)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE talk_attachments a
INNER JOIN talk_messages m ON m.id=a.message_id
SET a.ticket_id=COALESCE(a.ticket_id,m.ticket_id)
WHERE a.ticket_id IS NULL;

UPDATE talk_attachments
SET stored_name=COALESCE(NULLIF(stored_name,''),SUBSTRING_INDEX(storage_path,'/',-1)),
    size_bytes=COALESCE(size_bytes,0);

SET @idx_ticket := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND INDEX_NAME='talk_attachments_ticket');
SET @sql := IF(@idx_ticket=0,'ALTER TABLE talk_attachments ADD KEY talk_attachments_ticket (ticket_id,created_at,id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_ticket := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND CONSTRAINT_NAME='talk_attachments_ticket');
SET @sql := IF(@fk_ticket=0,'ALTER TABLE talk_attachments ADD CONSTRAINT talk_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES talk_tickets(id) ON DELETE CASCADE','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_uploader := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='talk_attachments' AND CONSTRAINT_NAME='talk_attachments_uploaded_by');
SET @sql := IF(@fk_uploader=0,'ALTER TABLE talk_attachments ADD CONSTRAINT talk_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
