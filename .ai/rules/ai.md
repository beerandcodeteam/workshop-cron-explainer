---
paths:
  - 'app/Ai/**'
---

# Ai

## CronExplainer implementa só Agent + HasStructuredOutput
O stub de `make:agent --structured` gera a classe implementando também `Conversational` e `HasTools` — remova os dois. O agente é stateless: não há memória de conversa (a migration `agent_conversations` do laravel/ai nunca foi publicada) nem tools.

O modelo NÃO vem de `#[Model]`; só `#[Provider(Lab::OpenAI)]` é anotado. O identificador do modelo é passado em tempo de execução no argumento `model:` de `prompt()`, lido de `config('cron-explainer.model')`.

Contrato de `schema()`: quatro chaves, todas `required` — `valido` (boolean), `explicacao` (string), `erro` (string), `proximas_execucoes` (array de string). Campos não aplicáveis vêm vazios (`''` / `[]`), nunca ausentes nem `null`.
