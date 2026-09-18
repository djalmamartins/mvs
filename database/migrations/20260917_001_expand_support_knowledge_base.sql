-- Moves Support
-- Knowledge Base v1.1
--
-- Evolução incremental da estrutura criada em
-- 20260916_001_create_support_knowledge_base.sql.
--
-- A biblioteca de mídia oficial continua sendo studio_media.

ALTER TABLE support_articles

    ADD COLUMN cover_media_id BIGINT UNSIGNED NULL
        AFTER content,

    ADD COLUMN meta_title VARCHAR(255) NULL
        AFTER cover_media_id,

    ADD COLUMN meta_description VARCHAR(320) NULL
        AFTER meta_title,

    ADD COLUMN focus_keyword VARCHAR(150) NULL
        AFTER meta_description,

    ADD COLUMN canonical_url VARCHAR(500) NULL
        AFTER focus_keyword,

    ADD COLUMN robots_index TINYINT(1) NOT NULL DEFAULT 1
        AFTER canonical_url,

    ADD COLUMN robots_follow TINYINT(1) NOT NULL DEFAULT 1
        AFTER robots_index,

    ADD COLUMN word_count INT UNSIGNED NOT NULL DEFAULT 0
        AFTER robots_follow,

    ADD COLUMN reading_time INT UNSIGNED NOT NULL DEFAULT 0
        AFTER word_count,

    ADD KEY support_articles_cover_media (cover_media_id),

    ADD CONSTRAINT support_articles_cover_media
        FOREIGN KEY (cover_media_id)
        REFERENCES studio_media(id)
        ON DELETE SET NULL;
