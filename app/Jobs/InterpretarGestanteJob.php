<?php

namespace App\Jobs;

use App\Exceptions\PythonApiException;
use App\Models\AnaliseHistorico;
use App\Models\InterpretacaoClinica;
use App\Services\ClinicalContextBuilder;
use App\Services\PythonApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Após CatBoost, solicita interpretação clínica (Gemini/mock) ao FastAPI.
 * Não altera probabilidade/SHAP — apenas persiste narrativa.
 */ class InterpretarGestanteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $analiseHistoricoId,
    ) {}

    public function uniqueId(): string
    {
        return 'interpretar-analise-'.$this->analiseHistoricoId.'-'.InterpretacaoClinica::PROMPT_VERSION;
    }

    public function uniqueFor(): int
    {
        return 120;
    }

    public function handle(PythonApiService $api, ClinicalContextBuilder $builder): void
    {
        if (! config('services.interpretacao.enabled')) {
            Log::debug('InterpretarGestanteJob: desabilitado (INTERPRETACAO_GEMINI_ENABLED).');

            return;
        }

        $analise = AnaliseHistorico::query()
            ->with(['gestante.consultas', 'consulta'])
            ->find($this->analiseHistoricoId);

        if (! $analise || $analise->status !== 'concluida') {
            Log::info('InterpretarGestanteJob: análise indisponível.', [
                'analise_id' => $this->analiseHistoricoId,
            ]);

            return;
        }

        $gestante = $analise->gestante;
        $consulta = $analise->consulta;
        if (! $gestante || ! $consulta) {
            return;
        }

        $built = $builder->build($gestante, $consulta, $analise);
        $hash = $built['hash'];
        $contexto = $built['contexto'];

        $existente = InterpretacaoClinica::query()
            ->where('analise_historico_id', $analise->id)
            ->where('prompt_version', InterpretacaoClinica::PROMPT_VERSION)
            ->first();

        if ($existente && $existente->contexto_hash === $hash
            && in_array($existente->status, [InterpretacaoClinica::STATUS_CONCLUIDA, InterpretacaoClinica::STATUS_CACHE_HIT], true)) {
            Log::info('InterpretarGestanteJob: cache hit local.', ['analise_id' => $analise->id]);

            return;
        }

        $registro = $existente ?? new InterpretacaoClinica;
        $registro->fill([
            'gestante_id' => $gestante->id,
            'consulta_id' => $consulta->id,
            'analise_historico_id' => $analise->id,
            'status' => InterpretacaoClinica::STATUS_PENDENTE,
            'prompt_version' => InterpretacaoClinica::PROMPT_VERSION,
            'schema_version' => ClinicalContextBuilder::SCHEMA_VERSION,
            'contexto_hash' => $hash,
            'contexto_enviado' => $contexto,
            'erro_mensagem' => null,
        ]);
        $registro->save();

        try {
            $resposta = $api->interpretarGestante($contexto);
            $this->assertNaoAlterouPredicao($contexto, $resposta);
            $this->persistirSucesso($registro, $resposta);
        } catch (Throwable $e) {
            $registro->update([
                'status' => InterpretacaoClinica::STATUS_ERRO,
                'erro_mensagem' => $e->getMessage(),
            ]);
            Log::error('InterpretarGestanteJob: falha.', [
                'analise_id' => $analise->id,
                'mensagem' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  array<string, mixed>  $resposta
     */
    private function assertNaoAlterouPredicao(array $contexto, array $resposta): void
    {
        $pred = $contexto['predicao_catboost'] ?? [];
        $echo = $resposta['predicao_ecoada'] ?? null;
        if (! is_array($echo)) {
            return;
        }

        foreach (['probabilidade_risco', 'classificacao_risco'] as $campo) {
            if (! array_key_exists($campo, $echo)) {
                continue;
            }
            $esperado = $pred[$campo] ?? null;
            $recebido = $echo[$campo];
            if ($campo === 'probabilidade_risco') {
                if ($esperado !== null && abs((float) $esperado - (float) $recebido) > 0.0001) {
                    throw new PythonApiException('LLM/mock tentou alterar probabilidade_risco.', null, $resposta);
                }
            } elseif ((string) $esperado !== (string) $recebido) {
                throw new PythonApiException('LLM/mock tentou alterar classificacao_risco.', null, $resposta);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    private function persistirSucesso(InterpretacaoClinica $registro, array $resposta): void
    {
        $registro->update([
            'status' => ! empty($resposta['cache_hit'])
                ? InterpretacaoClinica::STATUS_CACHE_HIT
                : InterpretacaoClinica::STATUS_CONCLUIDA,
            'model_id' => $resposta['model_id'] ?? 'mock-fase0',
            'resumo_medico' => $resposta['resumo_medico'] ?? null,
            'resumo_gestante' => $resposta['resumo_gestante'] ?? null,
            'fatores_explicados' => $resposta['fatores_explicados'] ?? [],
            'recomendacoes' => $resposta['recomendacoes'] ?? [],
            'deltas' => $resposta['deltas'] ?? [],
            'resposta_bruta' => $resposta,
            'tokens_in' => $resposta['tokens_in'] ?? 0,
            'tokens_out' => $resposta['tokens_out'] ?? 0,
            'latency_ms' => $resposta['latency_ms'] ?? null,
            'mock' => (bool) ($resposta['mock'] ?? true),
            'erro_mensagem' => null,
        ]);
    }
}
