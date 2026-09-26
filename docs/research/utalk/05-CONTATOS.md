# 05 — Contatos

## uTalk

A área de contatos declara gestão de informações e acesso ao histórico. A API confirma:

- criação manual e automática; busca paginada e filtros;
- nome, telefone e perfil; localização por telefone;
- conversas atuais e anteriores do contato;
- notas criadas, fixadas e removidas;
- tags individuais ou em lote;
- campos personalizados e agregações;
- bloqueio do contato;
- verificação se o número existe no WhatsApp usando canais disponíveis;
- exclusão com opção de fechar conversas abertas ou apagar conversas;
- Boards de contatos com colunas, funcionando como CRM/Kanban.

Importação/exportação não foi confirmada na interface acessível.

## Relação correta para o Moves

```text
Pessoa/Contato
├── identidades de canal (telefone, WhatsApp, e-mail)
├── vínculos ERP
│   ├── administradora
│   ├── condomínio
│   ├── unidade
│   └── papel: morador, proprietário, síndico, fornecedor
├── conversas
└── consentimentos, bloqueios, tags, notas e auditoria
```

## Estado do Moves

`talk_contacts` guarda nome, telefone, e-mail, `external_id`, canal e JSON de metadados. A entrada WhatsApp cria contato por telefone. O modelo ainda não resolve múltiplas identidades, duplicidade entre canais, consentimento/LGPD nem vínculo relacional ao ERP.

## Recomendação

Não adotar Boards na V1. Priorizar identidade canônica, merge seguro de duplicados, vínculos ERP e linha do tempo única. Um mesmo telefone pode representar várias unidades/condomínios; o vínculo deve ser confirmado pelo contexto e auditado.
