---
paths:
  - 'resources/views/pages/**'
  - 'resources/views/pages/*.test.php'
---

# Pages

## SFC compila no namespace global: nada de `use` para classes globais
O bloco PHP de um single-file component é compilado para uma classe em storage/framework/views/livewire/classes/ sem namespace. Por isso `use Throwable;` / `use UnexpectedValueException;` disparam "The use statement with non-compound name has no effect", que o HandleExceptions do Laravel converte em ErrorException e derruba a página com 500. Referencie classes globais qualificadas: `catch (\Throwable $e)`, `throw new \UnexpectedValueException(...)`. Classes namespaced (Carbon, Log, Lab, Component) continuam via `use` normal.

## Testes co-locados de SFC precisam de phpunit.xml + Pest.php
`make:livewire pages::x --test` grava o teste ao lado do componente (resources/views/pages/⚡x.test.php), fora de tests/. Para a suíte enxergá-lo, o testsuite "Feature" do phpunit.xml inclui `<directory suffix=".test.php">resources/views</directory>` e tests/Pest.php faz `pest()->extend(TestCase::class)->in('Feature', __DIR__.'/../resources/views')`. Ao criar novos componentes com --test, nada mais precisa ser configurado — mas não remova nenhuma das duas entradas.

## SFC fica fora do Larastan e fora do Pint padrão
Os `paths` do phpstan.neon são app/, bootstrap/app.php, config/, database/ e routes/. `resources/views/` NÃO está lá: o bloco PHP do SFC ⚡cron-explainer.blade.php nunca é analisado (verificado empiricamente — um `strlen(42)` dentro de `explicar()` passa batido, o mesmo erro em app/ e config/ é acusado). Não adicione `resources/` aos paths sem aprovação.

O Pint padrão também ignora `*.blade.php`. Se você rodar `pint` apontando direto para o arquivo, ele aplica `fully_qualified_strict_types` e reescreve `\Throwable`/`\UnexpectedValueException` para a forma sem barra — o oposto da convenção do SFC. Rode sempre `vendor/bin/sail bin pint --format agent` sem argumento de path.

Consequência: o único gate real do SFC são os testes co-locados. Ao mexer nele, confie na suíte, não no Pint/Larastan.
