# Cron Explainer — Project Description

## Overview

**Cron Explainer** é uma **single page** que recebe uma expressão cron e devolve, **em português**, o que ela significa e **quando ela vai rodar de verdade**. O usuário digita `0 3 * * 1-5`, clica em *Explicar*, e recebe uma explicação em linguagem natural mais a lista das **5 próximas execuções** com data e hora concretas.

O público é **quem escreve ou revisa crontab e não quer decorar a ordem dos cinco campos** — devs, SREs e alunos de workshop. O problema resolvido é o de sempre: a sintaxe cron é críptica, e ler `*/15 2-6 * * 0` não diz nada sobre quando a coisa realmente dispara. A página fecha esse gap sem exigir instalação, login ou consulta a documentação.

O projeto é **deliberadamente pequeno**: **uma rota, um componente Livewire, um agente de IA, zero banco de dados de domínio, zero autenticação**. Toda a inteligência — interpretação da expressão, cálculo das próximas datas e detecção de erro — vem de **uma única chamada ao modelo via Laravel AI SDK com structured output**. Não há parser de cron em PHP: se a expressão é inválida, é a própria IA que diz qual campo está errado e por quê.

**Fora do MVP:** histórico, persistência, contas de usuário, presets clicáveis, breakdown campo a campo em tabela, tradução para outros idiomas, geração de expressão a partir de texto (o caminho inverso), suporte a sintaxe estendida de schedulers específicos (Quartz, systemd timers).

### Key Concepts

- **Expressão cron:** string de **5 campos** separados por espaço — minuto, hora, dia-do-mês, mês, dia-da-semana — aceitando `*`, valores, listas (`1,15`), intervalos (`1-5`) e passos (`*/15`). Macros (`@daily`, `@hourly`) são passadas à IA como entrada válida.
- **Explicação:** parágrafo curto em pt-BR descrevendo em linguagem natural a regra de disparo (ex: "a cada 15 minutos, entre 2h e 6h, todos os dias").
- **Próximas execuções:** lista de **exatamente 5** datas/horas futuras, calculadas pela IA, no timezone fixo **`America/Sao_Paulo`**, contadas a partir do instante da requisição.
- **Validade:** campo booleano do structured output. Quando `false`, a resposta traz um **erro explicado** apontando o campo problemático e o intervalo aceito (ex: "o campo minuto aceita 0–59, você passou 70") — nenhuma data é retornada.
- **Agente:** classe sob `app/Ai/Agents/` implementando `Laravel\Ai\Contracts\Agent` e `HasStructuredOutput`, com as instruções do domínio e o schema da resposta.
- **Structured output:** contrato JSON devolvido pela IA — `valido`, `explicacao`, `erro`, `proximas_execucoes[]` — validado pelo SDK, consumido pelo componente Livewire como array.
- **Sem estado:** nenhuma tabela de domínio, nenhuma sessão, nenhum cache de resultado. Cada requisição é independente.

## Tech Stack

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.5 (`composer.json` exige `^8.3`; runtime Sail é 8.5) |
| Framework | Laravel 13.17 (`laravel/framework: ^13.17`) |
| UI reativa | Livewire 4.1 (`livewire/livewire: ^4.1`) + `livewire/blaze: ^1.0` |
| IA | **Laravel AI SDK** (`laravel/ai`, **a instalar**) — `Lab::OpenAI`, modelo **`gpt-5.6-terra`** |
| Config IA | `config/ai.php` + `OPENAI_API_KEY` no `.env` |
| CSS | Tailwind CSS 4.1 via `@tailwindcss/vite` |
| Build | Vite 8 + `laravel-vite-plugin` 3.1 |
| Testes | Pest 5.1 + `pest-plugin-laravel` 5.0; `AI::fake()` para os testes do agente |
| Análise estática | Larastan 3.9 (`phpstan.neon`) |
| Formatação | Laravel Pint 1.27 (`pint.json`) |
| Ambiente dev | Laravel Sail (`compose.yaml`, serviço único `laravel.test`, **sem container de banco**) |
| Persistência | **Nenhuma** — sem tabelas de domínio, sem models além do `User` do starter kit |
| Rotas | Rota única `/` (hoje `Route::view('/', 'welcome')`) |

