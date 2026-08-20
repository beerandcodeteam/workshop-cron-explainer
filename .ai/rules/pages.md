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
