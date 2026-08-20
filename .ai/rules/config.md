---
paths:
  - 'config/**'
---

# Config

## Modelo e timezone vêm de config/cron-explainer.php
O identificador do modelo fica em config('cron-explainer.model') (env CRON_EXPLAINER_MODEL) — nunca hardcoded no atributo #[Model] do agente, para poder ser trocado por ambiente. A exibição de datas usa exclusivamente config('cron-explainer.timezone') (America/Sao_Paulo); config('app.timezone') permanece UTC, o padrão do framework. occurrences é fixo em 5. A chave do provider vem de OPENAI_API_KEY via config/ai.php.

## O 401 da OpenAI grava a API key em storage/logs/laravel.log
Verificado em runtime na Phase 4.2: quando a chave é recusada, a OpenAI devolve 401 com a mensagem "Incorrect API key provided: sk-proj-...", e o `Log::error(..., ['exception' => $e])` do SFC grava essa mensagem — chave inteira incluída — em storage/logs/laravel.log.

A página não vaza nada (mensagem genérica só, confirmado com APP_DEBUG=true), mas o log vaza. Se uma chave real for revogada/rotacionada e continuar no .env, ela fica em claro no arquivo de log. Trate storage/logs/ como material sensível e não publique o log em issue, gist ou slide de workshop sem redigir `sk-[A-Za-z0-9_-]+`.
