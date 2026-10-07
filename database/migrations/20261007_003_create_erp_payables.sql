CREATE TABLE erp_payables (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    liability_account_id BIGINT UNSIGNED NOT NULL,
    expense_account_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(200) NOT NULL,
    total_amount DECIMAL(13,2) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_payables_scope (administrator_id, id, condominium_id),
    KEY idx_erp_payables_listing (administrator_id, condominium_id, created_at, id),
    KEY idx_erp_payables_supplier (administrator_id, supplier_id, created_at),
    CONSTRAINT fk_erp_payables_condominium FOREIGN KEY (administrator_id, condominium_id)
        REFERENCES erp_condominiums(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_payables_supplier FOREIGN KEY (administrator_id, supplier_id)
        REFERENCES erp_suppliers(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_payables_plan FOREIGN KEY (administrator_id, plan_id, condominium_id)
        REFERENCES erp_accounting_plans(administrator_id, id, condominium_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_payables_liability FOREIGN KEY (administrator_id, plan_id, liability_account_id)
        REFERENCES erp_accounting_accounts(administrator_id, plan_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_payables_expense FOREIGN KEY (administrator_id, plan_id, expense_account_id)
        REFERENCES erp_accounting_accounts(administrator_id, plan_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_payables_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_payables_description CHECK (CHAR_LENGTH(TRIM(description)) BETWEEN 1 AND 200),
    CONSTRAINT chk_erp_payables_amount CHECK (total_amount > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE erp_payable_installments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    payable_id BIGINT UNSIGNED NOT NULL,
    installment_number SMALLINT UNSIGNED NOT NULL,
    accounting_period_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(13,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_payable_installments_number (administrator_id, payable_id, installment_number),
    KEY idx_erp_payable_installments_due (administrator_id, condominium_id, due_date, id),
    CONSTRAINT fk_erp_payable_installments_payable FOREIGN KEY (administrator_id, payable_id, condominium_id)
        REFERENCES erp_payables(administrator_id, id, condominium_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_payable_installments_period FOREIGN KEY (administrator_id, accounting_period_id, condominium_id)
        REFERENCES erp_accounting_periods(administrator_id, id, condominium_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_payable_installments_number CHECK (installment_number > 0),
    CONSTRAINT chk_erp_payable_installments_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
