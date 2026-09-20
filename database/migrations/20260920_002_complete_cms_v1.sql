ALTER TABLE studio_content
    ADD COLUMN seo_focus_keyword VARCHAR(120) NULL AFTER seo_description,
    ADD COLUMN canonical_url VARCHAR(500) NULL AFTER seo_focus_keyword,
    ADD COLUMN robots_index TINYINT(1) NOT NULL DEFAULT 1 AFTER canonical_url,
    ADD COLUMN robots_follow TINYINT(1) NOT NULL DEFAULT 1 AFTER robots_index,
    ADD COLUMN deleted_at DATETIME NULL AFTER published_at,
    ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at,
    ADD COLUMN deleted_status VARCHAR(20) NULL AFTER deleted_by,
    ADD KEY studio_content_deleted_listing (deleted_at, type, updated_at),
    ADD CONSTRAINT studio_content_deleted_user FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE studio_content_revisions
    ADD COLUMN seo_focus_keyword VARCHAR(120) NULL AFTER seo_description,
    ADD COLUMN canonical_url VARCHAR(500) NULL AFTER seo_focus_keyword,
    ADD COLUMN robots_index TINYINT(1) NOT NULL DEFAULT 1 AFTER canonical_url,
    ADD COLUMN robots_follow TINYINT(1) NOT NULL DEFAULT 1 AFTER robots_index;

CREATE TABLE IF NOT EXISTS studio_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS studio_content_tags (
    content_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (content_id, tag_id),
    KEY studio_content_tags_tag (tag_id, content_id),
    CONSTRAINT studio_content_tags_content FOREIGN KEY (content_id) REFERENCES studio_content(id) ON DELETE CASCADE,
    CONSTRAINT studio_content_tags_tag FOREIGN KEY (tag_id) REFERENCES studio_tags(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS studio_menus (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    location VARCHAR(80) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY studio_menus_location (location)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS studio_menu_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    label VARCHAR(120) NOT NULL,
    type VARCHAR(20) NOT NULL,
    page_id BIGINT UNSIGNED NULL,
    url VARCHAR(500) NULL,
    target VARCHAR(20) NOT NULL DEFAULT '_self',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY studio_menu_items_order (menu_id, parent_id, position, id),
    CONSTRAINT studio_menu_items_menu FOREIGN KEY (menu_id) REFERENCES studio_menus(id) ON DELETE CASCADE,
    CONSTRAINT studio_menu_items_parent FOREIGN KEY (parent_id) REFERENCES studio_menu_items(id) ON DELETE SET NULL,
    CONSTRAINT studio_menu_items_page FOREIGN KEY (page_id) REFERENCES studio_content(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
