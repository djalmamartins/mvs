-- Make public Talk protocols unique inside each administradora instead of globally.
-- This allows the agreed temporary TK-YYYY-NNNNNNN-00 format to coexist across tenants.
ALTER TABLE talk_tickets
    DROP INDEX protocol,
    ADD UNIQUE KEY talk_tickets_tenant_protocol (tenant_id, protocol);
