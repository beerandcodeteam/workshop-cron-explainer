<?php

use Illuminate\Support\Carbon;

it('formata dia da semana em português', function () {
    $data = Carbon::parse('2026-08-23T05:15:00Z')
        ->setTimezone(config('cron-explainer.timezone'));

    expect($data->translatedFormat('l, d/m/Y H:i'))
        ->toBe('domingo, 23/08/2026 02:15');
});

it('usa o timezone do domínio, não o do framework', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('cron-explainer.timezone'))->toBe('America/Sao_Paulo');
});
