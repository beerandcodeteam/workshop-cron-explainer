<?php

use App\Ai\Agents\CronExplainer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Livewire\Component;

/**
 * Página única do Cron Explainer.
 *
 * Toda a lógica vive aqui: não há Action nem service class. O componente monta o
 * prompt, chama o agente e traduz a resposta estruturada em estado de tela. Nada
 * é gravado em banco, sessão ou cache — cada submissão substitui a anterior.
 */
new class extends Component
{
    /**
     * Mensagem única exibida para qualquer falha da chamada à IA — rede, timeout,
     * chave ausente ou inválida, payload fora do contrato e data não parseável.
     */
    public const MENSAGEM_DE_FALHA = 'Não foi possível interpretar a expressão agora. Tente novamente em instantes.';

    /**
     * A expressão cron digitada pelo visitante.
     */
    public string $expressao = '';

    /**
     * Explicação devolvida pela IA. Nula enquanto não houver resultado válido.
     */
    public ?string $explicacao = null;

    /**
     * Motivo da invalidez devolvido pela IA. Nulo enquanto a expressão não for recusada.
     */
    public ?string $erro = null;

    /**
     * As próximas execuções, já no timezone de exibição.
     *
     * @var array<int, Carbon>
     */
    public array $proximasExecucoes = [];

    /**
     * Se a última submissão terminou em falha da chamada à IA.
     */
    public bool $falhaDeIa = false;

    /**
     * Limpa a mensagem de campo obrigatório assim que o visitante digita conteúdo.
     */
    public function updatedExpressao(string $value): void
    {
        if (trim($value) !== '') {
            $this->resetErrorBag('expressao');
        }
    }

    /**
     * Interpreta a expressão digitada e publica o resultado no estado do componente.
     */
    public function explicar(): void
    {
        $this->explicacao = null;
        $this->erro = null;
        $this->proximasExecucoes = [];
        $this->falhaDeIa = false;

        $this->expressao = trim($this->expressao);

        $this->validate(
            ['expressao' => ['required', 'string']],
            ['expressao.required' => 'Digite uma expressão cron para explicar.'],
        );

        $timezone = config('cron-explainer.timezone');

        try {
            $resposta = (new CronExplainer)->prompt(
                $this->montarPrompt($timezone),
                provider: Lab::OpenAI,
                model: config('cron-explainer.model'),
                timeout: config('cron-explainer.timeout'),
            );

            $resultado = $this->validarPayload($resposta->toArray());

            if ($resultado['valido'] === false) {
                $this->erro = $resultado['erro'];

                return;
            }

            $this->explicacao = $resultado['explicacao'];
            $this->proximasExecucoes = array_map(
                fn (string $execucao): Carbon => Carbon::parse($execucao, $timezone),
                $resultado['proximas_execucoes'],
            );
        } catch (\Throwable $e) {
            Log::error('Falha ao interpretar expressão cron.', ['exception' => $e]);

            $this->explicacao = null;
            $this->erro = null;
            $this->proximasExecucoes = [];
            $this->falhaDeIa = true;
        }
    }

    /**
     * Monta o prompt com a expressão, o instante atual em ISO-8601 e o timezone.
     */
    protected function montarPrompt(string $timezone): string
    {
        $agora = Carbon::now($timezone)->toIso8601String();

        return <<<PROMPT
        Expressão cron: {$this->expressao}
        Instante atual: {$agora}
        Timezone: {$timezone}
        PROMPT;
    }

    /**
     * Garante que a resposta respeita o contrato do agente antes de virar estado de tela.
     *
     * @param  array<string, mixed>  $payload
     * @return array{valido: bool, explicacao: string, erro: string, proximas_execucoes: array<int, string>}
     *
     * @throws \UnexpectedValueException
     */
    protected function validarPayload(array $payload): array
    {
        foreach (['valido', 'explicacao', 'erro', 'proximas_execucoes'] as $chave) {
            if (! array_key_exists($chave, $payload)) {
                throw new \UnexpectedValueException("Resposta da IA sem a chave [{$chave}].");
            }
        }

        if (! is_bool($payload['valido'])
            || ! is_string($payload['explicacao'])
            || ! is_string($payload['erro'])
            || ! is_array($payload['proximas_execucoes'])) {
            throw new \UnexpectedValueException('Resposta da IA fora dos tipos do contrato.');
        }

        $execucoes = array_values($payload['proximas_execucoes']);

        foreach ($execucoes as $execucao) {
            if (! is_string($execucao)) {
                throw new \UnexpectedValueException('Execução devolvida pela IA não é uma string.');
            }
        }

        $esperadas = (int) config('cron-explainer.occurrences');

        if ($payload['valido'] === true && count($execucoes) !== $esperadas) {
            throw new \UnexpectedValueException("Esperadas {$esperadas} execuções, recebidas ".count($execucoes).'.');
        }

        return [
            'valido' => $payload['valido'],
            'explicacao' => $payload['explicacao'],
            'erro' => $payload['erro'],
            'proximas_execucoes' => $execucoes,
        ];
    }
};
?>

