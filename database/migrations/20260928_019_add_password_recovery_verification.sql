ALTER TABLE password_recovery_requests
    ADD COLUMN verified_at DATETIME NULL AFTER expires_at,
    ADD KEY password_recovery_state (id, used_at, verified_at, expires_at);
