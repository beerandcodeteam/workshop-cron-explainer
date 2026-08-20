<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelo de IA
    |--------------------------------------------------------------------------
    |
    | Identificador do modelo usado pelo agente que interpreta as expressões
    | cron. Fica aqui — e não no atributo #[Model] do agente — para poder
    | ser trocado por ambiente, sem qualquer alteração de código.
    |
    */

    'model' => env('CRON_EXPLAINER_MODEL', 'gpt-5.6-terra'),

    /*
    |--------------------------------------------------------------------------
    | Timezone de exibição
    |--------------------------------------------------------------------------
    |
    | Timezone usado para calcular e exibir as próximas execuções. É o único
    | timezone consultado pelo domínio; "app.timezone" permanece no padrão
    | do framework (UTC) e não é usado para exibição.
    |
    */

    'timezone' => env('CRON_EXPLAINER_TIMEZONE', 'America/Sao_Paulo'),

    /*
    |--------------------------------------------------------------------------
    | Quantidade de próximas execuções
    |--------------------------------------------------------------------------
    |
    | Quantas execuções futuras o agente deve devolver. Valor fixo do escopo
    | do produto: a página sempre mostra exatamente cinco execuções.
    |
    */

    'occurrences' => 5,

    /*
    |--------------------------------------------------------------------------
    | Timeout da chamada à IA
    |--------------------------------------------------------------------------
    |
    | Tempo máximo, em segundos, que a aplicação espera pela resposta do
    | provider antes de abortar e exibir a mensagem genérica de falha.
    |
    */

    'timeout' => (int) env('CRON_EXPLAINER_TIMEOUT', 30),

];
