# Talk: upgrade do schema legado de 2026-09-22

O banco `moves` usado pelo XAMPP ainda contém a trilha de migrations Talk de
2026-09-22. O código atual usa a trilha `20260926_011`–`20260927_013`.
As duas trilhas criam parte das mesmas tabelas e colunas com estruturas
diferentes. Elas não podem ser aplicadas uma sobre a outra diretamente.

## Evidência em 2026-09-27

- `migrations` registra `20260922_001_add_talk_tenant_context.sql`, mas não
  registra `20260926_011_create_talk_multitenancy.sql`.
- `talk_tenant_users` tem `tenant_id,user_id,role,status`, sem `is_default`.
- `talk_presence`, `talk_messages` e várias outras tabelas não têm `tenant_id`.
- O tenant existente tem slug `principal` e ID 1. A migration nova criaria
  outro tenant `moves` e o escolheria como padrão para os usuários, ocultando
  os atendimentos existentes.
- Um clone descartável do banco falhou em `20260926_011` com
  `Unknown column 'is_default' in 'field list'`. A migration SQL já havia
  executado instruções anteriores, pois DDL MySQL não é transacional.

O runner agora recusa essa combinação **antes** de executar qualquer
migration pendente. Isso evita uma segunda aplicação parcial; não atualiza o
schema nem corrige o 500 por si só.

## Critérios para o upgrade

1. Fazer backup verificável e criar clone descartável do banco antes de
   qualquer alteração no banco em uso.
2. Construir migração de reconciliação que preserve IDs, tenant `principal`,
   membros, canais e todos os atendimentos, mensagens e vínculos existentes.
   Não editar migration já aplicada nem marcar `011` como concluída sem
   demonstrar equivalência estrutural e de dados.
3. No clone, aplicar a reconciliação e as migrations seguintes. Comparar
   contagens, chaves estrangeiras, isolamento por tenant e associação dos
   registros antes/depois. Executar PHPUnit, PHPStan, lint, auditorias e smoke
   HTTP autenticado do Talk.
4. Planejar janela de manutenção, backup e rollback. Só aplicar no banco XAMPP
   após a mesma validação e uma revisão do resultado.

Rastreamento: [issue #170](https://github.com/djalmamartins/mvs/issues/170).
