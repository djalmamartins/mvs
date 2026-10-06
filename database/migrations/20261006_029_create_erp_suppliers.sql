CREATE TABLE erp_supplier_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(110) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_supplier_categories_administrator_id (administrator_id, id),
    UNIQUE KEY uq_erp_supplier_categories_slug (administrator_id, slug),
    CONSTRAINT fk_erp_supplier_categories_administrator FOREIGN KEY (administrator_id)
        REFERENCES erp_administrators(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_supplier_categories_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE erp_suppliers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    person_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_suppliers_administrator_id (administrator_id, id),
    UNIQUE KEY uq_erp_suppliers_person (administrator_id, person_id),
    KEY idx_erp_suppliers_category (administrator_id, category_id, status),
    CONSTRAINT fk_erp_suppliers_administrator FOREIGN KEY (administrator_id)
        REFERENCES erp_administrators(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_suppliers_person FOREIGN KEY (administrator_id, person_id)
        REFERENCES erp_people(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_suppliers_category FOREIGN KEY (administrator_id, category_id)
        REFERENCES erp_supplier_categories(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_suppliers_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_suppliers_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE erp_supplier_condominiums (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    condominium_id BIGINT UNSIGNED NOT NULL,
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    ended_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_erp_supplier_condominiums_administrator_id (administrator_id, id),
    UNIQUE KEY uq_erp_supplier_condominiums_start (administrator_id, supplier_id, condominium_id, starts_at),
    KEY idx_erp_supplier_condominiums_condominium (administrator_id, condominium_id, status, ends_at),
    KEY idx_erp_supplier_condominiums_supplier (administrator_id, supplier_id, status, ends_at),
    CONSTRAINT fk_erp_supplier_condominiums_administrator FOREIGN KEY (administrator_id)
        REFERENCES erp_administrators(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_supplier_condominiums_supplier FOREIGN KEY (administrator_id, supplier_id)
        REFERENCES erp_suppliers(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_supplier_condominiums_condominium FOREIGN KEY (administrator_id, condominium_id)
        REFERENCES erp_condominiums(administrator_id, id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_supplier_condominiums_created_by FOREIGN KEY (created_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_erp_supplier_condominiums_ended_by FOREIGN KEY (ended_by_user_id)
        REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_erp_supplier_condominiums_period CHECK (ends_at IS NULL OR ends_at >= starts_at),
    CONSTRAINT chk_erp_supplier_condominiums_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO erp_supplier_categories(administrator_id,name,slug)
SELECT administrator.id,category.name,category.slug
FROM erp_administrators administrator
JOIN platform_tenant_products product ON product.tenant_id=administrator.tenant_id
  AND product.product='erp' AND product.enabled=1
CROSS JOIN (
    SELECT 'Elevadores' AS name,'elevadores' AS slug
    UNION ALL SELECT 'Limpeza','limpeza'
    UNION ALL SELECT 'Segurança','seguranca'
    UNION ALL SELECT 'Engenharia','engenharia'
    UNION ALL SELECT 'Elétrica','eletrica'
    UNION ALL SELECT 'Hidráulica','hidraulica'
    UNION ALL SELECT 'Advocacia','advocacia'
    UNION ALL SELECT 'Contabilidade','contabilidade'
    UNION ALL SELECT 'Manutenção','manutencao'
    UNION ALL SELECT 'Seguros','seguros'
) category
WHERE administrator.status='active';
