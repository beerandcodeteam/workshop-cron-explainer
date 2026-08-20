# Cron Explainer — Project Phases

<!-- inputs: project-description.md@sha256:7025602f3bcc user-stories.md@sha256:47d3d24e8d3c database-schema.md@sha256:6bf3cc848468 -->

## Overview

O build é **foundation-first** em **4 fases**. A Fase 1 prepara o terreno: instala e configura o Laravel AI SDK, confirma que nenhuma migration de domínio é necessária e monta a base de UI. A Fase 2 constrói o agente `CronExplainer` — instruções e contrato de structured output — que é o coração da aplicação. A Fase 3 entrega a interface Livewire e todos os fluxos das user stories. A Fase 4 é o gate de qualidade: formatação, análise estática, suíte completa e verificação manual dos critérios de aceite.

Não há fase de banco de dados nem de models: o `database-schema.md` declara **zero tabelas de domínio**. Não há fase de autenticação: a página é pública e anônima. O **corte do MVP é o fim da Fase 4** — a Fase 3 já entrega a aplicação funcionando, mas o release só fecha depois do gate. Toda a lógica de negócio vive **dentro do componente Livewire** (decisão do desenvolvedor), então os testes de regra de negócio passam por `Livewire::test()` com `AI::fake()`.

**Conventions:**
- `[ ]` pending · `[x]` done in the codebase.
- Phases and sub-phases are numbered (`Phase 1`, `Phase 5.3`) for reference by AI agents.
- Business-logic tasks list the **feature tests** to generate; frontend-only tasks list validatable **acceptance criteria** and a **Design ref**.
- `.spec/init/design/` não existe e, por decisão do desenvolvedor, **a UI é construída com um default sensato** (Tailwind 4, layout centrado, dark mode, responsivo). Tarefas de tela não têm Design ref; seus critérios descrevem elementos e estados exigidos.
- Todo comando roda por Sail: `vendor/bin/sail ...`.

---

## Phase 1: Fundação — Laravel AI SDK, ausência de banco e base de UI

**Goal:** Deixar o projeto pronto para receber o agente: SDK instalado e configurado, locale/timezone corretos, suíte de testes sem dependência de banco e layout base no lugar. · **Depends on:** none · **Covers:** Tech Stack da descrição, todas as tabelas do schema (declaradas intocadas), base de US-1.1 e US-1.2

### Phase 1.1: Instalação e configuração do AI SDK

- [ ] **Task:** Instalar o pacote `laravel/ai` como dependência de produção
  - **Acceptance criteria:**
    - `vendor/bin/sail composer require laravel/ai` executado com sucesso
    - `laravel/ai` aparece em `require` (não `require-dev`) do `composer.json`
    - `composer.lock` atualizado e commitado
    - `vendor/bin/sail artisan about` não reporta erro de service provider
  - **Traces:** project-description → Tech Stack (linha "IA — Laravel AI SDK, a instalar")

- [ ] **Task:** Publicar a configuração do SDK sem rodar as migrations do pacote
  - **Acceptance criteria:**
    - `vendor/bin/sail artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` executado; `config/ai.php` existe
    - As migrations publicadas pelo pacote **não são executadas** — a aplicação é stateless
    - Se `prompt()` com structured output falhar por falta das tabelas do SDK, rodar as migrations do pacote contra o SQLite local e registrar o fato neste documento; **nenhuma tabela de domínio é criada em nenhum cenário**
    - `config/ai.php` lê a credencial do provider OpenAI de `OPENAI_API_KEY`, sem chave hardcoded
  - **Traces:** database-schema → Notes & Conventions ("Migrations do AI SDK"); project-description → Open Questions

