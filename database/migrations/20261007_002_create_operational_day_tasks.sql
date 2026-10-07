ALTER TABLE erp_condominiums
    MODIFY tax_id VARCHAR(20) NULL;

ALTER TABLE day_tasks
    DROP FOREIGN KEY day_tasks_assignee,
    MODIFY assigned_user_id BIGINT UNSIGNED NULL,
    ADD COLUMN automation_key VARCHAR(190) NULL AFTER source_id,
    ADD UNIQUE KEY day_tasks_tenant_automation (tenant_id, automation_key),
    ADD CONSTRAINT day_tasks_assignee_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL;

INSERT IGNORE INTO platform_permissions(slug,name)
VALUES ('erp.pending.manage','Gerenciar pendências operacionais do ERP');

INSERT IGNORE INTO platform_role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM platform_roles r
JOIN platform_permissions p ON p.slug='erp.pending.manage'
WHERE r.slug IN ('owner','administrator','supervisor');
