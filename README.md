# Cron Explainer

Single page que recebe uma expressão cron e devolve, em português, o que ela significa e **quando ela vai rodar de verdade**.

Você digita `0 3 * * 1-5`, clica em **Explicar**, e recebe:

- uma explicação em linguagem natural ("Às 3h de segunda a sexta.");
- as **5 próximas execuções** com data e hora concretas, no timezone `America/Sao_Paulo`.

Se a expressão for inválida, a página mostra qual campo está errado e o intervalo aceito por ele.

> Este projeto foi construído ao vivo no **workshop de Loop Engineering**. Ele é ao mesmo tempo o produto e a demonstração do método: o código foi escrito por um loop de agentes dirigido por especificação. A segunda metade deste README explica como.

---

## O produto

### Escopo

Deliberadamente pequeno: **uma rota, um componente Livewire, um agente de IA, zero banco de dados de domínio, zero autenticação**.

Não existe parser de cron em PHP. Toda a inteligência — interpretar a expressão, calcular as próximas datas, detectar erro e dizer qual campo está errado — vem de **uma única chamada ao modelo via Laravel AI SDK com structured output**.

**Fora do MVP:** histórico, persistência, contas de usuário, presets clicáveis, breakdown campo a campo, outros idiomas, geração de expressão a partir de texto (o caminho inverso), sintaxe estendida (Quartz, systemd timers).

### Sintaxe aceita

Cinco campos separados por espaço — minuto, hora, dia-do-mês, mês, dia-da-semana — com `*`, valores únicos (`30`), listas (`1,15`), intervalos (`1-5`), passos (`*/15`, `10-30/5`) e combinações (`1,15-20,*/2`).

Macros: `@hourly`, `@daily`, `@weekly`, `@monthly`, `@yearly`. Nenhuma outra.

### Stack

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.5 (runtime Sail) |
| Framework | Laravel 13 |
| UI reativa | Livewire 4 + Blaze |
| IA | Laravel AI SDK (`laravel/ai`) — `Lab::OpenAI` |
| CSS | Tailwind CSS 4 via `@tailwindcss/vite` |
| Build | Vite 8 |
| Testes | Pest 5 |
| Análise estática | Larastan 3 |
| Formatação | Laravel Pint |
| Ambiente dev | Laravel Sail (serviço único, **sem container de banco**) |
| Persistência | **Nenhuma** |

### Arquitetura

```
routes/web.php                              Route::livewire('/', 'pages::cron-explainer')
resources/views/pages/⚡cron-explainer.blade.php   SFC Livewire: estado, prompt, tratamento de erro e view
resources/views/pages/⚡cron-explainer.test.php    testes co-locados do SFC
app/Ai/Agents/CronExplainer.php             instruções do domínio + schema do structured output
config/cron-explainer.php                   modelo, timezone, quantidade de execuções, timeout
```

Toda a lógica vive no componente — não há Action nem service class. Ele monta o prompt (expressão + instante atual em ISO-8601 + timezone), chama o agente, valida o payload contra o contrato e traduz o resultado em estado de tela.

**Contrato do structured output** — quatro chaves, todas obrigatórias. Campos não aplicáveis vêm vazios (`''` / `[]`), nunca ausentes nem `null`:

```json
{
  "valido": true,
  "explicacao": "A cada 15 minutos, entre 2h e 6h, apenas aos domingos.",
  "erro": "",
  "proximas_execucoes": [
    "2026-08-23 02:00",
    "2026-08-23 02:15",
    "2026-08-23 02:30",
    "2026-08-23 02:45",
    "2026-08-23 03:00"
  ]
}
```

Qualquer falha da chamada — rede, timeout, chave ausente ou inválida, payload fora do contrato, data não parseável — cai numa mensagem única na tela. O detalhe técnico vai para `storage/logs`, nunca para o usuário.

### Decisões que não são acidentais