- [ ] **Task:** Criar `config/cron-explainer.php` e as chaves de ambiente do domínio
  - **Acceptance criteria:**
    - `config/cron-explainer.php` retorna `model`, `timezone`, `occurrences` e `timeout`
    - `model` vem de `env('CRON_EXPLAINER_MODEL', 'gpt-5.6-terra')` — **o identificador do modelo não é hardcoded no atributo `#[Model]`**, para poder ser trocado sem alterar código
    - `timezone` default `America/Sao_Paulo`; `occurrences` fixo em `5`; `timeout` default `30` segundos, tipado como `int`
    - `.env` e `.env.example` ganham `OPENAI_API_KEY=`, `CRON_EXPLAINER_MODEL=`, `CRON_EXPLAINER_TIMEOUT=`
    - `.env.example` **não** contém valor real de chave
    - `vendor/bin/sail artisan config:show cron-explainer` imprime os quatro valores
  - **Traces:** project-description → Open Questions ("Identificador do modelo"); US-1.2

### Phase 1.2: Confirmação de ausência de banco de domínio

- [x] **Task:** Confirmar que nenhuma migration nova é necessária e que as tabelas herdadas permanecem intocadas
  - **Acceptance criteria:**
    - `database/migrations/` contém apenas os três arquivos `0001_01_01_*` do starter kit
    - As tabelas herdadas `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` e `failed_jobs` não são lidas nem escritas por nenhum código do Cron Explainer
    - Nenhum model novo é criado em `app/Models/`; `User` permanece como está, sem uso
    - `DB_CONNECTION=sqlite` permanece; `compose.yaml` continua sem container de banco
    - **Estado atual:** já satisfeito pelo repositório — nenhuma ação de código, apenas não regredir
  - **Traces:** database-schema → Schema (DBML), todas as 8 tabelas herdadas

