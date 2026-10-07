CREATE TABLE erp_accounting_periods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_accounting_periods_administrator_id (administrator_id, id),
    UNIQUE KEY uq_erp_accounting_periods_month (administrator_id, condominium_id, period_year, period_month),
    KEY idx_erp_accounting_periods_listing (administrator_id, period_year, period_month, status),
    CONSTRAINT fk_erp_accounting_periods_administrator FOREIGN KEY (administrator_id)
        REFERENCES erp_administrators(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_periods_condominium FOREIGN KEY (administrator_id, condominium_id)
        REFERENCES erp_condominiums(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_periods_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_periods_updated_by FOREIGN KEY (updated_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_accounting_periods_year CHECK (period_year BETWEEN 2000 AND 2100),
    CONSTRAINT chk_erp_accounting_periods_month CHECK (period_month BETWEEN 1 AND 12),
    CONSTRAINT chk_erp_accounting_periods_status CHECK (status = 'open')
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
