CREATE TABLE IF NOT EXISTS studio_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product VARCHAR(30) NOT NULL DEFAULT 'studio',
    version VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    notes TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'current',
    created_by BIGINT UNSIGNED NULL,
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY studio_versions_product_version (product, version),
    KEY studio_versions_current (product, status, published_at),
    CONSTRAINT studio_versions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS studio_log_states (
    fingerprint CHAR(64) PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY studio_log_states_status (status, updated_at),
    CONSTRAINT studio_log_states_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
