<?php

use App\Ai\Agents\CronExplainer;
use Livewire\Livewire;

/**
 * Trava o ambiente da suíte: nenhum teste pode alcançar a API real da OpenAI.
 *
 * O phpunit.xml zera OPENAI_API_KEY com force="true" justamente para que a chave
 * do .env — ou uma exportada no shell — nunca chegue aos testes. Sem essa trava,
 * qualquer chamada que escapasse de um fake viraria requisição paga de verdade.
 */
it('roda sem chave da OpenAI configurada', function () {
    expect(env('OPENAI_API_KEY'))->toBe('')
        ->and(config('ai.providers.openai.key'))->toBe('');
});

it('não alcança a API real quando o agente está fakeado', function () {
    CronExplainer::fake([[
        'valido' => true,
        'explicacao' => 'A cada dia útil, às 3h.',
        'erro' => '',
        'proximas_execucoes' => [
            '2026-08-19 03:00',
            '2026-08-20 03:00',
            '2026-08-21 03:00',
            '2026-08-24 03:00',
            '2026-08-25 03:00',
        ],
    ]]);

    Livewire::test('pages::cron-explainer')
        ->set('expressao', '0 3 * * 1-5')
        ->call('explicar')
        ->assertSet('falhaDeIa', false)
        ->assertSet('explicacao', 'A cada dia útil, às 3h.');
});
