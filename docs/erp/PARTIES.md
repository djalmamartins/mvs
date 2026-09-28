# ERP — Fornecedores, funcionários e terceiros

A issue #44 centraliza fornecedores, funcionários e terceiros em `erp_parties`, sempre vinculados ao condomínio.

- Tipos iniciais: `supplier`, `employee`, `contractor`.
- Contratos/vínculos possuem vigência e histórico em `erp_party_links`.
- Avaliações são append-only em `erp_party_reviews`, com nota de 1 a 5.
- Leitura/escrita usam `erp.parties.read/write`; avaliações usam `erp.parties.review.write`.
- Dados bancários exigem `erp.parties.bank.write`, são serializados e cifrados em AES-256-GCM com chave externa versionada.
- Auditoria registra somente identificadores/metadados operacionais, nunca o conteúdo bancário.
