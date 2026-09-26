# 04 — Filas e distribuição

## Modelo encontrado no uTalk

- **Setor** organiza conversas e restringe acesso.
- **Atendente** pode estar online; o painel “Agora” conta membros online.
- **Grupo** (Enterprise) associa objetos e permissões organizacionais.
- **Reatribuição** é configurada por atendente e possui consequência global: deixar disponível ou iniciar chatbot.
- **Espera** (Enterprise) dispara chatbot quando o atendente demora, permitindo notificação ou transferência.
- **Horário** (Enterprise) define janelas e resposta fora do expediente.
- **Transferência** pode listar todos os destinos ou apenas os acessíveis ao atendente.

## Fluxo conceitual

```text
entrada por canal
→ bot/horário decide mensagem inicial
→ setor aplicável
→ espera
→ atendente elegível assume ou recebe atribuição
→ timeout/offline/inatividade
   ├── reatribuir e disponibilizar
   └── iniciar chatbot
→ transferir entre atendente/setor/canal conforme permissão
```

Não foi possível confirmar round-robin nominalmente. A interface sugere regras de disponibilidade e reatribuição, mas o algoritmo interno não está exposto.

## Moves atual

O Moves possui departamentos, filas, membros, capacidade e presença. A autoatribuição escolhe usuário online com menor carga, respeitando capacidade, em [TalkService.php](../../../app/Services/Talk/TalkService.php). Isso é “least-loaded”, não round-robin puro.

## Gaps prioritários

- `tenant_id` em todas as entidades e consultas.
- Calendário/feriados e regras fora do horário.
- Estratégia configurável (manual, least-loaded, round-robin, afinidade).
- Timeout de aceite, fallback, escalonamento e fila sem atendente.
- Concorrência testada com lock e métricas de justiça da distribuição.
- SLA por fila/prioridade e pausa de SLA fora do horário.
