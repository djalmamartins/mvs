# Moves v1 — Hardening do contato público

## Objetivo
Evitar abuso, spam e duplicidade sem degradar a experiência normal do formulário público.

## Contrato mínimo
- validação server-side de nome, e-mail, telefone, assunto e mensagem;
- honeypot invisível para usuários;
- rate limit dedicado por IP + fingerprint de sessão;
- chave de idempotência por submissão;
- janela curta de deduplicação por hash normalizado;
- telemetria sem registrar conteúdo sensível integral;
- resposta genérica para bloqueios de abuso.

## Regras propostas
- Honeypot preenchido: descartar e responder de forma neutra.
- Rate limit inicial: 5 envios por 10 minutos por IP, ajustável por configuração.
- Idempotência: aceitar `Idempotency-Key` e persistir resultado por 24h.
- Deduplicação: hash SHA-256 de e-mail normalizado + telefone normalizado + mensagem normalizada em janela curta.
- Validação: rejeitar payloads excessivos antes da persistência.
- Logs: registrar timestamp, origem anonimizada/hash, regra acionada e correlation id; nunca senha, token ou mensagem integral.

## Testes obrigatórios
1. envio válido;
2. e-mail inválido;
3. payload excessivo;
4. honeypot preenchido;
5. repetição dentro da janela;
6. mesma chave de idempotência repetida;
7. estouro de rate limit;
8. envio válido após expiração da janela.

## Critério de pronto
A issue #20 só deve ser fechada quando as regras acima estiverem implementadas na rota real de contato e cobertas por testes positivos/negativos.
