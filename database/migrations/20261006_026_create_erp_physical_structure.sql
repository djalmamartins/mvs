ALTER TABLE erp_condominiums
    ADD UNIQUE KEY uq_erp_condominiums_administrator_id (administrator_id, id);

CREATE TABLE erp_blocks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_blocks_condominium_code (condominium_id, code),
    UNIQUE KEY uq_erp_blocks_condominium_id (condominium_id, id),
    CONSTRAINT fk_erp_blocks_condominium FOREIGN KEY (condominium_id)
        REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE erp_units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    block_id BIGINT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    complement VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_units_condominium_code (condominium_id, code),
    UNIQUE KEY uq_erp_units_condominium_id (condominium_id, id),
    KEY idx_erp_units_block (block_id),
    CONSTRAINT fk_erp_units_condominium FOREIGN KEY (condominium_id)
        REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_units_block FOREIGN KEY (condominium_id, block_id)
        REFERENCES erp_blocks(condominium_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
