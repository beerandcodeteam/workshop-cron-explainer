---
paths:
  - '**'
  - phpunit.xml
---

# General

## Aplicação stateless: nada de banco de domínio
O Cron Explainer não persiste nada. database/migrations/ tem apenas os três arquivos 0001_01_01_* do starter kit; as tabelas herdadas (users, sessions, cache, jobs, ...) não são lidas nem escritas. A migration do laravel/ai (agent_conversations) NÃO foi publicada nem executada — só seria necessária se o agente passasse a usar memória de conversa. tests/Pest.php não usa RefreshDatabase: a suíte roda sem arquivo SQLite. Não adicione models, migrations ou RefreshDatabase sem aprovação.

## A suíte roda com OPENAI_API_KEY zerada à força
O phpunit.xml tem `<env name="OPENAI_API_KEY" value="" force="true"/>`. Sem isso, a chave real do .env (ou uma exportada no shell) chega aos testes, e qualquer chamada que escapasse de um `CronExplainer::fake()` viraria requisição paga de verdade contra a API da OpenAI. O `force="true"` é necessário: sem ele o PHPUnit não sobrescreve variável já presente no ambiente.

tests/Feature/AmbienteDeTesteTest.php trava esse invariante. Não remova nenhum dos dois.
