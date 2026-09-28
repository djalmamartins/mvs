# ERP Changelog

## 2026-09-28

### #43 — Pessoas e vínculos temporais

- pessoa canônica com deduplicação por documento normalizado;
- criação protegida por escopo de condomínio;
- vínculos proprietário, inquilino, morador, síndico, conselho e procurador;
- vigência com datas validadas e encerramento sem perda de histórico;
- isolamento entre unidades de condomínios diferentes;
- timeline protegida por permissão de leitura;
- eventos de criação/reuso, vínculo e encerramento preparados para auditoria append-only;
- cobertura automatizada validada por PHPUnit e PHPStan.
