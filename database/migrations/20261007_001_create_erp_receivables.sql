ALTER TABLE erp_person_links
    ADD UNIQUE KEY uq_erp_person_links_scope (administrator_id, id, condominium_id, unit_id);

ALTER TABLE erp_accounting_periods
    ADD UNIQUE KEY uq_erp_accounting_periods_scope (administrator_id, id, condominium_id);

ALTER TABLE erp_accounting_plans
    ADD UNIQUE KEY uq_erp_accounting_plans_scope (administrator_id, id, condominium_id);

ALTER TABLE erp_accounting_accounts
    ADD UNIQUE KEY uq_erp_accounting_accounts_scope (administrator_id, plan_id, id);

CREATE TABLE erp_receivables (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    person_link_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    receivable_account_id BIGINT UNSIGNED NOT NULL,
    revenue_account_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(200) NOT NULL,
    total_amount DECIMAL(13,2) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_receivables_scope (administrator_id, id, condominium_id),
    KEY idx_erp_receivables_listing (administrator_id, condominium_id, created_at, id),
    KEY idx_erp_receivables_unit (condominium_id, unit_id, created_at),
    CONSTRAINT fk_erp_receivables_condominium FOREIGN KEY (administrator_id, condominium_id)
        REFERENCES erp_condominiums(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_receivables_unit FOREIGN KEY (condominium_id, unit_id)
        REFERENCES erp_units(condominium_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_receivables_person_link FOREIGN KEY (administrator_id, person_link_id, condominium_id, unit_id)
        REFERENCES erp_person_links(administrator_id, id, condominium_id, unit_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_receivables_plan FOREIGN KEY (administrator_id, plan_id, condominium_id)
        REFERENCES erp_accounting_plans(administrator_id, id, condominium_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_receivables_asset_account FOREIGN KEY (administrator_id, plan_id, receivable_account_id)
        REFERENCES erp_accounting_accounts(administrator_id, plan_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_receivables_revenue_account FOREIGN KEY (administrator_id, plan_id, revenue_account_id)
        REFERENCES erp_accounting_accounts(administrator_id, plan_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_receivables_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_receivables_description CHECK (CHAR_LENGTH(TRIM(description)) BETWEEN 1 AND 200),
    CONSTRAINT chk_erp_receivables_amount CHECK (total_amount > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE erp_receivable_installments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    receivable_id BIGINT UNSIGNED NOT NULL,
    installment_number SMALLINT UNSIGNED NOT NULL,
    accounting_period_id BIGINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(13,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_receivable_installments_number (administrator_id, receivable_id, installment_number),
    KEY idx_erp_receivable_installments_due (administrator_id, condominium_id, due_date, id),
    CONSTRAINT fk_erp_receivable_installments_receivable FOREIGN KEY (administrator_id, receivable_id, condominium_id)
        REFERENCES erp_receivables(administrator_id, id, condominium_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_receivable_installments_period FOREIGN KEY (administrator_id, accounting_period_id, condominium_id)
        REFERENCES erp_accounting_periods(administrator_id, id, condominium_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_receivable_installments_number CHECK (installment_number > 0),
    CONSTRAINT chk_erp_receivable_installments_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