## Core Workflows

### 1. Explicar uma expressão cron válida

1. Usuário abre `/` e vê um único campo de texto com placeholder de exemplo (`0 3 * * 1-5`) e o botão **Explicar**.
2. Usuário digita a expressão e clica em **Explicar**. O componente Livewire mostra estado de carregamento via `wire:loading` (botão desabilitado + spinner). **Não há streaming** — a resposta chega de uma vez.
3. O componente monta o prompt com a expressão, o timezone fixo `America/Sao_Paulo` e o instante atual (`now()` em ISO-8601), e chama o agente:

```php
$resposta = (new CronExplainer)->prompt(
    "Expressão: {$this->expressao}\nAgora: {$agora}\nTimezone: America/Sao_Paulo"
);
```

4. O agente devolve structured output:

```json
{
  "valido": true,
  "explicacao": "A cada 15 minutos, entre 2h e 6h, apenas aos domingos.",
  "erro": null,
  "proximas_execucoes": [
    "2026-08-23 02:00",
    "2026-08-23 02:15",
    "2026-08-23 02:30",
    "2026-08-23 02:45",
    "2026-08-23 03:00"
  ]
}
```

5. A página renderiza a **explicação** em destaque e as **5 próximas execuções** como lista, cada uma com dia da semana por extenso.
6. Uma nova submissão substitui o resultado anterior. Nada é gravado.

### 2. Tratar expressão inválida

1. Fluxo idêntico ao workflow 1 até a chamada do agente — **não há validação prévia por regex**; qualquer texto não vazio é enviado à IA.
2. O agente identifica o problema e devolve `valido: false`:

```json
{
  "valido": false,
  "explicacao": null,
  "erro": "O campo minuto aceita valores de 0 a 59, mas recebeu 70.",
  "proximas_execucoes": []
}
```

3. A página mostra o **erro explicado** em estado visual de alerta e **não renderiza lista de execuções**.
4. Regras de borda tratadas no cliente, sem chamar a IA: campo vazio e campo só com espaços bloqueiam a submissão com mensagem local.

### 3. Falhar com graça quando a IA não responde

1. A chamada ao agente é envolvida em `try/catch` com `timeout` explícito.
2. Em falha de rede, timeout, chave ausente/ inválida ou payload fora do schema, a página exibe uma mensagem única: falha ao consultar a IA, tente novamente — sem stack trace, sem vazar a chave.
3. O detalhe técnico vai para o log da aplicação (`storage/logs`), não para a tela.
4. O campo preenchido pelo usuário é preservado para nova tentativa.

## Open Questions

- **Precisão das datas:** foi decidido que a IA calcula as próximas execuções (sem `cron-expression` determinístico). LLMs erram aritmética de calendário em casos como `0 0 31 * *` (meses com menos de 31 dias) e dia-do-mês combinado com dia-da-semana. Aceito como risco conhecido do escopo — reavaliar se os exemplos do workshop caírem nesses casos.
- **Identificador do modelo:** `gpt-5.6-terra` foi informado pelo desenvolvedor e não foi verificado contra a API da OpenAI. Deve ficar em `config/ai.php`/`.env`, não hardcoded no atributo `#[Model]`, para poder ser trocado sem alterar código.
- **Migrations do AI SDK:** o `vendor:publish` do pacote registra migrations (persistência de conversas). Como o projeto é stateless, a intenção é **não rodá-las**. Confirmar que `prompt()` com structured output funciona sem essas tabelas; se não funcionar, o SQLite local cobre o caso sem introduzir container de banco.
