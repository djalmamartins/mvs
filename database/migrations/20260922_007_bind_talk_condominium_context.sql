-- Optional condominium context for a Talk contact/ticket.
-- Kept as an external ERP identifier because the condominium registry is module-owned.
ALTER TABLE talk_contacts
    ADD COLUMN condominium_id BIGINT UNSIGNED NULL AFTER tenant_id,
    ADD KEY talk_contacts_condominium (tenant_id,condominium_id);

ALTER TABLE talk_tickets
    ADD COLUMN condominium_id BIGINT UNSIGNED NULL AFTER tenant_id,
    ADD KEY talk_tickets_condominium (tenant_id,condominium_id,status);