<div class="w-full space-y-8">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl dark:text-zinc-50">
            Cron Explainer
        </h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Escreva uma expressão cron de cinco campos e veja, em português, o que ela faz e quando roda.
        </p>
    </header>

    <form wire:submit="explicar" class="space-y-4">
        <div class="space-y-2">
            <label for="expressao" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Expressão cron
            </label>

            <input
                id="expressao"
                type="text"
                wire:model.live.debounce.300ms="expressao"
                placeholder="0 3 * * 1-5"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono text-base text-zinc-900 shadow-sm placeholder:text-zinc-400 focus:border-zinc-500 focus:ring-2 focus:ring-zinc-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-600 dark:focus:border-zinc-400 dark:focus:ring-zinc-400/30"
            >

            @error('expressao')
                <p class="text-sm text-amber-700 dark:text-amber-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="explicar"
                class="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 focus:ring-2 focus:ring-zinc-500/40 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
            >
                Explicar
            </button>

            <span
                wire:loading
                wire:target="explicar"
                class="inline-flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400"
            >
                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                Interpretando a expressão...
            </span>
        </div>
    </form>

    @if ($explicacao !== null)
        <section class="space-y-5 rounded-xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/40">
            <p class="text-lg font-medium text-emerald-900 dark:text-emerald-100">
                {{ $explicacao }}
            </p>

            <div class="space-y-2">
                <h2 class="text-sm font-semibold text-emerald-800 uppercase dark:text-emerald-300">
                    Próximas execuções
                </h2>

                <ul class="divide-y divide-emerald-200 rounded-lg border border-emerald-200 bg-white dark:divide-emerald-900 dark:border-emerald-900 dark:bg-zinc-900">
                    @foreach ($proximasExecucoes as $execucao)
                        <li wire:key="execucao-{{ $loop->index }}" class="px-4 py-2 text-sm text-zinc-800 dark:text-zinc-200">
                            {{ $execucao->translatedFormat('l, d/m/Y H:i') }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @elseif ($erro !== null)
        <section class="rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40">
            <h2 class="text-sm font-semibold text-amber-800 uppercase dark:text-amber-300">
                Expressão inválida
            </h2>

            <p class="mt-2 text-base text-amber-900 dark:text-amber-100">{{ $erro }}</p>
        </section>
    @elseif ($falhaDeIa)
        <section class="rounded-xl border border-zinc-300 bg-zinc-100 p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold text-zinc-700 uppercase dark:text-zinc-300">
                Falha temporária
            </h2>

            <p class="mt-2 text-base text-zinc-800 dark:text-zinc-200">{{ $this::MENSAGEM_DE_FALHA }}</p>
        </section>
    @endif
</div>
