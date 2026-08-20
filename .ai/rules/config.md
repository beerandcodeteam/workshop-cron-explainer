---
paths:
  - 'config/**'
---

# Config

## Modelo e timezone vêm de config/cron-explainer.php
O identificador do modelo fica em config('cron-explainer.model') (env CRON_EXPLAINER_MODEL) — nunca hardcoded no atributo #[Model] do agente, para poder ser trocado por ambiente. A exibição de datas usa exclusivamente config('cron-explainer.timezone') (America/Sao_Paulo); config('app.timezone') permanece UTC, o padrão do framework. occurrences é fixo em 5. A chave do provider vem de OPENAI_API_KEY via config/ai.php.