- [ ] **Task:** Remover `RefreshDatabase` de `tests/Pest.php`
  - **Acceptance criteria:**
    - `tests/Pest.php` deixa de aplicar `RefreshDatabase` aos testes de `Feature` — nenhum teste do projeto toca o banco
    - `pest()->extend(TestCase::class)->in('Feature')` continua valendo
    - `vendor/bin/sail artisan test` roda sem exigir arquivo SQLite nem migrations
    - A suíte de exemplo (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`) continua verde
  - **Feature tests:** nenhum teste novo — a própria suíte existente rodando verde sem banco é a validação
  - **Traces:** database-schema → Overview ("zero tabelas de domínio")

### Phase 1.3: Base de UI e localização

- [ ] **Task:** Configurar locale pt_BR e o timezone de exibição
  - **Acceptance criteria:**
    - `APP_LOCALE=pt_BR` e `APP_FALLBACK_LOCALE=en` em `.env` e `.env.example`
    - `Carbon::translatedFormat('l')` devolve dia da semana em português (ex: `domingo`)
    - `config('app.timezone')` permanece o default do framework; a conversão para exibição usa **exclusivamente** `config('cron-explainer.timezone')`
    - `vendor/bin/sail artisan config:show app.locale` confirma `pt_BR`
  - **Feature tests:** `it('formata dia da semana em português')` → assegura que uma data conhecida convertida para `America/Sao_Paulo` e formatada com `translatedFormat` produz o dia por extenso em pt-BR
  - **Traces:** US-1.2

- [ ] **Task:** Criar o layout base `resources/views/components/layouts/app.blade.php`
  - **Acceptance criteria:**
    - Layout com `<!DOCTYPE html>`, `lang` derivado do locale da aplicação, `<meta name="viewport">` e `@vite(['resources/css/app.css', 'resources/js/app.js'])`
    - `{{ $slot }}` renderiza o conteúdo da página; `<title>` usa `config('app.name')`
    - Fundo e texto com classes Tailwind claras/escuras (`dark:`), container centrado com largura máxima legível
    - Layout é o default de página do Livewire (`components.layouts.app`) — nenhum componente precisa declarar `#[Layout]`
    - `vendor/bin/sail npm run build` conclui sem erro
  - **Traces:** US-1.1

---

## Phase 2: Agente CronExplainer — instruções e contrato de saída

**Goal:** Construir a classe de agente que interpreta a expressão cron, com instruções em pt-BR e schema de structured output estável. · **Depends on:** Phase 1 · **Covers:** US-1.1, US-1.2, US-2.1 (a parte de IA); Core Workflows 1 e 2

### Phase 2.1: Classe do agente

- [ ] **Task:** Criar `app/Ai/Agents/CronExplainer.php` implementando `Agent` e `HasStructuredOutput`
  - **Acceptance criteria:**
    - Gerado com `vendor/bin/sail artisan make:agent CronExplainer`
    - Implementa `Laravel\Ai\Contracts\Agent` e `Laravel\Ai\Contracts\HasStructuredOutput`, usa o trait `Laravel\Ai\Promptable`
    - Anotado com `#[Provider(Lab::OpenAI)]`; **sem** `#[Model]` — o modelo é passado em tempo de execução a partir de `config('cron-explainer.model')`
    - Métodos com tipos de retorno explícitos; passa Pint e Larastan level 7
  - **Traces:** project-description → Core Workflows 1; US-1.1

- [ ] **Task:** Escrever `instructions()` com as regras do domínio em pt-BR
  - **Acceptance criteria:**
    - Define o papel: interpretar expressões cron de **5 campos** (minuto, hora, dia-do-mês, mês, dia-da-semana)
    - Aceita `*`, valores, listas (`1,15`), intervalos (`1-5`), passos (`*/15`) e as macros `@daily`, `@hourly`, `@weekly`, `@monthly`, `@yearly`
    - Ordena explicitamente **exatamente 5** execuções futuras, em ordem cronológica crescente, todas posteriores ao instante informado no prompt
    - Fixa o formato de data de saída como `Y-m-d H:i` e o timezone como o informado no prompt
    - Instrui a devolver `valido: false` com `erro` citando **o campo e o intervalo aceito** quando a expressão for inválida, e nesse caso `explicacao` vazia e `proximas_execucoes` vazio
    - Instrui a devolver `erro` vazio quando `valido: true`
    - Responde sempre em português do Brasil
  - **Feature tests:** `it('instrui as regras do domínio')` → assegura que a string de `instructions()` menciona os 5 campos, as macros, o número 5 de execuções, o formato `Y-m-d H:i` e a regra de `valido: false`
  - **Traces:** US-1.1, US-1.2, US-2.1

- [ ] **Task:** Definir `schema()` com o contrato de structured output
  - **Acceptance criteria:**
    - Retorna exatamente quatro chaves: `valido` (boolean, required), `explicacao` (string, required), `erro` (string, required), `proximas_execucoes` (array de string, required)
    - Todas as chaves são `required` — os campos não aplicáveis vêm como string vazia ou array vazio, nunca ausentes. Isso refina o exemplo da descrição, que mostrava `"erro": null`; o consumidor trata vazio e `null` de forma equivalente
    - `proximas_execucoes` declara `items()` como string
    - Nenhum campo enum é declarado — `valido` é booleano
  - **Feature tests:** `it('declara o contrato de saída')` → assegura que `schema()` devolve as quatro chaves esperadas e que `proximas_execucoes` é um array de strings
  - **Traces:** US-1.2, US-2.1

### Phase 2.2: Testes de contrato do agente

- [ ] **Task:** Criar o arquivo de teste do agente
  - **Acceptance criteria:**
    - Teste criado com `vendor/bin/sail artisan make:test --pest CronExplainerAgentTest`
    - Os dois testes da Phase 2.1 vivem nele e rodam **sem** chamada de rede (asserções sobre a classe, não sobre resposta de IA)
    - `vendor/bin/sail artisan test --compact --filter=CronExplainerAgent` verde
  - **Feature tests:** os dois casos acima (`instrui as regras do domínio`, `declara o contrato de saída`)
  - **Traces:** US-1.1, US-1.2, US-2.1

---

## Phase 3: Interface Livewire e fluxos completos

**Goal:** Entregar a página funcionando de ponta a ponta — caminho feliz, expressão inválida, input vazio e falha da IA — com testes cobrindo cada fluxo. · **Depends on:** Phase 2 · **Covers:** US-1.1, US-1.2, US-1.3, US-2.1, US-2.2, US-2.3; Core Workflows 1, 2 e 3

### Phase 3.1: Componente, rota e lógica

- [ ] **Task:** Criar o page component SFC `pages::cron-explainer`
  - **Acceptance criteria:**
    - Gerado com `vendor/bin/sail artisan make:livewire pages::cron-explainer --test`
    - Arquivo em `resources/views/pages/` no formato single-file (PHP + Blade no mesmo arquivo)
    - Renderiza dentro do layout `components.layouts.app` da Phase 1.3
    - O componente **contém toda a lógica** — não há Action nem service class (decisão do desenvolvedor)
  - **Traces:** US-1.1

- [ ] **Task:** Apontar a rota `/` para o componente e remover a view de boas-vindas
  - **Acceptance criteria:**
    - `routes/web.php` usa `Route::livewire('/', 'pages::cron-explainer')->name('home')`, substituindo `Route::view('/', 'welcome')`
    - `resources/views/welcome.blade.php` é removido
    - O nome de rota `home` é preservado
    - Se o identificador `pages::cron-explainer` não resolver, ajustar para o identificador correto e manter o teste de smoke verde — não alterar o caminho do arquivo do componente
  - **Feature tests:** `it('renderiza o componente na home')` → `$this->get('/')` retorna 200 e `assertSeeLivewire` encontra o componente
  - **Traces:** US-1.1

- [ ] **Task:** Implementar estado do componente e a action `explicar()`
  - **Acceptance criteria:**
    - Propriedade pública `expressao` ligada por `wire:model`; propriedades de resultado (`explicacao`, `erro`, `proximasExecucoes`, `falhaDeIa`) resetadas no início de cada submissão
    - `explicar()` monta o prompt com a expressão, o instante atual em ISO-8601 e `config('cron-explainer.timezone')`
    - Chama o agente passando `provider: Lab::OpenAI`, `model: config('cron-explainer.model')` e `timeout: config('cron-explainer.timeout')`
    - Quando `valido` é `true`, converte cada string de `proximas_execucoes` para Carbon no timezone configurado e guarda no estado; quando `valido` é `false`, guarda o `erro` e deixa a lista vazia
    - Uma nova submissão substitui integralmente o resultado anterior
    - Nada é gravado em banco, sessão ou cache
  - **Feature tests:** `it('envia expressão, instante atual e timezone no prompt')` → com `AI::fake()`, assegura que o prompt enviado contém a expressão digitada e a string do timezone configurado; `it('substitui o resultado anterior a cada submissão')` → duas submissões seguidas com respostas diferentes deixam apenas a segunda no estado
  - **Traces:** US-1.1, US-1.2

- [ ] **Task:** Implementar a validação local de input vazio
  - **Acceptance criteria:**
    - Expressão vazia bloqueia a submissão com mensagem local e **não** dispara chamada à IA
    - Expressão composta apenas de espaços em branco é tratada como vazia
    - A mensagem local desaparece quando o visitante digita conteúdo válido
    - Nenhuma validação por regex de formato cron é aplicada — qualquer texto não vazio segue para a IA
  - **Feature tests:** `it('bloqueia submissão vazia sem chamar a IA')` → asserta erro de validação e que `AI::fake()` não registrou nenhuma chamada; `it('trata espaços em branco como vazio')` → mesma asserção com `"   "`
  - **Traces:** US-2.2, US-2.1

- [ ] **Task:** Implementar o tratamento de falha da IA
  - **Acceptance criteria:**
    - A chamada ao agente roda dentro de `try/catch` abrangente, com o `timeout` explícito da config
    - Falha de rede, timeout, chave ausente, chave inválida, payload fora do schema e data impossível de parsear produzem **a mesma** mensagem genérica em tela
    - A tela nunca exibe stack trace, nome de exception, nem qualquer parte da `OPENAI_API_KEY`
    - O detalhe técnico é registrado via `Log::error()` com a exception, indo para `storage/logs`
    - O texto digitado permanece na propriedade `expressao` após a falha
    - Com `APP_DEBUG=true` o comportamento é idêntico — a exception não escapa para a página de erro do Laravel
  - **Feature tests:** `it('mostra mensagem genérica quando a IA falha')` → força exceção na chamada e asserta a mensagem única em tela; `it('não vaza detalhe técnico nem a chave da API')` → asserta que a resposta não contém o valor de `OPENAI_API_KEY` nem o nome da exception; `it('preserva o input após falha')` → asserta que `expressao` mantém o valor digitado
  - **Traces:** US-2.3

### Phase 3.2: View e estados de tela

- [ ] **Task:** Construir o formulário com estado de carregamento
  - **Acceptance criteria:**
    - `<form wire:submit="explicar">` com um único `<input type="text" wire:model="expressao">` com placeholder `0 3 * * 1-5` e label acessível
    - Botão **Explicar** de `type="submit"`, desabilitado durante o processamento (`wire:loading.attr="disabled"`)
    - Indicador de carregamento visível enquanto a chamada está em curso e oculto ao final, tanto em sucesso quanto em erro
    - Resposta chega de uma vez — **nenhum uso de `stream()`**
    - Cliques repetidos durante o carregamento não disparam chamadas adicionais
  - **Traces:** US-1.3, US-1.1

- [ ] **Task:** Construir o bloco de sucesso com explicação e as 5 execuções
  - **Acceptance criteria:**
    - A explicação aparece em destaque, **acima** da lista de execuções
    - Exatamente 5 itens são listados, em ordem cronológica crescente
    - Cada item mostra dia da semana por extenso em português, data e hora — formato `domingo, 23/08/2026 02:15`
    - Todas as datas exibidas estão em `America/Sao_Paulo`
    - O bloco só é renderizado quando `valido` é `true`
  - **Feature tests:** `it('renderiza explicação e as 5 execuções')` → com `AI::fake()` devolvendo um payload válido, asserta a explicação em tela e a contagem de 5 itens formatados com dia da semana em pt-BR
  - **Traces:** US-1.1, US-1.2

- [ ] **Task:** Construir o bloco de erro de expressão inválida
  - **Acceptance criteria:**
    - Quando `valido` é `false`, o texto de `erro` é exibido em estado visual de alerta, distinto do estado de sucesso
    - A lista de próximas execuções **não** é renderizada nesse estado
    - O texto digitado permanece no campo para correção
    - Os blocos de sucesso e de erro nunca aparecem simultaneamente
  - **Traces:** US-2.1

- [ ] **Task:** Construir o bloco de falha da IA e finalizar o acabamento visual
  - **Acceptance criteria:**
    - Estado de falha da IA renderiza a mensagem genérica, visualmente distinto tanto do sucesso quanto do erro de validade
    - Layout responsivo: legível em viewport de 375px e centralizado em telas largas
    - Variantes `dark:` aplicadas a fundo, texto, borda do input e blocos de estado
    - Nenhum dos três estados de resultado aparece antes da primeira submissão
    - `vendor/bin/sail npm run build` conclui sem erro
  - **Traces:** US-2.3, US-1.1

### Phase 3.3: Testes de feature dos fluxos

- [ ] **Task:** Consolidar a suíte de testes do componente
  - **Acceptance criteria:**
    - Todos os testes das tarefas das Phases 3.1 e 3.2 vivem no arquivo de teste gerado junto ao componente (ou em `tests/Feature/`, conforme o formato produzido pelo `--test`)
    - Todo teste usa `AI::fake()` — **nenhuma chamada real de rede** na suíte
    - Nenhum teste depende de banco de dados
    - `vendor/bin/sail artisan test --compact` verde do início ao fim
    - Cobertura declarada: caminho feliz, expressão inválida, input vazio, espaços em branco, falha da IA, preservação de input, ausência de vazamento de chave
  - **Feature tests:** agregação dos casos definidos nas Phases 3.1 e 3.2 — nenhum caso novo
  - **Traces:** US-1.1, US-1.2, US-1.3, US-2.1, US-2.2, US-2.3

---

## Phase 4: Gate de qualidade e fechamento do MVP

**Goal:** Fechar o release: formatação, análise estática, suíte completa e verificação manual dos critérios de aceite com chave real. · **Depends on:** Phase 3 · **Covers:** todas as user stories (verificação); nenhuma feature nova

### Phase 4.1: Gates automáticos

- [x] **Task:** Rodar Pint e corrigir toda a formatação
  - **Acceptance criteria:**
    - `vendor/bin/sail bin pint --format agent` executado, sem arquivos pendentes de correção depois
    - Convenções de PHP do projeto respeitadas: chaves obrigatórias em estruturas de controle, tipos explícitos de parâmetro e retorno, promoção de propriedades no construtor, PHPDoc no lugar de comentário inline
  - **Traces:** US-1.1, US-2.3

- [x] **Task:** Rodar Larastan e zerar os erros
  - **Acceptance criteria:**
    - `vendor/bin/sail php vendor/bin/phpstan analyse` sem erros no level 7
    - `app/`, `config/` e `routes/` limpos — inclui `app/Ai/Agents/CronExplainer.php` e `config/cron-explainer.php`
    - Registrar explicitamente que o componente SFC em `resources/views/pages/` **não** é analisado, por estar fora dos `paths` do `phpstan.neon`; nenhum path novo é adicionado sem aprovação
  - **Traces:** US-1.2, US-2.1

- [x] **Task:** Rodar a suíte completa e o build de produção
  - **Acceptance criteria:**
    - `vendor/bin/sail artisan test --compact` verde, sem teste pulado
    - `vendor/bin/sail npm run build` conclui e gera `public/build/manifest.json`
    - A suíte roda sem `OPENAI_API_KEY` configurada — `AI::fake()` cobre todas as chamadas
  - **Traces:** US-1.3, US-2.2

### Phase 4.2: Verificação manual dos critérios de aceite

- [x] **Task:** Percorrer os critérios de aceite das 6 user stories com chave real
  - **Acceptance criteria:**
    - Com `OPENAI_API_KEY` válida, testar `0 3 * * 1-5`, `*/15 2-6 * * 0`, `@daily` e `0 0 1 * *` — todos retornam explicação em pt-BR e 5 datas futuras coerentes
    - Testar `70 3 * * *` — retorna erro citando campo minuto e o intervalo 0–59, sem lista de execuções
    - Testar campo vazio e campo com espaços — bloqueio local, sem chamada à IA
    - Cada critério de aceite de US-1.1, US-1.2, US-1.3, US-2.1, US-2.2 e US-2.3 é marcado como verificado
    - **Registrar** quaisquer datas incorretas observadas em `0 0 31 * *` ou em combinações de dia-do-mês com dia-da-semana — é o risco conhecido de a IA calcular calendário
  - **Traces:** US-1.1, US-1.2, US-1.3, US-2.1, US-2.2, US-2.3

- [x] **Task:** Verificar o comportamento de segurança em falha
  - **Acceptance criteria:**
    - Com `OPENAI_API_KEY` inválida, a página mostra a mensagem genérica e nada além dela
    - Com `APP_DEBUG=true` e chave inválida, a página continua mostrando a mensagem genérica, não a tela de erro do Laravel
    - `storage/logs/laravel.log` contém o detalhe técnico da falha
    - Inspecionar o HTML servido: nenhuma ocorrência da chave de API, de nome de exception ou de caminho de arquivo do servidor
  - **Feature tests:** cobertos pelos testes de US-2.3 na Phase 3.1; esta tarefa é a confirmação manual em runtime real
  - **Traces:** US-2.3

### Registro da verificação — executada em 2026-08-19 (America/Sao_Paulo)

Ambiente: Sail, `APP_DEBUG=true`, chave real da OpenAI em `.env`, modelo `gpt-5.6-terra`.
Método: round-trip HTTP real contra `POST /livewire-6df161ae/update` (não `Livewire::test()`),
inspecionando o HTML efetivamente servido.

**Gates automáticos (4.1)** — Pint `passed`; Larastan level 7 `0 errors`; suíte `25/25`,
0 pulados, 106 asserções; `npm run build` gerou `public/build/manifest.json`.

Cobertura do Larastan confirmada empiricamente: um erro de tipo plantado em
`app/Ai/Agents/CronExplainer.php` e em `config/cron-explainer.php` é acusado; o mesmo erro
plantado no bloco PHP de `resources/views/pages/⚡cron-explainer.blade.php` passa batido —
o SFC está fora dos `paths` do `phpstan.neon` e nenhum path novo foi adicionado.

A suíte roda sem chave: `phpunit.xml` agora zera `OPENAI_API_KEY` com `force="true"`, e
`tests/Feature/AmbienteDeTesteTest.php` trava esse invariante. Verificado rodando a suíte
com uma chave exportada no shell — os testes continuam vendo string vazia.

**Expressões válidas (4.2)** — todas com explicação em pt-BR e 5 datas futuras. Cada dia da
semana renderizado foi conferido contra cálculo independente de calendário; todos batem.

| Expressão | Explicação | Datas |
| --- | --- | --- |
| `0 3 * * 1-5` | "Às 3h da manhã, de segunda a sexta-feira." | 20, 21, 24, 25, 26/08 às 03:00 — pula sáb 22 e dom 23 ✅ |
| `*/15 2-6 * * 0` | "A cada 15 minutos, entre 2h e 6h, todos os domingos." | 23/08 (domingo) 02:00→03:00 de 15 em 15 ✅ |
| `@daily` | "Todos os dias à meia-noite." | 20 a 24/08 às 00:00 ✅ |
| `0 0 1 * *` | "À meia-noite do dia 1º de todos os meses." | 01/09, 01/10, 01/11, 01/12/2026, 01/01/2027 ✅ |
| `@monthly` | "À meia-noite do primeiro dia de cada mês." | idêntico a `0 0 1 * *` ✅ |

**Expressão inválida** — `70 3 * * *` devolveu
"Campo minuto inválido: 70. Valores aceitos: 0 a 59.", `proximasExecucoes` vazio, bloco
"Próximas execuções" ausente da página. ✅

**Bloqueio local** — campo vazio e campo só com espaços renderizaram "Digite uma expressão
cron para explicar." em ~60 ms, sem escrever nada em `storage/logs/laravel.log` e sem
qualquer requisição ao provider. ✅

**Risco conhecido de calendário — nenhuma data incorreta observada.**

- `0 0 31 * *` → 31/08, 31/10, 31/12/2026, 31/01, 31/03/2027. Pulou corretamente setembro,
  novembro e fevereiro. ✅
- `0 0 13 * 5` (dia-do-mês + dia-da-semana) → sextas 21/08, 28/08, 04/09, 11/09 **e**
  domingo 13/09. Aplicou a semântica OR do cron, explicou-a em português e ordenou certo. ✅
- `0 0 29 2 *` → 29/02 de 2028, 2032, 2036, 2040, 2044. Anos bissextos corretos. ✅

Único desvio observado: `0 0 31 4 *` foi **recusado** como inválido
("Campo dia-do-mês inválido para o mês 4: 31. Valores aceitos: 1 a 30."). O cron real aceita
a expressão — ela apenas nunca dispara. O comportamento é mais amigável que o do cron, mas
diverge dele; não afeta nenhum exemplo do workshop. A decisão de não usar
`dragonmantank/cron-expression` **não precisa ser reaberta** com base nesta rodada.

**Segurança em falha (4.2)** — com chave inválida e `APP_DEBUG=true`, a resposta foi HTTP 200
com `falhaDeIa: true`, `explicacao: null`, `erro: null`. A página exibiu apenas
"Falha temporária / Não foi possível interpretar a expressão agora. Tente novamente em
instantes." — não a tela de erro do Laravel. Varredura do corpo JSON inteiro e do HTML
renderizado não encontrou: a chave em uso, o prefixo `sk-proj-`, a chave real do `.env`,
`/var/www/html`, `vendor/laravel`, `Exception`, `Throwable`, stack trace, `Laravel\Ai`,
`401`, `Incorrect API key` ou `Unauthorized`. ✅
`storage/logs/laravel.log` recebeu o detalhe técnico: `local.ERROR: Falha ao interpretar
expressão cron.` com `Illuminate\Http\Client\RequestException (code: 401)` e stack trace.

⚠️ Achado colateral: a mensagem de 401 da OpenAI **ecoa um fragmento da API key**, e o
`Log::error(..., ['exception' => $e])` o grava no log. A OpenAI mascara o miolo — o que
chega ao arquivo é `sk-proj-*******************************ase4`, ou seja, prefixo mais os
4 últimos caracteres, **não** a chave inteira. A página não vaza nada, mas `storage/logs/`
passa a conter esse fragmento, stack trace e caminhos `/var/www/html/vendor/...`. Registrado
em `.ai/rules/config.md`. Não publique o log sem redigir `sk-[A-Za-z0-9_-]+`.

**Revalidação (mesma data, sessão posterior)** — os quatro gates foram reexecutados do zero:
Pint `passed`, Larastan level 7 `0 errors`, suíte `25/25` com `OPENAI_API_KEY=sk-should-not-be-used`
exportada no shell (os testes seguiram vendo string vazia), `public/build/` apagado e
reconstruído. Todas as expressões acima foram resubmetidas por round-trip HTTP real com a
chave válida e devolveram os mesmos resultados; cada dia da semana renderizado foi conferido
contra `date -d`. `0 0 31 4 *` voltou a ser recusado — o desvio é estável, não intermitente.
Campo vazio e campo com espaços responderam em 19 ms e 14 ms com `laravel.log` em 0 linhas,
provando ausência de chamada à IA. O teste de chave inválida foi refeito com `APP_DEBUG=true`:
mesma mensagem genérica, e a varredura do corpo servido (3.913 bytes) por
`sk-[A-Za-z0-9_-]{4,}`, `/var/www`, `vendor/`, `Exception`, `Throwable`, `Stack trace`, `401`,
`Unauthorized`, `Incorrect API key` e `Laravel\Ai` não retornou nenhuma ocorrência.

**Identificador do modelo** — `gpt-5.6-terra` existe e respondeu; a Open Question sobre ele
está resolvida. **Migrations do `laravel/ai`** — `prompt()` com structured output funcionou
sem nenhuma tabela do pacote; o fallback SQLite não foi necessário. **Identificador da rota**
— `pages::cron-explainer` resolveu sem ajuste.

---


## Open Questions

- **Precisão das datas calculadas pela IA:** decisão registrada de não usar cálculo determinístico. LLMs erram aritmética de calendário em casos como `0 0 31 * *` e dia-do-mês combinado com dia-da-semana. A Phase 4.2 obriga a registrar as falhas observadas — se aparecerem nos exemplos do workshop, cabe reabrir a decisão e introduzir `dragonmantank/cron-expression` para as datas, mantendo a IA só na explicação.
- **Identificador do modelo `gpt-5.6-terra`:** informado pelo desenvolvedor, não verificado contra a API da OpenAI. A Phase 1.1 o coloca em config/env justamente para permitir a troca sem alterar código; a primeira chamada real na Phase 4.2 é o que confirma se o identificador existe.
- **Migrations do `laravel/ai`:** a intenção é não rodá-las. Só a primeira chamada com structured output confirma se `prompt()` funciona sem as tabelas do pacote. O plano já prevê o fallback na Phase 1.1 (SQLite local), sem introduzir container de banco nem tabela de domínio.
- **Identificador do componente na rota:** `Route::livewire('/', 'pages::cron-explainer')` assume a resolução pelo namespace `pages::` do Livewire 4. Se não resolver, a Phase 3.1 manda ajustar o identificador mantendo o caminho do arquivo — não é bloqueio, mas é o ponto do plano com maior chance de precisar de correção na hora.
