CREATE TABLE IF NOT EXISTS mst_user_invitations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mst_user_invitations_token (token_hash),
    KEY idx_mst_user_invitations_user (tenant_id,user_id,accepted_at,expires_at),
    CONSTRAINT fk_mst_user_invitations_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_mst_user_invitations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_mst_user_invitations_actor FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
