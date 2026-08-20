---
paths:
  - 'config/**'
---

# Config

## Modelo e timezone vêm de config/cron-explainer.php
O identificador do modelo fica em config('cron-explainer.model') (env CRON_EXPLAINER_MODEL) — nunca hardcoded no atributo #[Model] do agente, para poder ser trocado por ambiente. A exibição de datas usa exclusivamente config('cron-explainer.timezone') (America/Sao_Paulo); config('app.timezone') permanece UTC, o padrão do framework. occurrences é fixo em 5. A chave do provider vem de OPENAI_API_KEY via config/ai.php.

## O 401 da OpenAI grava um fragmento da API key em storage/logs/laravel.log
Verificado em runtime na Phase 4.2, e reconferido na revalidação: quando a chave é recusada, a OpenAI devolve 401 com "Incorrect API key provided: sk-proj-*******************************ase4", e o `Log::error(..., ['exception' => $e])` do SFC grava essa mensagem em storage/logs/laravel.log. A OpenAI mascara o miolo — o que chega ao log é o prefixo `sk-proj-` mais os 4 últimos caracteres da chave, não a chave inteira.

A página não vaza nada (mensagem genérica só, confirmado com APP_DEBUG=true), mas o log vaza esse fragmento, junto com stack trace e caminhos `/var/www/html/vendor/...`. Trate storage/logs/ como material sensível e não publique o log em issue, gist ou slide de workshop sem redigir `sk-[A-Za-z0-9_-]+`.
