CREATE TABLE erp_people (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(20) NOT NULL DEFAULT 'person',
    full_name VARCHAR(190) NOT NULL,
    trade_name VARCHAR(190) NULL,
    document_type VARCHAR(10) NULL,
    document_number VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_people_administrator_id (administrator_id, id),
    UNIQUE KEY uq_erp_people_document (administrator_id, document_type, document_number),
    KEY idx_erp_people_name (administrator_id, full_name),
    KEY idx_erp_people_status (administrator_id, status),
    CONSTRAINT fk_erp_people_administrator FOREIGN KEY (administrator_id)
        REFERENCES erp_administrators(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_people_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_people_entity_type CHECK (entity_type IN ('person', 'organization')),
    CONSTRAINT chk_erp_people_document CHECK (
        (document_type IS NULL AND document_number IS NULL)
        OR (document_type = 'cpf' AND CHAR_LENGTH(document_number) = 11)
        OR (document_type = 'cnpj' AND CHAR_LENGTH(document_number) = 14)
    ),
    CONSTRAINT chk_erp_people_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE erp_person_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NULL,
    role VARCHAR(40) NOT NULL,
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    source VARCHAR(40) NOT NULL DEFAULT 'staff',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    ended_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_person_links_administrator_id (administrator_id, id),
    KEY idx_erp_person_links_person (administrator_id, person_id, starts_at, ends_at),
    KEY idx_erp_person_links_condominium (administrator_id, condominium_id, status),
    KEY idx_erp_person_links_unit (condominium_id, unit_id, status),
    KEY idx_erp_person_links_period (starts_at, ends_at),
    CONSTRAINT fk_erp_person_links_person FOREIGN KEY (administrator_id, person_id)
        REFERENCES erp_people(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_person_links_condominium FOREIGN KEY (administrator_id, condominium_id)
        REFERENCES erp_condominiums(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_person_links_unit FOREIGN KEY (condominium_id, unit_id)
        REFERENCES erp_units(condominium_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_person_links_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_person_links_ended_by FOREIGN KEY (ended_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_person_links_period CHECK (ends_at IS NULL OR ends_at >= starts_at),
    CONSTRAINT chk_erp_person_links_status CHECK (status IN ('active', 'inactive')),
    CONSTRAINT chk_erp_person_links_role CHECK (role IN ('owner', 'tenant', 'resident', 'manager', 'deputy_manager', 'council', 'proxy'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
