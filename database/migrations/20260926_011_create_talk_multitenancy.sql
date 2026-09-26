CREATE TABLE IF NOT EXISTS talk_tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY talk_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO talk_tenants(name,slug,status)
VALUES('Moves','moves','active');

SET @talk_default_tenant := (SELECT id FROM talk_tenants WHERE slug='moves' LIMIT 1);

CREATE TABLE IF NOT EXISTS talk_tenant_users (
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'agent',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id,user_id),
    KEY talk_tenant_users_user (user_id,status,is_default),
    CONSTRAINT talk_tenant_users_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE,
    CONSTRAINT talk_tenant_users_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO talk_tenant_users(tenant_id,user_id,role,status,is_default)
SELECT @talk_default_tenant,id,IF(role='admin','admin','agent'),'active',1 FROM users;

ALTER TABLE talk_departments ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_queues ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_queue_members ADD COLUMN tenant_id BIGINT UNSIGNED NULL FIRST;
ALTER TABLE talk_contacts ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id, ADD COLUMN channel_id BIGINT UNSIGNED NULL AFTER tenant_id;
ALTER TABLE talk_conversations ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id, ADD COLUMN channel_id BIGINT UNSIGNED NULL AFTER tenant_id;
ALTER TABLE talk_tickets ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id, ADD COLUMN channel_id BIGINT UNSIGNED NULL AFTER tenant_id;
ALTER TABLE talk_messages ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id, ADD COLUMN channel_id BIGINT UNSIGNED NULL AFTER tenant_id;
ALTER TABLE talk_transfers ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_notes ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_tags ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_ticket_tags ADD COLUMN tenant_id BIGINT UNSIGNED NULL FIRST;
ALTER TABLE talk_events ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_presence ADD COLUMN tenant_id BIGINT UNSIGNED NULL FIRST;
ALTER TABLE talk_jack_interactions ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_settings ADD COLUMN tenant_id BIGINT UNSIGNED NULL FIRST;
ALTER TABLE talk_channels
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN external_id VARCHAR(190) NULL AFTER name,
    ADD COLUMN driver VARCHAR(40) NOT NULL DEFAULT 'null' AFTER external_id,
    ADD COLUMN phone_number VARCHAR(40) NULL AFTER driver,
    ADD COLUMN connection_status VARCHAR(30) NOT NULL DEFAULT 'disconnected' AFTER status,
    ADD COLUMN session_key VARCHAR(190) NULL AFTER connection_status,
    ADD COLUMN last_error VARCHAR(500) NULL AFTER last_connected_at;
ALTER TABLE talk_user_settings ADD COLUMN tenant_id BIGINT UNSIGNED NULL FIRST;
ALTER TABLE talk_attachments ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE talk_notifications ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id;

