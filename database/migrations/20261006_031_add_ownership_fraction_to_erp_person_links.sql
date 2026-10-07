ALTER TABLE erp_person_links
    ADD COLUMN ownership_fraction_pct DECIMAL(7,4) NULL AFTER role,
    ADD CONSTRAINT chk_erp_person_links_ownership_fraction CHECK (
        ownership_fraction_pct IS NULL
        OR (
            ownership_fraction_pct > 0
            AND ownership_fraction_pct <= 100
            AND role = 'owner'
            AND unit_id IS NOT NULL
        )
    );
