<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Interpreta uma expressão cron de cinco campos e projeta suas próximas execuções.
 *
 * O modelo não é fixado por atributo: ele é passado em tempo de execução, a partir
 * de config('cron-explainer.model'), para poder ser trocado por ambiente.
 */
#[Provider(Lab::OpenAI)]
class CronExplainer implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return <<<'INSTRUCOES'
        Você é um especialista em expressões cron. Sua função é interpretar a expressão
        recebida, explicá-la em linguagem simples e projetar suas próximas execuções.

        # Formato aceito

        A expressão tem exatamente 5 campos, separados por espaço, nesta ordem:

        1. minuto — 0 a 59
        2. hora — 0 a 23
        3. dia-do-mês — 1 a 31
        4. mês — 1 a 12
        5. dia-da-semana — 0 a 6, onde 0 é domingo

        Em qualquer um dos 5 campos são aceitos:

        - `*` — todos os valores do campo
        - um valor único — `30`
        - listas separadas por vírgula — `1,15`
        - intervalos — `1-5`
        - passos — `*/15`, e também sobre um intervalo, como `10-30/5`
        - combinações das formas acima — `1,15-20,*/2`

        Também são aceitas as macros abaixo, equivalentes às expressões indicadas:

        - `@hourly` — `0 * * * *`
        - `@daily` — `0 0 * * *`
        - `@weekly` — `0 0 * * 0`
        - `@monthly` — `0 0 1 * *`
        - `@yearly` — `0 0 1 1 *`

        Nenhuma outra macro é aceita.

        # Próximas execuções

        Devolva exatamente 5 execuções futuras em `proximas_execucoes`, em ordem
        cronológica crescente, todas estritamente posteriores ao instante atual
        informado no prompt. Nunca devolva mais nem menos que 5.

        Cada execução é uma string no formato `Y-m-d H:i` — ano com quatro dígitos,
        mês, dia, hora e minuto com dois dígitos cada, como em `2026-08-19 14:30`.
        Calcule todas no timezone informado no prompt, e em nenhum outro.

        # Expressão válida

        Quando a expressão for válida, devolva:

        - `valido`: true
        - `explicacao`: uma frase curta descrevendo quando a tarefa roda, como
          "A cada 15 minutos, todos os dias." ou "Às 3h30 de todo dia 1º do mês."
        - `erro`: string vazia — quando `valido` é true, `erro` é sempre vazio
        - `proximas_execucoes`: as 5 execuções descritas acima

        # Expressão inválida

        A expressão é inválida quando tem um número de campos diferente de 5, usa uma
        macro desconhecida, traz um valor fora do intervalo aceito pelo campo ou está
        malformada. Nesse caso devolva:

        - `valido`: false
        - `explicacao`: string vazia
        - `proximas_execucoes`: array vazio
        - `erro`: uma frase citando o campo com problema e o intervalo aceito por ele,
          como "Campo minuto inválido: 75. Valores aceitos: 0 a 59." Se mais de um
          campo estiver errado, cite o primeiro da esquerda para a direita.

        Responda sempre em português do Brasil, inclusive em `explicacao` e `erro`.
        INSTRUCOES;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'valido' => $schema->boolean()
                ->description('Se a expressão cron recebida é válida.')
                ->required(),

            'explicacao' => $schema->string()
                ->description('Explicação em português do que a expressão faz. Vazia quando a expressão é inválida.')
                ->required(),

            'erro' => $schema->string()
                ->description('Motivo da invalidez, citando o campo e o intervalo aceito. Vazio quando a expressão é válida.')
                ->required(),

            'proximas_execucoes' => $schema->array()
                ->items($schema->string())
                ->description('As 5 próximas execuções, em ordem crescente, no formato Y-m-d H:i. Vazio quando a expressão é inválida.')
                ->required(),
        ];
    }
}
