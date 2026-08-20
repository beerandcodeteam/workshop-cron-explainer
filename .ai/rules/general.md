---
paths:
  - '**'
---

# General

## Aplicação stateless: nada de banco de domínio
O Cron Explainer não persiste nada. database/migrations/ tem apenas os três arquivos 0001_01_01_* do starter kit; as tabelas herdadas (users, sessions, cache, jobs, ...) não são lidas nem escritas. A migration do laravel/ai (agent_conversations) NÃO foi publicada nem executada — só seria necessária se o agente passasse a usar memória de conversa. tests/Pest.php não usa RefreshDatabase: a suíte roda sem arquivo SQLite. Não adicione models, migrations ou RefreshDatabase sem aprovação.
