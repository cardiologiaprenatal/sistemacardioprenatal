<?php

namespace App\Services;

use App\Models\AnaliseHistorico;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Models\InterpretacaoClinica;
use Illuminate\Support\Collection;

/**
 * Monta PatientContext v1.1 (Laravel ↔ FastAPI) para interpretação Gemini.

 * Não inclui CPF/telefone/nome completo (LGPD / minimização).
 */
class ClinicalContextBuilder
{
    public const SCHEMA_VERSION = '1.1';

    /**
     * @return array{contexto: array<string, mixed>, hash: string}
     */
    public function build(Gestante $gestante, Consulta $consulta, AnaliseHistorico $analise): array
    {

        $consultas = $gestante->consultas()

            ->orderBy('consulta_numero')

            ->orderBy('id')

            ->get();

        $historico = [];

        foreach ($consultas as $c) {

            $historico[] = [

                'consulta_id' => $c->id,

                'consulta_numero' => $c->consulta_numero,

                'data_consulta' => $c->data_consulta?->format('Y-m-d'),

                'idade_gestacional' => $c->idade_gestacional,

                'imc_pre_gestacional' => $c->imc_pre_gestacional !== null ? (float) $c->imc_pre_gestacional : null,

                'pressao_sistolica' => $c->pressao_sistolica,

                'bpm_materno' => $c->bpm_materno,

                'saturacao' => $c->saturacao,

                'diabetes_gestacional' => (bool) $c->diabetes_gestacional,

                'diabetes_pre_gestacional' => (bool) $c->diabetes_pre_gestacional,

                'hipertensao_cronica' => (bool) $c->hipertensao_cronica,

                'chd_confirmada' => (bool) $c->chd_confirmada,

            ];

        }

        $consultaAnterior = $this->consultaAnterior($consultas, $consulta);

        $predicaoAnterior = AnaliseHistorico::query()

            ->where('gestante_id', $gestante->id)

            ->where('status', 'concluida')

            ->where('id', '!=', $analise->id)

            ->latest('id')

            ->first();

        $interpAnterior = InterpretacaoClinica::query()

            ->where('gestante_id', $gestante->id)

            ->whereIn('status', [InterpretacaoClinica::STATUS_CONCLUIDA, InterpretacaoClinica::STATUS_CACHE_HIT])

            ->where('analise_historico_id', '!=', $analise->id)

            ->latest('id')

            ->first();

        $predicaoAtual = [

            'analise_id' => $analise->id,

            'probabilidade_risco' => $analise->probabilidade_ccf,

            'classificacao_risco' => $analise->classificacao_risco,

            'score' => $analise->score_prioridade !== null

                ? round(((float) $analise->score_prioridade) / 100, 4)

                : $analise->probabilidade_ccf,

            'shap' => is_array($analise->shap) ? $analise->shap : [],

            'recomenda_ecocardiograma' => (bool) $analise->recomenda_ecocardiograma,

            'prioridade_ecocardiograma' => $analise->prioridade_ecocardiograma,

        ];

        $predicaoAnteriorPayload = $predicaoAnterior ? [

            'analise_id' => $predicaoAnterior->id,

            'probabilidade_risco' => $predicaoAnterior->probabilidade_ccf,

            'classificacao_risco' => $predicaoAnterior->classificacao_risco,

            'recomenda_ecocardiograma' => (bool) $predicaoAnterior->recomenda_ecocardiograma,

            'prioridade_ecocardiograma' => $predicaoAnterior->prioridade_ecocardiograma,

            'consulta_id' => $predicaoAnterior->consulta_id,

        ] : null;

        $contexto = [

            'schema_version' => self::SCHEMA_VERSION,

            'gestante' => [

                'id_interno' => $gestante->id,

                'id_publico' => $gestante->gestante_id ?: ('G-'.$gestante->id),

                'idade_materna' => $consulta->idade_materna,

            ],

            'consulta_atual' => $this->consultaSnapshot($consulta),

            'consulta_anterior' => $consultaAnterior ? $this->consultaSnapshot($consultaAnterior) : null,

            'historico_consultas' => $historico,

            'predicao_catboost' => $predicaoAtual,

            'predicao_anterior' => $predicaoAnteriorPayload,

            'deltas_numericos' => $this->calcularDeltasNumericos(

                $consulta,

                $consultaAnterior,

                $predicaoAtual,

                $predicaoAnteriorPayload,

            ),

            'interpretacao_anterior' => $interpAnterior ? [

                'id' => $interpAnterior->id,

                'resumo_medico' => $interpAnterior->resumo_medico,

                'resumo_gestante' => $interpAnterior->resumo_gestante,

                'prompt_version' => $interpAnterior->prompt_version,

            ] : null,

            'politica' => [

                'audiencias' => ['medico', 'gestante'],

                'idioma' => 'pt-BR',

                'proibir_alterar_predicao' => true,

                'mock_permitido' => true,

            ],

        ];

        $hash = hash('sha256', json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['contexto' => $contexto, 'hash' => $hash];

    }

    /**
     * @param  Collection<int, Consulta>  $consultas
     */
    private function consultaAnterior(Collection $consultas, Consulta $atual): ?Consulta
    {

        $anterior = null;

        foreach ($consultas as $c) {

            if ((int) $c->id === (int) $atual->id) {

                return $anterior;

            }

            $anterior = $c;

        }

        return $consultas->count() >= 2

            ? $consultas->slice(-2, 1)->first()

            : null;

    }

    /**
     * @param  array<string, mixed>  $predicaoAtual
     * @param  array<string, mixed>|null  $predicaoAnterior
     * @return list<array{campo: string, anterior: mixed, atual: mixed, delta: float|null, unidade: string|null}>
     */
    private function calcularDeltasNumericos(

        Consulta $atual,

        ?Consulta $anterior,

        array $predicaoAtual,

        ?array $predicaoAnterior,

    ): array {

        if (! $anterior) {

            return [];

        }

        $pares = [

            ['campo' => 'idade_gestacional', 'unidade' => 'semanas'],

            ['campo' => 'pressao_sistolica', 'unidade' => 'mmHg'],

            ['campo' => 'bpm_materno', 'unidade' => 'bpm'],

            ['campo' => 'saturacao', 'unidade' => '%'],

            ['campo' => 'imc_pre_gestacional', 'unidade' => 'kg/m2'],

        ];

        $deltas = [];

        foreach ($pares as $meta) {

            $campo = $meta['campo'];

            $a = $this->toFloatOrNull($anterior->{$campo} ?? null);

            $b = $this->toFloatOrNull($atual->{$campo} ?? null);

            if ($a === null && $b === null) {

                continue;

            }

            $deltas[] = [

                'campo' => $campo,

                'anterior' => $a,

                'atual' => $b,

                'delta' => ($a !== null && $b !== null) ? round($b - $a, 4) : null,

                'unidade' => $meta['unidade'],

            ];

        }

        $probAnt = $this->toFloatOrNull($predicaoAnterior['probabilidade_risco'] ?? null);

        $probAtu = $this->toFloatOrNull($predicaoAtual['probabilidade_risco'] ?? null);

        if ($probAnt !== null || $probAtu !== null) {

            $deltas[] = [

                'campo' => 'probabilidade_risco',

                'anterior' => $probAnt,

                'atual' => $probAtu,

                'delta' => ($probAnt !== null && $probAtu !== null) ? round($probAtu - $probAnt, 6) : null,

                'unidade' => 'fracao',

            ];

        }

        $classeAnt = $predicaoAnterior['classificacao_risco'] ?? null;

        $classeAtu = $predicaoAtual['classificacao_risco'] ?? null;

        if ($classeAnt !== null || $classeAtu !== null) {

            $deltas[] = [

                'campo' => 'classificacao_risco',

                'anterior' => $classeAnt,

                'atual' => $classeAtu,

                'delta' => null,

                'unidade' => null,

            ];

        }

        return $deltas;

    }

    private function toFloatOrNull(mixed $value): ?float
    {

        if ($value === null || $value === '') {

            return null;

        }

        if (! is_numeric($value)) {

            return null;

        }

        return (float) $value;

    }

    /**
     * @return array<string, mixed>
     */
    private function consultaSnapshot(Consulta $c): array
    {

        return [

            'consulta_id' => $c->id,

            'consulta_numero' => $c->consulta_numero,

            'data_consulta' => $c->data_consulta?->format('Y-m-d'),

            'idade_materna' => $c->idade_materna,

            'idade_gestacional' => $c->idade_gestacional,

            'etnia' => $c->etnia,

            'imc_pre_gestacional' => $c->imc_pre_gestacional !== null ? (float) $c->imc_pre_gestacional : null,

            'paridade' => $c->paridade,

            'abortamentos_previos' => $c->abortamentos_previos,

            'historico_natimorto' => (bool) $c->historico_natimorto,

            'historico_filho_anterior_chd' => (bool) $c->historico_filho_anterior_chd,

            'historico_familiar_chd' => (bool) $c->historico_familiar_chd,

            'diabetes_pre_gestacional' => (bool) $c->diabetes_pre_gestacional,

            'diabetes_gestacional' => (bool) $c->diabetes_gestacional,

            'hipertensao_cronica' => (bool) $c->hipertensao_cronica,

            'lupus_eritomatoso_sistemico' => (bool) $c->lupus_eritomatoso_sistemico,

            'pressao_sistolica' => $c->pressao_sistolica,

            'bpm_materno' => $c->bpm_materno,

            'saturacao' => $c->saturacao,

            'temperatura_corporal' => $c->temperatura_corporal,

            'altura' => $c->altura,

            'peso' => $c->peso,

            'tabagismo' => (bool) $c->tabagismo,

            'alcoolismo' => (bool) $c->alcoolismo,

            'translucencia_nucal_aumentada' => (bool) $c->translucencia_nucal_aumentada,

            'regurgitacao_tricuspide_fetal' => (bool) $c->regurgitacao_tricuspide_fetal,

            'crescimento_fetal_rcf_iugr' => (bool) $c->crescimento_fetal_rcf_iugr,

            'tipo_gestacao' => $c->tipo_gestacao,

            'corionicidade' => $c->corionicidade,

        ];

    }
}
