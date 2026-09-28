CREATE TABLE IF NOT EXISTS erp_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    version_no INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_erp_documents_scope (condominium_id, entity_type, entity_id),
    KEY idx_erp_documents_category (category),
    UNIQUE KEY uq_erp_documents_version (condominium_id, entity_type, entity_id, storage_key, version_no),
    CONSTRAINT fk_erp_documents_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS erp_timeline_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    metadata_json TEXT NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_erp_timeline_entity (condominium_id, entity_type, entity_id, occurred_at),
    CONSTRAINT fk_erp_timeline_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT
);