- **O modelo não está no atributo `#[Model]`.** Só `#[Provider(Lab::OpenAI)]` é anotado; o identificador do modelo é passado em runtime a partir de `config('cron-explainer.model')`, para poder ser trocado por ambiente sem tocar em código.
- **Sem banco.** A migration `agent_conversations` do `laravel/ai` nunca foi publicada — o agente é stateless, sem memória de conversa e sem tools. A suíte roda sem arquivo SQLite.
- **`OPENAI_API_KEY` é zerada à força nos testes.** O `phpunit.xml` tem `<env name="OPENAI_API_KEY" value="" force="true"/>`. Sem o `force`, a chave real do `.env` (ou exportada no shell) chegaria aos testes, e qualquer chamada que escapasse de um fake viraria requisição paga de verdade. `tests/Feature/AmbienteDeTesteTest.php` trava esse invariante.
- **Risco conhecido e aceito:** a IA calcula as datas, sem biblioteca determinística de cron. LLMs erram aritmética de calendário em casos como `0 0 31 * *` (meses com menos de 31 dias) ou dia-do-mês combinado com dia-da-semana.

Estas e outras decisões estão registradas em `.ai/rules/`, indexadas por glob em `.ai/rules/index.md` — é lá que agentes e humanos leem as regras da casa antes de editar cada caminho.

---

## Rodando

Requer Docker.

```bash
git clone <repo> && cd workshop-cron-explainer
cp .env.example .env

vendor/bin/sail up -d
vendor/bin/sail composer install
vendor/bin/sail artisan key:generate
vendor/bin/sail npm install && vendor/bin/sail npm run build
```

Preencha `OPENAI_API_KEY` no `.env` e abra `vendor/bin/sail open`.

Variáveis do domínio:

| Variável | Padrão | Papel |
|---|---|---|
| `OPENAI_API_KEY` | — | credencial do provider |
| `CRON_EXPLAINER_MODEL` | `gpt-5.6-terra` | modelo usado pelo agente |
| `CRON_EXPLAINER_TIMEZONE` | `America/Sao_Paulo` | timezone das execuções exibidas |
| `CRON_EXPLAINER_TIMEOUT` | `30` | timeout, em segundos, da chamada à IA |

### Gate de qualidade

```bash
vendor/bin/sail composer test        # config:clear + pint --test + phpstan + pest
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --format agent
vendor/bin/sail composer types:check
```

Os testes usam `CronExplainer::fake()` — nenhuma requisição real é feita.

Atenção ao SFC: `resources/views/` está **fora** dos paths do Larastan e o Pint padrão ignora `*.blade.php`. O único gate real do componente são os testes co-locados.

---

## O método: Loop Engineering

A parte do workshop. O produto acima é pretexto; o que se demonstra é o processo.

A ideia central: **em vez de conversar com um agente até o código sair, você escreve a especificação, quebra em fases verificáveis, e deixa um loop executar as fases até cada uma passar no gate.** O humano trabalha na spec e no gate — não no diff.

### 1. Spec antes de código

Tudo começa em `.spec/init/`, gerado por entrevista com o desenvolvedor:

```
.spec/init/project-description.md    escopo, key concepts, stack, workflows, open questions
.spec/init/user-stories.md           histórias testáveis
.spec/init/database-schema.md        schema derivado (aqui: declarado intocado)
.spec/init/project-phases.md         o plano de execução
```

`project-description.md` não é um documento decorativo: ele fixa o que está **fora** do MVP e registra as *open questions* — as decisões que ainda eram risco no momento do planejamento (o identificador do modelo não verificado, a precisão de datas calculadas por LLM, as migrations do SDK). O agente executa contra esse documento.

### 2. Fases com critérios de aceite binários

`project-phases.md` quebra o trabalho em fases numeradas, cada uma com tasks, **acceptance criteria verificáveis** e rastreio de volta para a spec:

```
Phase 1: Fundação — Laravel AI SDK, ausência de banco e base de UI
Phase 2: Agente CronExplainer — instruções e contrato de saída
Phase 3: Interface Livewire e fluxos completos
Phase 4: Gate de qualidade e fechamento do MVP
```

Cada critério é objetivo o bastante para ser conferido sem julgamento — `laravel/ai` aparece em `require` e não em `require-dev`, `artisan about` não reporta erro de provider, e assim por diante. Critério vago não sobrevive a um loop.

