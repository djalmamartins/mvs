ALTER TABLE studio_media
    ADD COLUMN alt_text VARCHAR(255) NULL AFTER name,
    ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER height,
    ADD COLUMN crop_data VARCHAR(255) NULL AFTER parent_id,
    ADD CONSTRAINT studio_media_parent FOREIGN KEY (parent_id) REFERENCES studio_media(id) ON DELETE SET NULL;

ALTER TABLE studio_content
    ADD COLUMN seo_title VARCHAR(160) NULL AFTER content,
    ADD COLUMN seo_description VARCHAR(320) NULL AFTER seo_title,
    ADD COLUMN media_id BIGINT UNSIGNED NULL AFTER image_path,
    ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER media_id,
    ADD COLUMN template VARCHAR(60) NULL AFTER category_id,
    ADD COLUMN meta_json JSON NULL AFTER template,
    ADD COLUMN starts_at DATETIME NULL AFTER position,
    ADD COLUMN ends_at DATETIME NULL AFTER starts_at,
    ADD CONSTRAINT studio_content_media FOREIGN KEY (media_id) REFERENCES studio_media(id) ON DELETE SET NULL;

CREATE TABLE studio_taxonomies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY studio_taxonomy_type_slug (type, slug)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE studio_content
    ADD CONSTRAINT studio_content_category FOREIGN KEY (category_id) REFERENCES studio_taxonomies(id) ON DELETE SET NULL;
