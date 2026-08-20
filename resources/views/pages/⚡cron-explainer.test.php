<?php

use App\Ai\Agents\CronExplainer;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

/**
 * Payload válido, no formato exato do contrato do agente.
 *
 * @param  array<int, string>  $execucoes
 * @return array<string, mixed>
 */
function payloadValido(string $explicacao = 'Às 3h de segunda a sexta.', ?array $execucoes = null): array
{
    return [
        'valido' => true,
        'explicacao' => $explicacao,
        'erro' => '',
        'proximas_execucoes' => $execucoes ?? [
            '2026-08-19 03:00',
            '2026-08-20 03:00',
            '2026-08-21 03:00',
            '2026-08-24 03:00',
            '2026-08-25 03:00',
        ],
    ];
}

/**
 * Payload de expressão recusada pelo agente.
 *
 * @return array<string, mixed>
 */
function payloadInvalido(string $erro = 'Campo minuto inválido: 75. Valores aceitos: 0 a 59.'): array
{
    return [
        'valido' => false,
        'explicacao' => '',
        'erro' => $erro,
        'proximas_execucoes' => [],
    ];
}

const MENSAGEM_DE_FALHA = 'Não foi possível interpretar a expressão agora. Tente novamente em instantes.';

it('renderiza o componente na home', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire('pages::cron-explainer');
});

