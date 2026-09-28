# ERP — Pessoas e vínculos temporais

A issue #43 usa uma pessoa canônica em `erp_people` e vínculos temporais em `erp_person_links`.

## Regras

- A criação de pessoa ocorre pelo `PeopleService` e exige `erp.people.write` no escopo do condomínio.
- Documento é normalizado antes da deduplicação; quando informado, `document_type + document_number` identifica a pessoa canônica.
- Papéis suportados: proprietário, inquilino, morador, síndico, conselho e procurador.
- Cada vínculo pertence a um condomínio e pode apontar apenas para uma unidade do mesmo condomínio.
- `starts_at` e `ends_at` usam o formato estrito `YYYY-MM-DD`; o fim nunca pode anteceder o início.
- O encerramento altera o vínculo para `inactive`, preservando o registro para histórico e timeline.
- A timeline exige `erp.people.read`; criação e encerramento exigem `erp.people.write`.
- Criação/reuso de pessoa, criação de vínculo e encerramento podem ser registrados no audit log append-only.

## Validação

`ErpPeopleServiceTest` cobre deduplicação, autorização por escopo, isolamento de unidade entre condomínios, vigência, encerramento, preservação do histórico, timeline e rejeição de datas inválidas.

Quality Gate validado com migrations, PHPUnit e PHPStan.