UPDATE talk_departments SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_queues SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_contacts SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_settings SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_channels SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_channels SET external_id=CONCAT(type,'-default'),driver=IF(type='whatsapp','baileys','simulation') WHERE external_id IS NULL;
INSERT INTO talk_channels(tenant_id,type,name,external_id,driver,status,connection_status,config)
SELECT @talk_default_tenant,c.channel,CONCAT('Legado ',c.channel),CONCAT('legacy-',@talk_default_tenant,'-',LOWER(REPLACE(c.channel,' ','-'))),'null','active','disconnected',JSON_OBJECT('migrated',true)
FROM talk_contacts c
LEFT JOIN talk_channels ch ON ch.tenant_id=@talk_default_tenant AND ch.type=c.channel
WHERE ch.id IS NULL
GROUP BY c.channel;
UPDATE talk_tags SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_presence SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_user_settings SET tenant_id=@talk_default_tenant WHERE tenant_id IS NULL;
UPDATE talk_contacts c INNER JOIN talk_channels ch ON ch.tenant_id=c.tenant_id AND ch.type=c.channel SET c.channel_id=ch.id WHERE c.channel_id IS NULL;
UPDATE talk_conversations cv INNER JOIN talk_contacts c ON c.id=cv.contact_id SET cv.tenant_id=c.tenant_id WHERE cv.tenant_id IS NULL;
UPDATE talk_conversations cv INNER JOIN talk_contacts c ON c.id=cv.contact_id SET cv.channel_id=c.channel_id WHERE cv.channel_id IS NULL;
UPDATE talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id SET t.tenant_id=cv.tenant_id WHERE t.tenant_id IS NULL;
UPDATE talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id SET t.channel_id=cv.channel_id WHERE t.channel_id IS NULL;
UPDATE talk_ticket_tags tt INNER JOIN talk_tickets t ON t.id=tt.ticket_id SET tt.tenant_id=t.tenant_id WHERE tt.tenant_id IS NULL;
UPDATE talk_messages m INNER JOIN talk_conversations cv ON cv.id=m.conversation_id SET m.tenant_id=cv.tenant_id WHERE m.tenant_id IS NULL;
UPDATE talk_messages m INNER JOIN talk_conversations cv ON cv.id=m.conversation_id SET m.channel_id=cv.channel_id WHERE m.channel_id IS NULL;
UPDATE talk_queue_members qm INNER JOIN talk_queues q ON q.id=qm.queue_id SET qm.tenant_id=q.tenant_id WHERE qm.tenant_id IS NULL;
UPDATE talk_transfers tr INNER JOIN talk_tickets t ON t.id=tr.ticket_id SET tr.tenant_id=t.tenant_id WHERE tr.tenant_id IS NULL;
UPDATE talk_notes n INNER JOIN talk_tickets t ON t.id=n.ticket_id SET n.tenant_id=t.tenant_id WHERE n.tenant_id IS NULL;
UPDATE talk_events e INNER JOIN talk_tickets t ON t.id=e.ticket_id SET e.tenant_id=t.tenant_id WHERE e.tenant_id IS NULL;
UPDATE talk_jack_interactions ji INNER JOIN talk_tickets t ON t.id=ji.ticket_id SET ji.tenant_id=t.tenant_id WHERE ji.tenant_id IS NULL;
UPDATE talk_attachments a INNER JOIN talk_tickets t ON t.id=a.ticket_id SET a.tenant_id=t.tenant_id WHERE a.tenant_id IS NULL;
UPDATE talk_notifications n LEFT JOIN talk_tickets t ON t.id=n.ticket_id SET n.tenant_id=COALESCE(t.tenant_id,@talk_default_tenant) WHERE n.tenant_id IS NULL;

ALTER TABLE talk_departments MODIFY tenant_id BIGINT UNSIGNED NOT NULL, DROP INDEX slug, ADD UNIQUE KEY talk_departments_tenant_slug (tenant_id,slug), ADD CONSTRAINT talk_departments_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_queues MODIFY tenant_id BIGINT UNSIGNED NOT NULL, DROP INDEX slug, ADD UNIQUE KEY talk_queues_tenant_slug (tenant_id,slug), ADD KEY talk_queues_tenant_status (tenant_id,status), ADD CONSTRAINT talk_queues_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_queue_members MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_queue_members_tenant_user (tenant_id,user_id,status), ADD CONSTRAINT talk_queue_members_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_contacts MODIFY tenant_id BIGINT UNSIGNED NOT NULL, MODIFY channel_id BIGINT UNSIGNED NOT NULL, DROP INDEX talk_contacts_channel_external, ADD UNIQUE KEY talk_contacts_tenant_channel_external (tenant_id,channel_id,external_id), ADD KEY talk_contacts_tenant_phone (tenant_id,phone), ADD CONSTRAINT talk_contacts_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE, ADD CONSTRAINT talk_contacts_channel_fk FOREIGN KEY (channel_id) REFERENCES talk_channels(id) ON DELETE RESTRICT;
ALTER TABLE talk_conversations MODIFY tenant_id BIGINT UNSIGNED NOT NULL, MODIFY channel_id BIGINT UNSIGNED NOT NULL, DROP INDEX talk_conversations_channel_external, ADD UNIQUE KEY talk_conversations_tenant_channel_external (tenant_id,channel_id,external_id), ADD KEY talk_conversations_tenant_status (tenant_id,status,last_message_at), ADD CONSTRAINT talk_conversations_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE, ADD CONSTRAINT talk_conversations_channel_fk FOREIGN KEY (channel_id) REFERENCES talk_channels(id) ON DELETE RESTRICT;
ALTER TABLE talk_tickets MODIFY tenant_id BIGINT UNSIGNED NOT NULL, MODIFY channel_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_tickets_tenant_status (tenant_id,status,updated_at), ADD CONSTRAINT talk_tickets_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE, ADD CONSTRAINT talk_tickets_channel_fk FOREIGN KEY (channel_id) REFERENCES talk_channels(id) ON DELETE RESTRICT;
ALTER TABLE talk_messages MODIFY tenant_id BIGINT UNSIGNED NOT NULL, MODIFY channel_id BIGINT UNSIGNED NOT NULL, DROP INDEX talk_messages_external_id, ADD UNIQUE KEY talk_messages_tenant_external_id (tenant_id,external_id), ADD KEY talk_messages_tenant_ticket (tenant_id,ticket_id,sent_at,id), ADD CONSTRAINT talk_messages_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE, ADD CONSTRAINT talk_messages_channel_fk FOREIGN KEY (channel_id) REFERENCES talk_channels(id) ON DELETE RESTRICT;
ALTER TABLE talk_transfers MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_transfers_tenant (tenant_id,created_at), ADD CONSTRAINT talk_transfers_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_notes MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_notes_tenant (tenant_id,ticket_id), ADD CONSTRAINT talk_notes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_tags MODIFY tenant_id BIGINT UNSIGNED NOT NULL, DROP INDEX slug, ADD UNIQUE KEY talk_tags_tenant_slug (tenant_id,slug), ADD CONSTRAINT talk_tags_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_ticket_tags MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_ticket_tags_tenant (tenant_id,ticket_id), ADD CONSTRAINT talk_ticket_tags_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_events MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_events_tenant (tenant_id,ticket_id,created_at), ADD CONSTRAINT talk_events_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_presence MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_presence_user_index (user_id), DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id,user_id), ADD CONSTRAINT talk_presence_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_jack_interactions MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_jack_tenant (tenant_id,created_at), ADD CONSTRAINT talk_jack_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_settings MODIFY tenant_id BIGINT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id,setting_key), ADD CONSTRAINT talk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_channels MODIFY tenant_id BIGINT UNSIGNED NOT NULL, MODIFY external_id VARCHAR(190) NOT NULL, DROP INDEX talk_channels_type_name, ADD UNIQUE KEY talk_channels_external_id (external_id), ADD UNIQUE KEY talk_channels_tenant_type_name (tenant_id,type,name), ADD KEY talk_channels_tenant_status (tenant_id,status), ADD CONSTRAINT talk_channels_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_user_settings MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_user_settings_user_index (user_id), DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id,user_id), ADD CONSTRAINT talk_user_settings_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_attachments MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_attachments_tenant (tenant_id,ticket_id), ADD CONSTRAINT talk_attachments_tenant_fk FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;
ALTER TABLE talk_notifications MODIFY tenant_id BIGINT UNSIGNED NOT NULL, ADD KEY talk_notifications_tenant_recipient (tenant_id,recipient_id,read_at), ADD CONSTRAINT talk_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES talk_tenants(id) ON DELETE CASCADE;

