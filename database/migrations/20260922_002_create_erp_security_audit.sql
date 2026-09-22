CREATE TABLE IF NOT EXISTS erp_security_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type VARCHAR(80) NOT NULL,
    actor_user_id INTEGER NULL,
    subject_user_id INTEGER NULL,
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_erp_security_audit_subject
    ON erp_security_audit (subject_user_id, created_at);

CREATE INDEX IF NOT EXISTS idx_erp_security_audit_event
    ON erp_security_audit (event_type, created_at);
