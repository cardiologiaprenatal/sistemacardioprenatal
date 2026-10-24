<?php

namespace App\Services;

use App\Models\Consulta;
use App\Models\Gestante;

/**
 * Converte entidades Laravel no payload clínico completo esperado por POST /predict
 * (36 features do Pipeline CardioPrenatal-ML).
 */
class PythonPredictPayloadMapper
{
    /**
     * @return array<string, int|float|string|null>
     */
    public function fromConsulta(Consulta $consulta, ?Gestante $gestante = null): array
    {
        $gestante = $gestante ?? $consulta->gestante;

        $idade = $consulta->idade_materna ?? $consulta->idade ?? null;
        if ($idade === null && $gestante?->data_nascimento && $consulta->data_consulta) {
            $idade = (int) round(abs($gestante->data_nascimento->diffInYears($consulta->data_consulta)));
        }

        $imc = $consulta->imc_pre_gestacional !== null
            ? (float) $consulta->imc_pre_gestacional
            : 24.0;

        $diabetesPre = (int) ((bool) $consulta->diabetes_pre_gestacional);
        $diabetesGest = (int) ((bool) $consulta->diabetes_gestacional);
        $hipertensao = (int) ((bool) ($consulta->hipertensao || $consulta->hipertensao_cronica));
        $exposicao = (int) ((bool) $consulta->exposicao_ocupacional);
        $gemelar = strtolower((string) ($consulta->tipo_gestacao ?? '')) === 'gemelar' ? 1 : 0;
        $liquido = strtolower((string) ($consulta->polidramnio_oligoidramnio ?? ''));
        $ducto = $this->ductoAlterado($consulta->doppler_ducto_venoso ?? null);
        $corionicidade = $this->corionicidade($consulta->corionicidade ?? null, $gemelar === 1);

        $idadeMaterna = $idade !== null ? (float) $idade : 28.0;
        $idadeGestacional = $consulta->idade_gestacional !== null
            ? (float) $consulta->idade_gestacional
            : 20.0;
        $paridade = $consulta->paridade !== null ? (int) $consulta->paridade : 0;
        $tabagismo = (int) ((bool) $consulta->tabagismo);

        return [
            // Contrato completo (Pipeline /model/info)
            'idade_materna' => $idadeMaterna,
            'etnia' => $this->nullableString($consulta->etnia) ?? 'Branca',
            'imc_pre_gestacional' => $imc,
            'paridade' => $paridade,
            'abortamentos_previos' => (int) ($consulta->abortamentos_previos ?? 0),
            'historia_natimorto' => (int) ((bool) $consulta->historico_natimorto),
            'filho_anterior_cardiopatia' => (int) ((bool) $consulta->historico_filho_anterior_chd),
            'historia_familiar_cardiopatia' => (int) ((bool) $consulta->historico_familiar_chd),
            'diabetes_pre_gestacional' => $diabetesPre,
            'diabetes_gestacional' => $diabetesGest,
            'lupus' => (int) ((bool) $consulta->lupus_eritomatoso_sistemico),
            'fenilcetonuria' => (int) ((bool) $consulta->fenilcetonuria),
            'doenca_tireoide' => (int) ((bool) $consulta->doencas_tireoidianas),
            'hipertensao_cronica' => $hipertensao,
            'rubeola' => (int) ((bool) $consulta->rubeola),
            'citomegalovirus' => (int) ((bool) $consulta->citomegalovirus),
            'toxoplasmose' => (int) ((bool) $consulta->toxoplasmose),
            'sifilis' => (int) ((bool) $consulta->sifilis),
            'isotretinoina' => (int) ((bool) $consulta->uso_isotretinoina),
            'acido_valproico' => (int) ((bool) $consulta->uso_acido_valproico),
            'litio' => (int) ((bool) $consulta->uso_litio),
            'alcool' => (int) ((bool) $consulta->alcoolismo),
            'tabagismo' => $tabagismo,
            'drogas_ilicitas' => (int) ((bool) $consulta->drogas_ilicitas),
            'solventes' => $exposicao,
            'pesticidas' => $exposicao,
            'idade_gestacional' => $idadeGestacional,
            'gestacao_gemelar' => $gemelar,
            'corionicidade' => $corionicidade,
            'translucencia_nucal_aumentada' => (int) ((bool) $consulta->translucencia_nucal_aumentada),
            'alteracao_ducto_venoso' => $ducto,
            'regurgitacao_tricuspide' => (int) ((bool) $consulta->regurgitacao_tricuspide_fetal),
            'malformacao_extracardiaca' => (int) ((bool) $consulta->malformacoes_extracardiacas_associadas),
            'restricao_crescimento_fetal' => (int) ((bool) $consulta->crescimento_fetal_rcf_iugr),
            'polidramnio' => $liquido === 'polidramnio' ? 1 : 0,
            'oligodramnio' => $liquido === 'oligodramnio' ? 1 : 0,

            // Aliases legados (PredictRequest enxuto / fallback heuristic)
            'idade' => $idadeMaterna,
            'imc' => $imc,
            'diabetes' => ($diabetesPre === 1 || $diabetesGest === 1) ? 1 : 0,
            'hipertensao' => $hipertensao,
        ];
    }

    private function ductoAlterado(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $v = strtolower(trim((string) $value));

        return str_contains($v, 'alter') || in_array($v, ['1', 'true', 'sim'], true) ? 1 : 0;
    }

    private function corionicidade(?string $value, bool $gemelar): string
    {
        $v = $this->nullableString($value);
        if ($v !== null) {
            return $v;
        }

        return $gemelar ? 'NA' : 'NA';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim((string) $value);

        return $v === '' || strtolower($v) === 'null' ? null : $v;
    }
}
