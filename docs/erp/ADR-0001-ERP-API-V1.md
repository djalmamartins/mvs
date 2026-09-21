# ADR 0001 — Fundação modular do ERP e API v1

Status: aceito  
Issue: #39

## Contexto

O ERP Condominial precisa evoluir dentro do Moves sem acoplar o Core ou o Studio ao domínio condominial. A API também precisa de um contrato estável antes da criação dos cadastros e do financeiro.

## Decisão

- O domínio ERP vive sob `Moves\Modules\Erp` e usa `Moves\Boot\Modules` como ponto de registro.
- Rotas públicas do ERP usam o prefixo `/api/v1/erp`.
- Endpoints ERP exigem autenticação e permissões explícitas; o bootstrap inicial concede `erp.access` somente a `admin`.
- Controllers HTTP ficam em `Moves\Controllers\Api\V1` e devem permanecer finos.
- Regras de negócio serão implementadas em services do módulo; persistência será isolada em repositories. Controllers não devem conter SQL.
- Entrada/saída entre HTTP e domínio deve usar DTOs/objetos explícitos quando o payload deixar de ser trivial.
- Respostas de sucesso usam envelope `data`; coleções futuras usarão `data` + `meta` para paginação.
- Erros futuros devem usar envelope `error` com código estável e mensagem segura; detalhes internos não devem ser expostos.
- Filtros e ordenação devem usar allowlists por recurso.
- Operações mutáveis que possam ser repetidas por integrações deverão aceitar chave de idempotência e persistir resultado antes de responder.
- Eventos de domínio serão emitidos após persistência bem-sucedida; consumidores não poderão alterar atomicidade do caso de uso principal.

## Limites

O Core fornece infraestrutura genérica (roteamento, autenticação, resposta, configuração). O ERP depende do Core; o Core não depende de entidades condominiais. Studio pode administrar/configurar o ERP por contratos públicos, sem incorporar suas regras de negócio.

## Consequências

A evolução de administradoras, condomínios, pessoas e financeiro poderá ocorrer no módulo sem duplicar aplicação ou criar uma segunda plataforma. Mudanças incompatíveis de contrato HTTP exigirão uma nova versão de API.