-- Chaves compostas impedem que um registro de um tenant referencie um recurso de outro tenant.
ALTER TABLE talk_departments ADD UNIQUE KEY talk_departments_tenant_id (tenant_id,id);
ALTER TABLE talk_queues ADD UNIQUE KEY talk_queues_tenant_id (tenant_id,id), ADD CONSTRAINT talk_queues_department_tenant_fk FOREIGN KEY (tenant_id,department_id) REFERENCES talk_departments(tenant_id,id) ON DELETE RESTRICT;
ALTER TABLE talk_channels ADD UNIQUE KEY talk_channels_tenant_id (tenant_id,id);
ALTER TABLE talk_contacts ADD UNIQUE KEY talk_contacts_tenant_id (tenant_id,id), ADD CONSTRAINT talk_contacts_channel_tenant_fk FOREIGN KEY (tenant_id,channel_id) REFERENCES talk_channels(tenant_id,id) ON DELETE RESTRICT;
ALTER TABLE talk_conversations ADD UNIQUE KEY talk_conversations_tenant_id (tenant_id,id), ADD CONSTRAINT talk_conversations_contact_tenant_fk FOREIGN KEY (tenant_id,contact_id) REFERENCES talk_contacts(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_conversations_channel_tenant_fk FOREIGN KEY (tenant_id,channel_id) REFERENCES talk_channels(tenant_id,id) ON DELETE RESTRICT;
ALTER TABLE talk_tickets ADD UNIQUE KEY talk_tickets_tenant_id (tenant_id,id), ADD CONSTRAINT talk_tickets_conversation_tenant_fk FOREIGN KEY (tenant_id,conversation_id) REFERENCES talk_conversations(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_tickets_queue_tenant_fk FOREIGN KEY (tenant_id,queue_id) REFERENCES talk_queues(tenant_id,id) ON DELETE RESTRICT, ADD CONSTRAINT talk_tickets_channel_tenant_fk FOREIGN KEY (tenant_id,channel_id) REFERENCES talk_channels(tenant_id,id) ON DELETE RESTRICT;
ALTER TABLE talk_messages ADD UNIQUE KEY talk_messages_tenant_id (tenant_id,id), ADD CONSTRAINT talk_messages_conversation_tenant_fk FOREIGN KEY (tenant_id,conversation_id) REFERENCES talk_conversations(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_messages_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_messages_channel_tenant_fk FOREIGN KEY (tenant_id,channel_id) REFERENCES talk_channels(tenant_id,id) ON DELETE RESTRICT;
ALTER TABLE talk_queue_members ADD CONSTRAINT talk_queue_members_queue_tenant_fk FOREIGN KEY (tenant_id,queue_id) REFERENCES talk_queues(tenant_id,id) ON DELETE CASCADE;
ALTER TABLE talk_transfers ADD CONSTRAINT talk_transfers_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_transfers_from_queue_tenant_fk FOREIGN KEY (tenant_id,from_queue_id) REFERENCES talk_queues(tenant_id,id) ON DELETE RESTRICT, ADD CONSTRAINT talk_transfers_to_queue_tenant_fk FOREIGN KEY (tenant_id,to_queue_id) REFERENCES talk_queues(tenant_id,id) ON DELETE RESTRICT;
ALTER TABLE talk_notes ADD CONSTRAINT talk_notes_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE;
ALTER TABLE talk_tags ADD UNIQUE KEY talk_tags_tenant_id (tenant_id,id);
ALTER TABLE talk_ticket_tags ADD CONSTRAINT talk_ticket_tags_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_ticket_tags_tag_tenant_fk FOREIGN KEY (tenant_id,tag_id) REFERENCES talk_tags(tenant_id,id) ON DELETE CASCADE;
ALTER TABLE talk_events ADD CONSTRAINT talk_events_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE;
ALTER TABLE talk_jack_interactions ADD CONSTRAINT talk_jack_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_jack_message_tenant_fk FOREIGN KEY (tenant_id,message_id) REFERENCES talk_messages(tenant_id,id) ON DELETE RESTRICT;
ALTER TABLE talk_attachments ADD CONSTRAINT talk_attachments_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE, ADD CONSTRAINT talk_attachments_message_tenant_fk FOREIGN KEY (tenant_id,message_id) REFERENCES talk_messages(tenant_id,id) ON DELETE CASCADE;
ALTER TABLE talk_notifications ADD CONSTRAINT talk_notifications_ticket_tenant_fk FOREIGN KEY (tenant_id,ticket_id) REFERENCES talk_tickets(tenant_id,id) ON DELETE CASCADE;

-- Vínculos de usuários também pertencem ao tenant, evitando atribuições e notificações cruzadas.
ALTER TABLE talk_queue_members ADD CONSTRAINT talk_queue_members_user_tenant_fk FOREIGN KEY (tenant_id,user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_tickets ADD CONSTRAINT talk_tickets_assignee_tenant_fk FOREIGN KEY (tenant_id,assigned_user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT, ADD CONSTRAINT talk_tickets_closed_by_tenant_fk FOREIGN KEY (tenant_id,closed_by) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_messages ADD CONSTRAINT talk_messages_sender_tenant_fk FOREIGN KEY (tenant_id,sender_user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_transfers ADD CONSTRAINT talk_transfers_from_user_tenant_fk FOREIGN KEY (tenant_id,from_user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT, ADD CONSTRAINT talk_transfers_to_user_tenant_fk FOREIGN KEY (tenant_id,to_user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT, ADD CONSTRAINT talk_transfers_creator_tenant_fk FOREIGN KEY (tenant_id,created_by) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_notes ADD CONSTRAINT talk_notes_user_tenant_fk FOREIGN KEY (tenant_id,user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_events ADD CONSTRAINT talk_events_user_tenant_fk FOREIGN KEY (tenant_id,user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_presence ADD CONSTRAINT talk_presence_user_tenant_fk FOREIGN KEY (tenant_id,user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE CASCADE;
ALTER TABLE talk_settings ADD CONSTRAINT talk_settings_user_tenant_fk FOREIGN KEY (tenant_id,updated_by) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_user_settings ADD CONSTRAINT talk_user_settings_user_tenant_fk FOREIGN KEY (tenant_id,user_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE CASCADE;
ALTER TABLE talk_attachments ADD CONSTRAINT talk_attachments_user_tenant_fk FOREIGN KEY (tenant_id,uploaded_by) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE RESTRICT;
ALTER TABLE talk_notifications ADD CONSTRAINT talk_notifications_user_tenant_fk FOREIGN KEY (tenant_id,recipient_id) REFERENCES talk_tenant_users(tenant_id,user_id) ON DELETE CASCADE;
