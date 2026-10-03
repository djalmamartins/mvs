CREATE TABLE platform_mfa_recovery_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT platform_mfa_recovery_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY platform_mfa_recovery_available (user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
