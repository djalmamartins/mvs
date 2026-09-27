# Migrations — operação e recuperação

As migrations do Moves são executadas por `php scripts/migrate.php` em ordem lexical. O runner usa o lock MySQL `moves:migrations` para impedir execução concorrente e mantém o ledger `migrations`; arquivos já registrados são ignorados.

## Deploy / upgrade

1. Faça backup do banco antes de qualquer upgrade de produção.
2. Execute `php scripts/migrate.php` uma única vez no artefato que será promovido.
3. Confirme a saída `Migrations concluídas.` e revise as linhas `OK:`/`SKIP:`.
4. Execute novamente em homologação para confirmar idempotência; migrations já registradas devem aparecer como `SKIP:`.
5. Só promova o release com CI verde. O CI valida banco limpo, reexecução, upgrade incremental e falha controlada.

## Falha

O runner somente grava uma migration no ledger depois que o SQL termina com sucesso. Se uma migration falhar, o processo retorna erro e o arquivo não é registrado como aplicado. Não edite nem reescreva migrations históricas para contornar a falha.

Para recuperar: interrompa a promoção, preserve logs e o banco afetado, identifique o último item presente no ledger e compare com o diretório versionado. Se o DDL do banco tiver sido parcialmente aplicado (MySQL pode fazer commit implícito em DDL), restaure o backup ou produza uma migration corretiva aditiva depois de validar o estado real. Nunca marque manualmente uma migration como executada sem comprovar o schema.

## Concorrência

Uma segunda execução não deve prosseguir enquanto `moves:migrations` estiver adquirido. Falha em obter o lock é tratada como erro, evitando dois deploys alterando o schema simultaneamente.
