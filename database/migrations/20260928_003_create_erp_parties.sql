CREATE TABLE IF NOT EXISTS erp_parties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(30) NOT NULL,
    legal_name VARCHAR(190) NOT NULL,
    trade_name VARCHAR(190) NULL,
    tax_id VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    bank_data_ciphertext TEXT NULL,
    bank_key_version VARCHAR(32) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_erp_parties_condominium (condominium_id),
    KEY idx_erp_parties_kind (kind),
    KEY idx_erp_parties_status (status),
    CONSTRAINT fk_erp_parties_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS erp_party_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    party_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    relationship_type VARCHAR(40) NOT NULL,
    contract_reference VARCHAR(100) NULL,
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_erp_party_links_party (party_id),
    KEY idx_erp_party_links_condominium (condominium_id),
    KEY idx_erp_party_links_period (starts_at, ends_at),
    CONSTRAINT fk_erp_party_links_party FOREIGN KEY (party_id) REFERENCES erp_parties(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_party_links_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_party_links_period CHECK (ends_at IS NULL OR ends_at >= starts_at)
);

CREATE TABLE IF NOT EXISTS erp_party_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    party_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_erp_party_reviews_party (party_id),
    CONSTRAINT fk_erp_party_reviews_party FOREIGN KEY (party_id) REFERENCES erp_parties(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_party_reviews_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_party_reviews_rating CHECK (rating BETWEEN 1 AND 5)
);