# 13 — Comparativo uTalk × Moves Talk

Legenda do Moves atual: **Sim**, **Parcial**, **Não encontrado**.

| Recurso | uTalk | Moves Talk atual | Moves Talk planejado | Gap | Recomendação |
|---|---|---|---|---|---|
| Inbox/filas | Sim | Sim | Sim | UX duplicada | consolidar workspace |
| Assumir/transferir/devolver/finalizar/reabrir | Sim | Sim | Sim | testes/escopo | endurecer autorização e concorrência |
| WhatsApp Web | Sim | Parcial (Baileys) | Sim | operação resiliente | experimental fora de produção crítica |
| WhatsApp Oficial | Sim | Parcial (adapter Meta) | Sim | onboarding, templates, janela | priorizar Cloud API |
| Múltiplos números | Sim | Não comprovado | Sim | entidade número/canal | modelar por tenant |
| Contatos e histórico | Sim | Parcial | Sim | identidade/vínculo ERP | criar pessoa + identidades + vínculos |
| Notas/tags/prioridade/anexos | Sim | Sim | Sim | refinamentos | manter e testar |
| Respostas rápidas/variáveis | Sim | Não encontrado | Implícito | grande | V1 para produtividade |
| Templates/Flows/campanhas | Sim | Não encontrado | Parcial | grande | templates V1; Flows/campanhas depois |
| Agendamentos | Sim | Não encontrado | Não explícito | médio | V1.1, com consentimento |
| Chatbot visual | Sim | Não encontrado | Jack | produto diferente | não copiar editor na V1 |
| IA/RAG | Sim | Jack básico | Sim | ferramentas, base, avaliações | foco no contexto ERP |
| Sugestão/melhoria de resposta | Sim | Não encontrado | Não explícito | oportunidade | V1.1 após segurança |
| Supervisão em tempo real | Sim | Parcial | Sim | KPIs e escopo | painel Agora V1 |
| Relatórios | Rico | Básico | Sim | percentis/SLA/filtros | eventos + projeções |
| Avaliação | Sim | Não encontrado | Não explícito | médio | V1.1 |
| Papéis e permissões | Sim | Parcial | Sim | tenant/escopo/middleware | crítico antes de produção |
| Webhooks/API | Sim | Inbound pontual | Futuro | grande | API interna estável + webhooks V1.1 |
| Auditoria | Sim | Eventos do ticket/Jack | Sim | cobertura/imutabilidade | ampliar para config e ERP |
| Multiempresa | Organizações | Não no domínio Talk | Sim | crítico | bloquear V1 até resolver |
| ERP/condomínios/unidades | Não nativo | Não no Talk | Sim | integração | principal diferencial Moves |
| Privacidade/LGPD | Enterprise | Não encontrado no Talk | Necessário | crítico | consentimento, retenção, exportação |

## Evidências do Moves

- Rotas: [Routes.php](../../../app/Boot/Routes.php)
- Núcleo: [TalkService.php](../../../app/Services/Talk/TalkService.php)
- Entrada/saída: [TalkInboundService.php](../../../app/Services/Talk/TalkInboundService.php), [TalkOutboundService.php](../../../app/Services/Talk/TalkOutboundService.php)
- Banco: [migrações Talk](../../../database/migrations)
- Interface: [talk-inbox.php](../../../resources/themes/admin/pages/talk-inbox.php), [talk-ticket.php](../../../resources/themes/admin/pages/talk-ticket.php)
