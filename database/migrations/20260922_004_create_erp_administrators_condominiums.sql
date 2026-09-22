CREATE TABLE IF NOT EXISTS erp_administrators (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    legal_name VARCHAR(190) NOT NULL,
    trade_name VARCHAR(190) NULL,
    tax_id VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_administrators_tax_id (tax_id),
    KEY idx_erp_administrators_status (status)
);

CREATE TABLE IF NOT EXISTS erp_condominiums (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    legal_name VARCHAR(190) NOT NULL,
    trade_name VARCHAR(190) NULL,
    tax_id VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_condominiums_tax_id (tax_id),
    KEY idx_erp_condominiums_administrator (administrator_id),
    KEY idx_erp_condominiums_status (status),
    CONSTRAINT fk_erp_condominiums_administrator
        FOREIGN KEY (administrator_id) REFERENCES erp_administrators(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
);
