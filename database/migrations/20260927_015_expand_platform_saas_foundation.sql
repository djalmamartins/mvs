ALTER TABLE talk_tenants
    ADD COLUMN legal_name VARCHAR(190) NULL AFTER name,
    ADD COLUMN tax_id VARCHAR(14) NULL AFTER legal_name,
    ADD COLUMN email VARCHAR(190) NULL AFTER tax_id,
    ADD COLUMN phone VARCHAR(32) NULL AFTER email,
    ADD COLUMN postal_code VARCHAR(8) NULL AFTER phone,
    ADD COLUMN street VARCHAR(190) NULL AFTER postal_code,
    ADD COLUMN address_number VARCHAR(30) NULL AFTER street,
    ADD COLUMN complement VARCHAR(120) NULL AFTER address_number,
    ADD COLUMN district VARCHAR(120) NULL AFTER complement,
    ADD COLUMN city VARCHAR(120) NULL AFTER district,
    ADD COLUMN state CHAR(2) NULL AFTER city,
    ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo' AFTER state,
    ADD COLUMN settings LONGTEXT NULL AFTER timezone,
    ADD COLUMN suspended_at DATETIME NULL AFTER status,
    ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER suspended_at,
    ADD UNIQUE KEY talk_tenants_tax_id_unique (tax_id);

CREATE TABLE platform_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY platform_roles_tenant_slug (tenant_id,slug),
    CONSTRAINT platform_roles_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE platform_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE platform_role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id,permission_id),
    CONSTRAINT platform_role_permissions_role_fk FOREIGN KEY (role_id) REFERENCES platform_roles(id) ON DELETE CASCADE,
    CONSTRAINT platform_role_permissions_permission_fk FOREIGN KEY (permission_id) REFERENCES platform_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE talk_tenant_users
    ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER role,
    ADD CONSTRAINT talk_tenant_users_role_fk FOREIGN KEY (role_id) REFERENCES platform_roles(id) ON DELETE RESTRICT;

INSERT INTO platform_permissions(slug,name) VALUES
('tenant.manage','Gerenciar administradora'),
('members.manage','Gerenciar usuários e papéis'),
('products.manage','Gerenciar produtos'),
('condominiums.read','Consultar condomínios'),
('condominiums.manage','Gerenciar condomínios'),
('settings.manage','Gerenciar configurações'),
('talk.access','Acessar Talk'),
('erp.access','Acessar ERP'),
('support.access','Acessar Suporte'),
('cms.access','Acessar CMS'),
('studio.access','Acessar Studio');

INSERT INTO platform_roles(tenant_id,slug,name,is_system)
SELECT id,'owner','Proprietário',1 FROM talk_tenants;
INSERT INTO platform_roles(tenant_id,slug,name,is_system)
SELECT id,'administrator','Administrador',1 FROM talk_tenants;
INSERT INTO platform_roles(tenant_id,slug,name,is_system)
SELECT id,'supervisor','Supervisor',1 FROM talk_tenants;
INSERT INTO platform_roles(tenant_id,slug,name,is_system)
SELECT id,'agent','Atendente',1 FROM talk_tenants;
INSERT INTO platform_roles(tenant_id,slug,name,is_system)
SELECT id,'operator','Operacional',1 FROM talk_tenants;

INSERT INTO platform_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM platform_roles r CROSS JOIN platform_permissions p WHERE r.slug IN ('owner','administrator');
INSERT IGNORE INTO platform_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM platform_roles r JOIN platform_permissions p ON p.slug IN
('condominiums.read','talk.access','erp.access','support.access') WHERE r.slug='supervisor';
INSERT IGNORE INTO platform_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM platform_roles r JOIN platform_permissions p ON p.slug IN
('talk.access','support.access') WHERE r.slug='agent';
INSERT IGNORE INTO platform_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM platform_roles r JOIN platform_permissions p ON p.slug IN
('condominiums.read','erp.access') WHERE r.slug='operator';

UPDATE talk_tenant_users m
JOIN platform_roles r ON r.tenant_id=m.tenant_id AND r.slug=CASE
    WHEN m.role IN ('owner','admin','administrator') THEN IF(m.is_default=1,'owner','administrator')
    WHEN m.role IN ('supervisor') THEN 'supervisor'
    WHEN m.role IN ('agent','member') THEN 'agent'
    ELSE 'operator' END
SET m.role_id=r.id;
ALTER TABLE talk_tenant_users MODIFY role_id BIGINT UNSIGNED NOT NULL;

-- Existing installations exposed every product before entitlements became
-- authoritative. Preserve that access during the upgrade; new tenants choose
-- their products explicitly during onboarding.
INSERT IGNORE INTO platform_tenant_products(tenant_id,product,enabled)
SELECT t.id,p.product,1
FROM talk_tenants t
CROSS JOIN (
    SELECT 'talk' product UNION ALL SELECT 'erp' UNION ALL SELECT 'support'
    UNION ALL SELECT 'cms' UNION ALL SELECT 'studio'
) p;

CREATE TABLE platform_tenant_settings (
    tenant_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value LONGTEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id,setting_key),
    CONSTRAINT platform_tenant_settings_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE platform_audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    subject_type VARCHAR(80) NULL,
    subject_id BIGINT UNSIGNED NULL,
    metadata LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY platform_audit_tenant_created (tenant_id,created_at),
    KEY platform_audit_actor_created (actor_user_id,created_at),
    CONSTRAINT platform_audit_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE erp_condominiums
    ADD COLUMN email VARCHAR(190) NULL AFTER tax_id,
    ADD COLUMN phone VARCHAR(32) NULL AFTER email,
    ADD COLUMN postal_code VARCHAR(8) NULL AFTER phone,
    ADD COLUMN street VARCHAR(190) NULL AFTER postal_code,
    ADD COLUMN address_number VARCHAR(30) NULL AFTER street,
    ADD COLUMN complement VARCHAR(120) NULL AFTER address_number,
    ADD COLUMN district VARCHAR(120) NULL AFTER complement,
    ADD COLUMN city VARCHAR(120) NULL AFTER district,
    ADD COLUMN state CHAR(2) NULL AFTER city,
    ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER timezone;
