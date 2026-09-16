CREATE TABLE support_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY support_products_status (status),
    CONSTRAINT support_products_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE support_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NULL,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    description TEXT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY support_categories_slug (product_id, slug),
    KEY support_categories_listing (product_id, status, position),
    CONSTRAINT support_categories_product FOREIGN KEY (product_id) REFERENCES support_products(id) ON DELETE CASCADE,
    CONSTRAINT support_categories_parent FOREIGN KEY (parent_id) REFERENCES support_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE support_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY support_articles_listing (status, updated_at),
    KEY support_articles_product (product_id, status),
    KEY support_articles_category (category_id, status),
    CONSTRAINT support_articles_product FOREIGN KEY (product_id) REFERENCES support_products(id) ON DELETE SET NULL,
    CONSTRAINT support_articles_category FOREIGN KEY (category_id) REFERENCES support_categories(id) ON DELETE SET NULL,
    CONSTRAINT support_articles_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE support_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE support_article_tags (
    article_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    CONSTRAINT support_article_tags_article FOREIGN KEY (article_id) REFERENCES support_articles(id) ON DELETE CASCADE,
    CONSTRAINT support_article_tags_tag FOREIGN KEY (tag_id) REFERENCES support_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE support_article_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NULL,
    content LONGTEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY support_article_revisions_listing (article_id, created_at),
    CONSTRAINT support_article_revisions_article FOREIGN KEY (article_id) REFERENCES support_articles(id) ON DELETE CASCADE,
    CONSTRAINT support_article_revisions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
