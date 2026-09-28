CREATE TABLE IF NOT EXISTS erp_people (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(190) NOT NULL,
    document_type VARCHAR(20) NULL,
    document_number VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_people_document (document_type, document_number),
    KEY idx_erp_people_name (full_name),
    KEY idx_erp_people_status (status)
);

CREATE TABLE IF NOT EXISTS erp_person_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NULL,
    role VARCHAR(40) NOT NULL,
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_erp_person_links_person (person_id),
    KEY idx_erp_person_links_condominium (condominium_id),
    KEY idx_erp_person_links_unit (unit_id),
    KEY idx_erp_person_links_period (starts_at, ends_at),
    CONSTRAINT fk_erp_person_links_person FOREIGN KEY (person_id) REFERENCES erp_people(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_person_links_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_person_links_unit FOREIGN KEY (unit_id) REFERENCES erp_units(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_person_links_period CHECK (ends_at IS NULL OR ends_at >= starts_at)
);