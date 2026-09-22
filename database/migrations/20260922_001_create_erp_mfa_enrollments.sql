CREATE TABLE IF NOT EXISTS erp_mfa_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    method VARCHAR(20) NOT NULL DEFAULT 'totp',
    secret_ciphertext TEXT NOT NULL,
    secret_key_id VARCHAR(120) NOT NULL,
    enabled_at TIMESTAMP NULL,
    disabled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_erp_mfa_enrollments_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT uq_erp_mfa_enrollments_user_method UNIQUE (user_id, method),
    INDEX idx_erp_mfa_enrollments_active (user_id, method, enabled_at, disabled_at)
) ENGINE=InnoDB
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
