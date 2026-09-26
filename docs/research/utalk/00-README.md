# Auditoria uTalk → Moves Talk

Auditoria realizada em 26/09/2026 sobre a organização **Connect**, na versão exibida `2026.9.25.2223 - wasm`, e confrontada com o código atual deste repositório.

## Escopo e método

- Navegação autenticada e estritamente de leitura por todos os menus principais e configurações acessíveis.
- Nenhuma mensagem foi enviada; nenhuma configuração, canal, contato ou campanha foi criado, alterado ou excluído.
- A API pública acessível na própria aplicação foi usada como evidência complementar de capacidades.
- O código, as migrações, rotas e telas do Moves Talk foram inspecionados localmente.

## Limitação da conta

A organização estava com acesso pendente e sem assinatura ativa. Vários botões estavam desabilitados e recursos Enterprise apareciam apenas como apresentação comercial. Assim, esta documentação distingue:

- **Confirmado**: comportamento ou estrutura visível na interface/API.
- **Indicado**: recurso descrito pela interface, mas bloqueado pelo plano.
- **Não verificado**: fluxo que exigiria criar/alterar dados ou uma conta ativa.

Não foi possível validar com conversa real: composição completa, janela de 24 horas, execução de chatbot, roteamento sob carga, SLA em produção, exportações e responsividade móvel.

## Índice

1. [Mapa da plataforma](01-MAPA-PLATAFORMA.md)
2. [Atendimento](02-ATENDIMENTO.md)
3. [WhatsApp e canais](03-WHATSAPP-CANAIS.md)
4. [Filas e distribuição](04-FILAS-DISTRIBUICAO.md)
5. [Contatos](05-CONTATOS.md)
6. [Automações, chatbot e IA](06-AUTOMACOES-CHATBOT-IA.md)
7. [Supervisão](07-SUPERVISAO.md)
8. [Usuários e permissões](08-USUARIOS-PERMISSOES.md)
9. [Dashboard e métricas](09-DASHBOARD-METRICAS.md)
10. [Configurações](10-CONFIGURACOES.md)
11. [Integrações e API](11-INTEGRACOES-API.md)
12. [UX/UI](12-UX-UI.md)
13. [Comparativo](13-COMPARATIVO-UTALK-MOVES-TALK.md)
14. [Gaps](14-GAPS-MOVES-TALK.md)
15. [Recomendações V1](15-RECOMENDACOES-V1.md)
16. [Roadmap](16-ROADMAP.md)

## Síntese executiva

O uTalk combina inbox omnicanal, CRM leve, automação e supervisão. Seus conceitos mais úteis para o Moves são: inbox em três painéis, visibilidade por função, configuração explícita de transferência/reatribuição, painel “Agora”, respostas rápidas com variáveis, histórico operacional e bases de conhecimento.

O Moves Talk já possui o núcleo transacional de atendimento, mas precisa priorizar isolamento multiempresa, autorização em todas as rotas, modelo de canal/número, resiliência do transporte, observabilidade, políticas de janela/template e testes. A vantagem defensável não é replicar um help desk genérico: é fornecer ao Jack e ao atendente contexto nativo de condomínio, unidade, morador, financeiro, chamados e documentos.
