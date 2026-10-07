CREATE TABLE erp_accounting_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_accounting_plans_administrator_id (administrator_id, id),
    UNIQUE KEY uq_erp_accounting_plans_condominium (administrator_id, condominium_id),
    CONSTRAINT fk_erp_accounting_plans_administrator FOREIGN KEY (administrator_id)
        REFERENCES erp_administrators(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_plans_condominium FOREIGN KEY (administrator_id, condominium_id)
        REFERENCES erp_condominiums(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_plans_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_plans_updated_by FOREIGN KEY (updated_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_accounting_plans_name CHECK (CHAR_LENGTH(TRIM(name)) BETWEEN 1 AND 160),
    CONSTRAINT chk_erp_accounting_plans_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE erp_accounting_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    nature VARCHAR(20) NOT NULL,
    account_type VARCHAR(20) NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_accounting_accounts_plan_code (plan_id, code),
    UNIQUE KEY uq_erp_accounting_accounts_plan_id (plan_id, id),
    KEY idx_erp_accounting_accounts_tree (administrator_id, plan_id, parent_id, sort_order, code),
    KEY idx_erp_accounting_accounts_search (administrator_id, plan_id, nature, account_type, status),
    CONSTRAINT fk_erp_accounting_accounts_plan FOREIGN KEY (administrator_id, plan_id)
        REFERENCES erp_accounting_plans(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_accounts_parent FOREIGN KEY (plan_id, parent_id)
        REFERENCES erp_accounting_accounts(plan_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_accounts_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_accounting_accounts_updated_by FOREIGN KEY (updated_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_accounting_accounts_code CHECK (CHAR_LENGTH(TRIM(code)) BETWEEN 1 AND 32),
    CONSTRAINT chk_erp_accounting_accounts_name CHECK (CHAR_LENGTH(TRIM(name)) BETWEEN 1 AND 160),
    CONSTRAINT chk_erp_accounting_accounts_nature CHECK (nature IN ('asset', 'liability', 'equity', 'revenue', 'expense')),
    CONSTRAINT chk_erp_accounting_accounts_type CHECK (account_type IN ('synthetic', 'analytic')),
    CONSTRAINT chk_erp_accounting_accounts_level CHECK (level BETWEEN 1 AND 8),
    CONSTRAINT chk_erp_accounting_accounts_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
