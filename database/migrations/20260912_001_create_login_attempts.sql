CREATE TABLE IF NOT EXISTS login_attempts (
    key_hash CHAR(64) PRIMARY KEY,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX login_attempts_blocked_until_index (blocked_until)
) ENGINE=InnoDB
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
