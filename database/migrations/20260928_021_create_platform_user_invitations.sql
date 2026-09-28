CREATE TABLE platform_user_invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    revoked_at DATETIME NULL,
    invited_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY platform_user_invitations_token_unique (token_hash),
    KEY platform_user_invitations_user_status (user_id,accepted_at,revoked_at),
    KEY platform_user_invitations_tenant_created (tenant_id,created_at),
    CONSTRAINT platform_user_invitations_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE,
    CONSTRAINT platform_user_invitations_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT platform_user_invitations_role_fk FOREIGN KEY (role_id) REFERENCES platform_roles(id) ON DELETE RESTRICT,
    CONSTRAINT platform_user_invitations_inviter_fk FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
