<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\Alerta;
use App\Models\AnaliseHistorico;
use App\Models\Gestante;
use Illuminate\Support\Facades\DB;

class AcompanhamentoSyncService
{
    public function __construct(
        private DashboardAnaliseService $dashboardAnalise,
    ) {}

    /**
     * Cria acompanhamentos/alertas faltantes a partir das últimas análises.
     *
     * @return array{acompanhamentos: int, alertas: int}
     */
    public function syncFromUltimasAnalises(int $limit = 300): array
    {
        $ids = $this->dashboardAnalise->idsUltimasAnalisesPorGestante();
        if ($ids->isEmpty()) {
            return ['acompanhamentos' => 0, 'alertas' => 0];
        }

        $analises = AnaliseHistorico::query()
            ->with('gestante')
            ->whereIn('id', $ids)
            ->where('status', 'concluida')
            ->where(function ($q) {
                $q->where('classificacao_risco', 'alto')
                    ->orWhere('recomenda_ecocardiograma', true);
            })
            ->orderByDesc('score_prioridade')
            ->limit($limit)
            ->get();

        $criados = 0;
        $alertas = 0;

        foreach ($analises as $analise) {
            $result = $this->syncFromAnalise($analise);
            $criados += $result['acompanhamentos'];
            $alertas += $result['alertas'];
        }

        return ['acompanhamentos' => $criados, 'alertas' => $alertas];
    }

    /**
     * @return array{acompanhamentos: int, alertas: int}
     */
    public function syncGestante(Gestante $gestante): array
    {
        $analise = $gestante->ultimaAnalise;
        if (! $analise || $analise->status !== 'concluida') {
            $analise = AnaliseHistorico::query()
                ->where('gestante_id', $gestante->id)
                ->where('status', 'concluida')
                ->latest('id')
                ->first();
        }

        if (! $analise) {
            return ['acompanhamentos' => 0, 'alertas' => 0];
        }

        return $this->syncFromAnalise($analise);
    }

    /**
     * @return array{acompanhamentos: int, alertas: int}
     */
    public function syncFromAnalise(AnaliseHistorico $analise): array
    {
        $criados = 0;
        $alertas = 0;

        if ($analise->classificacao_risco === 'alto') {
            $r = $this->ensureAcompanhamento(
                $analise,
                Acompanhamento::MOTIVO_RISCO_ALTO,
                'urgente',
                'Risco alto. Acompanhamento prioritário',
                'Última análise classificou risco alto'.(
                    $analise->probabilidade_ccf !== null
                        ? ' ('.number_format($analise->probabilidade_ccf * 100, 1, ',', '.').'% de cardiopatia congênita).'
                        : '.'
                ),
                Alerta::TIPO_RISCO,
                Alerta::SEVERIDADE_URGENTE,
            );
            $criados += $r['acompanhamento'] ? 1 : 0;
            $alertas += $r['alerta'] ? 1 : 0;
        }

        if ($analise->recomenda_ecocardiograma) {
            $prio = $analise->prioridade_ecocardiograma ?: 'moderada';
            $r = $this->ensureAcompanhamento(
                $analise,
                Acompanhamento::MOTIVO_ECO_RECOMENDADA,
                $prio,
                'Ecocardiograma recomendado',
                'Prioridade: '.DashboardAnaliseService::labelPrioridadeEco($prio).'.',
                Alerta::TIPO_ECO,
                in_array($prio, ['urgente', 'alta'], true) ? Alerta::SEVERIDADE_URGENTE : Alerta::SEVERIDADE_ATENCAO,
            );
            $criados += $r['acompanhamento'] ? 1 : 0;
            $alertas += $r['alerta'] ? 1 : 0;
        }

        return ['acompanhamentos' => $criados, 'alertas' => $alertas];
    }

    /**
     * @return array{acompanhamento: bool, alerta: bool}
     */
    private function ensureAcompanhamento(
        AnaliseHistorico $analise,
        string $motivo,
        string $prioridade,
        string $titulo,
        string $observacao,
        string $alertaTipo,
        string $severidade,
    ): array {
        $aberto = Acompanhamento::query()
            ->where('gestante_id', $analise->gestante_id)
            ->where('motivo', $motivo)
            ->abertos()
            ->exists();

        if ($aberto) {
            return ['acompanhamento' => false, 'alerta' => false];
        }

        $result = DB::transaction(function () use ($analise, $motivo, $prioridade, $titulo, $observacao, $alertaTipo, $severidade) {
            $acomp = Acompanhamento::create([
                'gestante_id' => $analise->gestante_id,
                'consulta_id' => $analise->consulta_id,
                'analise_historico_id' => $analise->id,
                'status' => Acompanhamento::STATUS_PENDENTE,
                'prioridade' => $prioridade,
                'motivo' => $motivo,
                'titulo' => $titulo,
                'observacao' => $observacao,
            ]);

            $chave = $motivo.':'.$analise->gestante_id.':open';
            $alertaCriado = false;

            $alerta = Alerta::query()->firstOrNew([
                'gestante_id' => $analise->gestante_id,
                'chave' => $chave,
            ]);

            if (! $alerta->exists || $alerta->resolvido_em !== null) {
                $alerta->fill([
                    'acompanhamento_id' => $acomp->id,
                    'tipo' => $alertaTipo,
                    'severidade' => $severidade,
                    'titulo' => $titulo,
                    'mensagem' => $observacao,
                    'lido_em' => null,
                    'resolvido_em' => null,
                    'metadata' => [
                        'analise_historico_id' => $analise->id,
                        'motivo' => $motivo,
                    ],
                ]);
                $alerta->save();
                $alertaCriado = true;
            }

            return ['acompanhamento' => true, 'alerta' => $alertaCriado];
        });

        return $result;
    }

    public function atualizarStatus(
        Acompanhamento $acompanhamento,
        string $status,
        ?string $observacao = null,
        ?string $agendadoPara = null,
        bool $atualizarAgendamento = false,
    ): Acompanhamento {
        $acompanhamento->status = $status;
        if ($observacao !== null && $observacao !== '') {
            $acompanhamento->observacao = $observacao;
        }

        if ($atualizarAgendamento) {
            $acompanhamento->agendado_para = $agendadoPara ?: null;
        }

        if (in_array($status, [Acompanhamento::STATUS_CONCLUIDO, Acompanhamento::STATUS_CANCELADO], true)) {
            $acompanhamento->concluido_em = now();
            $alertas = Alerta::query()
                ->where('acompanhamento_id', $acompanhamento->id)
                ->abertos()
                ->get();

            foreach ($alertas as $alerta) {
                $alerta->resolvido_em = now();
                if ($alerta->chave) {
                    $alerta->chave = $alerta->chave.':closed:'.$alerta->id;
                }
                $alerta->save();
            }
        } elseif ($status === Acompanhamento::STATUS_EM_ANDAMENTO) {
            $acompanhamento->concluido_em = null;
        }

        $acompanhamento->save();

        $gestante = $acompanhamento->gestante ?? Gestante::query()->find($acompanhamento->gestante_id);
        if ($gestante) {
            app(GestanteAtendimentoService::class)->sincronizarFluxo($gestante);
        }

        return $acompanhamento;
    }
}
