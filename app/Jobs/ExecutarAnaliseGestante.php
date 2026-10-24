<?php

namespace App\Jobs;

use App\Exceptions\PythonApiException;
use App\Models\AnaliseHistorico;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Services\PythonApiService;
use App\Services\PythonPredictPayloadMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Análise unitária: consulta MySQL → API Python (/predict) → analises_historico.
 */
class ExecutarAnaliseGestante implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [15, 60, 120];

    public function __construct(
        public int $gestanteId,
        public ?int $consultaId = null,
        public string $eventoTipo = AnaliseHistorico::EVENTO_CONSULTA,
    ) {}

    public function uniqueId(): string
    {
        return sprintf('analise-gestante-%d-%d-%s', $this->gestanteId, $this->consultaId ?? 0, $this->eventoTipo);
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function handle(PythonApiService $api, PythonPredictPayloadMapper $mapper): void
    {
        $gestante = Gestante::find($this->gestanteId);
        if (! $gestante) {
            Log::warning('ExecutarAnaliseGestante: gestante não encontrada.', [
                'gestante_id' => $this->gestanteId,
            ]);

            return;
        }

        $consulta = $this->resolveConsulta($gestante);
        if (! $consulta) {
            Log::info('ExecutarAnaliseGestante: sem consulta.', [
                'gestante_id' => $this->gestanteId,
            ]);

            return;
        }

        try {
            $predicao = $this->inferir($api, $mapper, $gestante, $consulta);
            $analise = $this->persistirSucesso($gestante, $consulta, $predicao);
            $this->dispararInterpretacao($analise);
        } catch (Throwable $e) {
            $this->persistirErro($gestante, $consulta, $e);
            throw $e;
        }
    }

    private function dispararInterpretacao(?AnaliseHistorico $analise): void
    {
        if (! $analise || $analise->status !== 'concluida') {
            return;
        }

        if (! config('services.interpretacao.enabled')) {
            return;
        }

        InterpretarGestanteJob::dispatch($analise->id);
    }

    private function resolveConsulta(Gestante $gestante): ?Consulta
    {
        if ($this->consultaId) {
            return Consulta::query()
                ->where('gestante_id', $gestante->id)
                ->whereKey($this->consultaId)
                ->first();
        }

        return $gestante->consultas()->latest('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function inferir(
        PythonApiService $api,
        PythonPredictPayloadMapper $mapper,
        Gestante $gestante,
        Consulta $consulta,
    ): array {
        $codigo = strtoupper(trim((string) $gestante->gestante_id));

        if (preg_match('/^GEST-\d+$/', $codigo) === 1) {
            try {
                return $api->predictById($codigo);
            } catch (PythonApiException $e) {
                Log::warning('ExecutarAnaliseGestante: predictById falhou, tentando /predict JSON.', [
                    'id_gestante' => $codigo,
                    'mensagem' => $e->getMessage(),
                ]);
            }
        }

        return $api->predict($mapper->fromConsulta($consulta, $gestante));
    }

    /**
     * @param  array<string, mixed>  $predicao
     */
    private function persistirSucesso(Gestante $gestante, Consulta $consulta, array $predicao): AnaliseHistorico
    {
        $riscoRaw = (string) ($predicao['classificacao_risco'] ?? $predicao['risk'] ?? 'Baixo');
        $riscoSlug = match (mb_strtolower($riscoRaw)) {
            'alto' => 'alto',
            'moderado' => 'moderado',
            default => 'baixo',
        };

        $prob = (float) ($predicao['probabilidade_risco'] ?? $predicao['probability'] ?? 0);
        $class = isset($predicao['class']) ? (int) $predicao['class'] : ($riscoSlug === 'alto' ? 1 : 0);
        $recomendaEco = $class === 1 || $riscoSlug === 'alto' || $riscoSlug === 'moderado';

        $prioridade = match ($riscoSlug) {
            'alto' => $prob >= 0.7 ? 'urgente' : 'alta',
            'moderado' => 'moderada',
            default => 'baixa',
        };

        $fonte = is_string($predicao['fonte'] ?? null) ? $predicao['fonte'] : null;

        return AnaliseHistorico::create([
            'gestante_id' => $gestante->id,
            'consulta_id' => $consulta->id,
            'evento_tipo' => $this->eventoTipo,
            'probabilidade_ccf' => $prob,
            'classificacao_risco' => $riscoSlug,
            'score_prioridade' => round($prob * 100, 2),
            'prioridade_ecocardiograma' => $prioridade,
            'recomenda_ecocardiograma' => $recomendaEco,
            'shap' => is_array($predicao['shap'] ?? null) ? $predicao['shap'] : [],
            'predicao' => $predicao,
            'analise' => [
                'fonte' => $fonte,
                'modelo' => $predicao['modelo'] ?? null,
                'experimento' => $predicao['experimento'] ?? null,
                'modo_inferencia' => $predicao['modo_inferencia'] ?? $fonte,
                'fallback_motivo' => $predicao['fallback_motivo'] ?? null,
            ],
            'full_response' => $predicao,
            'status' => 'concluida',
        ]);
    }

    private function persistirErro(Gestante $gestante, Consulta $consulta, Throwable $e): void
    {
        Log::error('ExecutarAnaliseGestante: falha.', [
            'gestante_id' => $gestante->id,
            'consulta_id' => $consulta->id,
            'mensagem' => $e->getMessage(),
        ]);

        AnaliseHistorico::create([
            'gestante_id' => $gestante->id,
            'consulta_id' => $consulta->id,
            'evento_tipo' => $this->eventoTipo,
            'full_response' => [],
            'status' => 'erro',
            'erro_mensagem' => $e->getMessage(),
        ]);
    }
}
