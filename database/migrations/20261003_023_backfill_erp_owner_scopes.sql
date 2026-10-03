INSERT IGNORE INTO erp_scope_grants (user_id, capability, scope_type, scope_id)
SELECT member.user_id, capabilities.capability, 'administrator', administrator.id
FROM talk_tenant_users member
INNER JOIN platform_roles role_record
    ON role_record.id = member.role_id
    AND role_record.tenant_id = member.tenant_id
    AND role_record.slug IN ('owner', 'administrator')
INNER JOIN erp_administrators administrator
    ON administrator.tenant_id = member.tenant_id
    AND administrator.status = 'active'
CROSS JOIN (
    SELECT 'erp.cadastros.read' AS capability
    UNION ALL
    SELECT 'erp.cadastros.write'
) capabilities
WHERE member.status = 'active';
