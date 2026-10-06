# ERP — Condomínios e fornecedores

## Condomínios canônicos

`erp_condominiums` já é o cadastro canônico consumido por `CondominiumService`, pessoas/vínculos, blocos/unidades e contexto da administradora. As telas novas consultam esse mesmo registro; não existe uma segunda tabela de condomínio nem sincronização paralela. `CondominiumService::save()` segue como caminho de criação/edição, com tenant, auditoria e validação de CNPJ.

## Identidade de fornecedor

Fornecedor não é uma segunda identidade. `erp_people` continua guardando uma pessoa física ou jurídica uma única vez por administradora. `erp_suppliers` é a qualificação operacional ligada à pessoa e à categoria; o índice único `(administrator_id, person_id)` impede qualificar a mesma pessoa duas vezes. CPF/CNPJ já usa a unicidade por administradora em `erp_people` e novos cadastros validam os dígitos verificadores.

O modelo antigo de `feat/mvs-erp` (`erp_parties`) não foi integrado: ele replica nome/documento/contato do fornecedor e o prende a um condomínio na própria entidade, não oferece reutilização de pessoa PF/PJ e mantém contratos/reviews/banco no mesmo agregado. Os relacionamentos de fornecedor desta etapa são explícitos e tenant-scoped.

## Categoria e atendimento

`erp_supplier_categories` é uma tabela configurável por administradora, sem enum fixo no schema. A migration semeia categorias iniciais para tenants com ERP ativo; o serviço também garante os valores padrão de forma idempotente para administradoras criadas depois. Novas categorias customizadas podem ser adicionadas sem alterar código ou migration.

`erp_supplier_condominiums` representa o atendimento N:N e guarda início, encerramento, estado e autores. Chaves estrangeiras compostas garantem que pessoa, fornecedor, categoria e condomínio pertençam à mesma administradora. Não há conta bancária do fornecedor nesta etapa.

## Próximo marco financeiro

Contas a pagar poderá referenciar `erp_suppliers.id` e `erp_condominiums.id`, com o tenant/administradora como parte da identidade. Nenhuma tabela ou dado financeiro fictício foi criado neste bloco.
