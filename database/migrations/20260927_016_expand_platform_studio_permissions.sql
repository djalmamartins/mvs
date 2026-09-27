INSERT IGNORE INTO platform_permissions(slug,name) VALUES
('profile.view','Consultar perfil'),
('users.manage','Gerenciar usuários globais'),
('diagnostics.view','Consultar diagnósticos'),
('studio.dashboard','Acessar painel do Studio'),
('studio.search','Pesquisar no Studio'),
('content.manage','Gerenciar conteúdo'),
('media.manage','Gerenciar mídia'),
('proposals.manage','Gerenciar propostas'),
('notifications.manage','Gerenciar notificações'),
('reports.view','Consultar relatórios'),
('logs.manage','Gerenciar logs');

INSERT IGNORE INTO platform_role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM platform_roles r
CROSS JOIN platform_permissions p
WHERE r.slug IN ('owner','administrator');
