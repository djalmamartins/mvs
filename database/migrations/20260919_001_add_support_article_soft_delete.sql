-- Moves Support
-- Knowledge Base v1.2: exclusão progressiva de artigos.

ALTER TABLE support_articles
    ADD COLUMN deleted_at DATETIME NULL AFTER published_at,
    ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
    ADD KEY support_articles_deleted (deleted_at, updated_at),
    ADD KEY support_articles_deleted_by (deleted_by),
    ADD CONSTRAINT support_articles_deleted_by_user
        FOREIGN KEY (deleted_by)
        REFERENCES users(id)
        ON DELETE SET NULL;