it('envia expressão, instante atual e timezone no prompt', function () {
    $this->freezeTime();

    CronExplainer::fake([payloadValido()]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar');

    CronExplainer::assertPrompted(function ($prompt) {
        return $prompt->contains('0 3 * * 1-5')
            && $prompt->contains(config('cron-explainer.timezone'))
            && $prompt->contains(now(config('cron-explainer.timezone'))->toIso8601String());
    });
});

it('usa o provider, o modelo e o timeout configurados', function () {
    CronExplainer::fake([payloadValido()]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '@daily')
        ->call('explicar');

    CronExplainer::assertPrompted(function ($prompt) {
        return $prompt->provider->name() === 'openai'
            && $prompt->model === config('cron-explainer.model')
            && $prompt->timeout === config('cron-explainer.timeout');
    });
});

it('renderiza explicação e as 5 execuções', function () {
    CronExplainer::fake([payloadValido('A cada dia útil, às 3h.')]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('explicacao', 'A cada dia útil, às 3h.')
        ->assertCount('proximasExecucoes', 5)
        ->assertSee('A cada dia útil, às 3h.')
        ->assertSee('quarta-feira, 19/08/2026 03:00')
        ->assertSee('quinta-feira, 20/08/2026 03:00')
        ->assertSee('sexta-feira, 21/08/2026 03:00')
        ->assertSee('segunda-feira, 24/08/2026 03:00')
        ->assertSee('terça-feira, 25/08/2026 03:00');
});

it('exibe o erro e nenhuma execução quando a expressão é inválida', function () {
    CronExplainer::fake([payloadInvalido()]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '75 3 * * *')
        ->call('explicar')
        ->assertSet('explicacao', null)
        ->assertSet('erro', 'Campo minuto inválido: 75. Valores aceitos: 0 a 59.')
        ->assertSet('proximasExecucoes', [])
        ->assertSet('falhaDeIa', false)
        ->assertSee('Campo minuto inválido: 75. Valores aceitos: 0 a 59.')
        ->assertDontSee('Próximas execuções')
        ->assertSet('expressao', '75 3 * * *');
});

it('substitui o resultado anterior a cada submissão', function () {
    CronExplainer::fake([
        payloadValido('Primeira explicação.'),
        payloadInvalido('Campo hora inválido: 99. Valores aceitos: 0 a 23.'),
    ]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('explicacao', 'Primeira explicação.')
        ->set('expressao', '0 99 * * *')
        ->call('explicar')
        ->assertSet('explicacao', null)
        ->assertSet('proximasExecucoes', [])
        ->assertSet('erro', 'Campo hora inválido: 99. Valores aceitos: 0 a 23.')
        ->assertDontSee('Primeira explicação.');
});

it('bloqueia submissão vazia sem chamar a IA', function () {
    CronExplainer::fake();

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '')
        ->call('explicar')
        ->assertHasErrors(['expressao' => 'required'])
        ->assertSee('Digite uma expressão cron para explicar.');

    CronExplainer::assertNeverPrompted();
});

it('trata espaços em branco como vazio', function () {
    CronExplainer::fake();

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '   ')
        ->call('explicar')
        ->assertHasErrors(['expressao' => 'required'])
        ->assertSee('Digite uma expressão cron para explicar.');

    CronExplainer::assertNeverPrompted();
});

it('limpa a mensagem de obrigatório quando o visitante digita', function () {
    CronExplainer::fake();

    Livewire::test('pages::cron-explainer')
        ->call('explicar')
        ->assertHasErrors('expressao')
        ->set('expressao', '0 3 * * 1-5')
        ->assertHasNoErrors('expressao');

    CronExplainer::assertNeverPrompted();
});

it('não aplica validação de formato cron no cliente', function () {
    CronExplainer::fake([payloadInvalido('Expressão malformada.')]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', 'isto não é cron')
        ->call('explicar')
        ->assertHasNoErrors('expressao');

    CronExplainer::assertPrompted(fn ($prompt) => $prompt->contains('isto não é cron'));
});

it('não exibe nenhum estado de resultado antes da primeira submissão', function () {
    Livewire::test('pages::cron-explainer')
        ->assertSet('explicacao', null)
        ->assertSet('erro', null)
        ->assertSet('proximasExecucoes', [])
        ->assertSet('falhaDeIa', false)
        ->assertDontSee('Próximas execuções')
        ->assertDontSee('Expressão inválida')
        ->assertDontSee(MENSAGEM_DE_FALHA);
});

it('mostra mensagem genérica quando a IA falha', function () {
    Log::spy();

    CronExplainer::fake(function () {
        throw new RuntimeException('cURL error 28: Operation timed out');
    });

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('falhaDeIa', true)
        ->assertSet('explicacao', null)
        ->assertSet('erro', null)
        ->assertSet('proximasExecucoes', [])
        ->assertSee(MENSAGEM_DE_FALHA);

    Log::shouldHaveReceived('error')->once();
});

it('mostra a mesma mensagem genérica para payload fora do contrato', function () {
    Log::spy();

    CronExplainer::fake([['valido' => true]]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('falhaDeIa', true)
        ->assertSee(MENSAGEM_DE_FALHA);
});

it('mostra a mesma mensagem genérica para data impossível de parsear', function () {
    Log::spy();

    CronExplainer::fake([payloadValido(execucoes: [
        'nunca',
        'nunca',
        'nunca',
        'nunca',
        'nunca',
    ])]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('falhaDeIa', true)
        ->assertSee(MENSAGEM_DE_FALHA);
});

it('não vaza detalhe técnico nem a chave da API', function () {
    config(['ai.providers.openai.key' => 'sk-chave-secreta-de-teste']);

    Log::spy();

    CronExplainer::fake(function () {
        throw new RuntimeException('Incorrect API key provided: sk-chave-secreta-de-teste');
    });

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertDontSee('sk-chave-secreta-de-teste')
        ->assertDontSee('RuntimeException')
        ->assertDontSee('Incorrect API key provided')
        ->assertSee(MENSAGEM_DE_FALHA);
});

it('preserva o input após falha', function () {
    Log::spy();

    CronExplainer::fake(function () {
        throw new RuntimeException('Falha de rede.');
    });

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('expressao', '0 3 * * 1-5');
});

it('mantém o comportamento de falha com APP_DEBUG ligado', function () {
    config(['app.debug' => true]);

    Log::spy();

    CronExplainer::fake(function () {
        throw new RuntimeException('Falha de rede com debug ligado.');
    });

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('falhaDeIa', true)
        ->assertSee(MENSAGEM_DE_FALHA)
        ->assertDontSee('Falha de rede com debug ligado.');
});
