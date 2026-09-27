CREATE TABLE IF NOT EXISTS erp_scope_grants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    capability VARCHAR(120) NOT NULL,
    scope_type VARCHAR(32) NOT NULL,
    scope_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_erp_scope_grants_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    CONSTRAINT uq_erp_scope_grant
        UNIQUE (user_id, capability, scope_type, scope_id),
    INDEX idx_erp_scope_grants_lookup (user_id, capability, scope_type, scope_id, revoked_at),
    INDEX idx_erp_scope_grants_scope (scope_type, scope_id, revoked_at)
) ENGINE=InnoDB
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
