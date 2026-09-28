# ERP — Estrutura física
A issue #42 introduz blocos/torres, unidades, fração ideal, vagas e áreas comuns por condomínio.
O boundary `PhysicalStructureService` exige grants `erp.structure.read` e `erp.structure.write` no escopo do condomínio.
Unidades não podem referenciar bloco de outro condomínio e a fração ideal aceita valores de 0 a 1.
A importação em lote processa linhas independentemente e devolve erros por linha, sem atravessar o tenant.
Validação automatizada: `tests/Unit/ErpPhysicalStructureServiceTest.php`.
