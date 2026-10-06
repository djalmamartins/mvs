-- Existing tenants received ERP entitlements during the platform entitlement
-- rollout, but some predated the ERP administrator profile. Create the
-- profile only for an active tenant with ERP enabled and an owner/admin.
-- Missing tax IDs use the same internal tenant marker as CompanyService.
INSERT IGNORE INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status)
SELECT t.id,
       COALESCE(NULLIF(t.legal_name,''),NULLIF(t.name,''),CONCAT('Administradora ',t.id)),
       NULLIF(t.name,''),
       COALESCE(NULLIF(t.tax_id,''),CONCAT('TENANT-',t.id)),
       'active'
FROM talk_tenants t
JOIN platform_tenant_products product
  ON product.tenant_id=t.id AND product.product='erp' AND product.enabled=1
WHERE t.status='active'
  AND EXISTS (
      SELECT 1
      FROM talk_tenant_users member
      JOIN platform_roles role_record
        ON role_record.id=member.role_id
       AND role_record.tenant_id=member.tenant_id
       AND role_record.slug IN ('owner','administrator')
      WHERE member.tenant_id=t.id AND member.status='active'
  )
  AND NOT EXISTS (
      SELECT 1 FROM erp_administrators existing
      WHERE existing.tenant_id=t.id
  );

-- Keep ERP access role-based and tenant-specific for these migrated tenants.
INSERT IGNORE INTO platform_role_permissions(role_id,permission_id)
SELECT role_record.id,permission.id
FROM platform_roles role_record
JOIN platform_tenant_products product
  ON product.tenant_id=role_record.tenant_id
 AND product.product='erp' AND product.enabled=1
JOIN platform_permissions permission ON permission.slug='erp.access'
WHERE role_record.slug IN ('owner','administrator');

-- Administrator scope remains a second authorization boundary after the role
-- permission and product entitlement checks.
INSERT IGNORE INTO erp_scope_grants(user_id,capability,scope_type,scope_id)
SELECT member.user_id,capability.capability,'administrator',administrator.id
FROM talk_tenant_users member
JOIN platform_roles role_record
  ON role_record.id=member.role_id
 AND role_record.tenant_id=member.tenant_id
 AND role_record.slug IN ('owner','administrator')
JOIN erp_administrators administrator
  ON administrator.tenant_id=member.tenant_id
 AND administrator.status='active'
JOIN platform_tenant_products product
  ON product.tenant_id=member.tenant_id
 AND product.product='erp' AND product.enabled=1
CROSS JOIN (
    SELECT 'erp.cadastros.read' AS capability
    UNION ALL SELECT 'erp.cadastros.write'
) capability
WHERE member.status='active';
