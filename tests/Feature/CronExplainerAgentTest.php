<?php

use App\Ai\Agents\CronExplainer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;

it('instrui as regras do domínio', function () {
    $instrucoes = (string) (new CronExplainer)->instructions();

    expect($instrucoes)
        ->toContain('exatamente 5 campos')
        ->toContain('minuto')
        ->toContain('hora')
        ->toContain('dia-do-mês')
        ->toContain('mês')
        ->toContain('dia-da-semana')
        ->toContain('@hourly')
        ->toContain('@daily')
        ->toContain('@weekly')
        ->toContain('@monthly')
        ->toContain('@yearly')
        ->toContain('exatamente 5 execuções')
        ->toContain('ordem')
        ->toContain('posteriores ao instante atual')
        ->toContain('Y-m-d H:i')
        ->toContain('timezone informado no prompt')
        ->toContain('`valido`: false')
        ->toContain('português do Brasil');
});

it('declara o contrato de saída', function () {
    $agente = new CronExplainer;

    expect($agente)
        ->toBeInstanceOf(Agent::class)
        ->toBeInstanceOf(HasStructuredOutput::class);

    $propriedades = $agente->schema(new JsonSchemaTypeFactory);

    expect(array_keys($propriedades))
        ->toBe(['valido', 'explicacao', 'erro', 'proximas_execucoes']);

    $schema = (new ObjectSchema($propriedades))->toSchema();

    expect($schema['required'])
        ->toBe(['valido', 'explicacao', 'erro', 'proximas_execucoes'])
        ->and($schema['properties']['valido']['type'])->toBe('boolean')
        ->and($schema['properties']['explicacao']['type'])->toBe('string')
        ->and($schema['properties']['erro']['type'])->toBe('string')
        ->and($schema['properties']['proximas_execucoes']['type'])->toBe('array')
        ->and($schema['properties']['proximas_execucoes']['items']['type'])->toBe('string');

    foreach ($schema['properties'] as $propriedade) {
        expect($propriedade)->not->toHaveKey('enum');
    }
});
