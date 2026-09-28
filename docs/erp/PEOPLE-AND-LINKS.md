# ERP — Pessoas e vínculos temporais

A issue #43 usa uma pessoa canônica em `erp_people` e vínculos temporais em `erp_person_links`.

## Regras

- Documento é normalizado antes da deduplicação; quando informado, `document_type + document_number` identifica a pessoa.
- Papéis suportados inicialmente: proprietário, inquilino, morador, síndico, conselho e procurador.
- Cada vínculo pertence a um condomínio e pode apontar para uma unidade do mesmo condomínio.
- `starts_at` e `ends_at` preservam vigência e histórico; vínculos antigos não são sobrescritos.
- Leitura e escrita exigem `erp.people.read` / `erp.people.write` no escopo do condomínio.
- Criação de vínculo autorizado pode ser registrada no audit log append-only de segurança.

## Validação

`ErpPeopleServiceTest` cobre deduplicação de documento, autorização por escopo e rejeição de unidade de outro condomínio.
