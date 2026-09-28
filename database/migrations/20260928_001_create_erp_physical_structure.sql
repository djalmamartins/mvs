CREATE TABLE IF NOT EXISTS erp_blocks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_blocks_condominium_code (condominium_id, code),
    CONSTRAINT fk_erp_blocks_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS erp_units (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    block_id BIGINT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    floor VARCHAR(20) NULL,
    ideal_fraction DECIMAL(12,8) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_units_condominium_code (condominium_id, code),
    KEY idx_erp_units_block (block_id),
    CONSTRAINT fk_erp_units_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_units_block FOREIGN KEY (block_id) REFERENCES erp_blocks(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_units_ideal_fraction CHECK (ideal_fraction >= 0 AND ideal_fraction <= 1)
);

CREATE TABLE IF NOT EXISTS erp_parking_spaces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    kind VARCHAR(30) NOT NULL DEFAULT 'vehicle',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_parking_condominium_code (condominium_id, code),
    CONSTRAINT fk_erp_parking_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_parking_unit FOREIGN KEY (unit_id) REFERENCES erp_units(id) ON UPDATE RESTRICT ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS erp_common_areas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    condominium_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    reservable TINYINT(1) NOT NULL DEFAULT 0,
    capacity INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_common_areas_condominium_code (condominium_id, code),
    CONSTRAINT fk_erp_common_areas_condominium FOREIGN KEY (condominium_id) REFERENCES erp_condominiums(id) ON UPDATE RESTRICT ON DELETE RESTRICT
);