O espelho executável fica em `.phases/`, com `manifest.txt` (fase → arquivo → título, carimbado com o sha do plano de origem) e `.progress` (o que já passou).

### 3. O loop

`loop.php` — ~90 linhas, sem dependência nenhuma — é o motor. Para cada fase:

1. **Implementa.** Chama `claude -p` com o texto integral da fase e a instrução de implementá-la completamente, sem TODOs e sem placeholders.
2. **Testa.** Roda `./vendor/bin/sail pest`. Se falhar, as últimas 30 linhas da saída viram o motivo realimentado na próxima tentativa.
3. **Verifica.** Chama um **segundo agente, independente e read-only** (`--allowedTools "Read,Glob,Grep"`) que lê o código real e responde exatamente uma linha: `DONE` ou `FALTA — <o que não está implementado>`. A instrução é explícita: *na dúvida, FALTA*.
4. **Commita.** Só com `DONE`, e a fase vira um commit.
5. **Insiste.** Até `MAX_TRIES = 3`, cada tentativa recebendo o motivo da reprovação anterior. Depois disso, desiste e segue — falha visível, não silenciosa.

```php
$veridct = claude($prompt, '--allowedTools "Read,Glob,Grep"', true);

if (preg_match('/^DONE/m', $veridct)) {
    exec('git add -A && git commit -q -m '.escapeshellarg("feat: $title"));
    break;
}
```

O ponto não é o script — é a **separação de papéis**. Quem escreve o código não decide se o código está pronto. O verificador não tem permissão de escrita, então não pode "consertar" o problema em vez de reportá-lo. E o gate mecânico (Pest) roda antes do gate de julgamento, porque é mais barato.

O histórico do repositório é a saída do loop, um commit por fase:

```
df9d3fc feat: Phase 1: Fundação — Laravel AI SDK, ausência de banco e base de UI
f00c6c1 feat: Phase 2: Agente CronExplainer — instruções e contrato de saída
dfd0525 feat: Phase 3: Interface Livewire e fluxos completos
4591165 feat: Phase 4: Gate de qualidade e fechamento do MVP
```

### 4. O que o loop aprendeu, fica

Descobertas caras viram regra escrita em `.ai/rules/`, não conhecimento perdido no fim da sessão. Exemplos reais deste projeto, todos achados durante a execução:

- **SFC compila no namespace global.** `use Throwable;` dentro do bloco PHP de um single-file component dispara "The use statement with non-compound name has no effect", que o Laravel converte em `ErrorException` e derruba a página com 500. Solução: `catch (\Throwable $e)` qualificado. (`.ai/rules/pages.md`)
- **Rodar `pint` apontando direto para o `.blade.php`** aplica `fully_qualified_strict_types` e reescreve exatamente as barras invertidas que a regra acima exige. Rode sempre sem argumento de path.
- **O stub de `make:agent --structured`** gera a classe implementando também `Conversational` e `HasTools` — os dois foram removidos, porque o agente é stateless. (`.ai/rules/ai.md`)

Cada uma dessas é uma armadilha que custaria uma iteração inteira do loop na próxima vez. Escrita como regra com glob, ela é lida antes de o próximo agente tocar naquele caminho.

### 5. Contexto para agentes

O repositório carrega o próprio manual de operação:

- `AGENTS.md` / `CLAUDE.md` — contexto canônico do projeto, gerado a partir do código implementado e regerável quando ele muda;
- `.ai/rules/` — regras por glob, com `index.md` mapeando caminho → arquivo de regra;
- `.mcp.json` — o servidor Laravel Boost, dando ao agente `search-docs`, `database-schema`, `browser-logs` e afins em vez de chutes;
- `.codex/`, `.grok/`, `.agents/` — o mesmo contexto exposto para outros clientes de agente.

### 6. O resumo

```
descrição → spec → fases com critérios binários → loop (implementa → testa → verifica → commita) → regras
                                                        ↑                                          │
                                                        └──────────── o que se aprendeu ───────────┘
```

Onde o humano gasta tempo: escrevendo a spec, apertando os critérios de aceite e endurecendo o gate. Não revisando diff linha a linha — se o diff está errado com frequência, o defeito está na spec ou no gate, e é lá que se corrige.
