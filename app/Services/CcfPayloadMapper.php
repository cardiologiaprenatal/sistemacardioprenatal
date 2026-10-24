<?php

namespace App\Services;

use App\Models\Consulta;
use App\Models\Gestante;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CcfPayloadMapper
{
    private const ETNIAS_VALIDAS = ['Branca', 'Parda', 'Preta', 'Amarela', 'Indigena'];

    private const CORIONICIDADES_VALIDAS = ['Monocoriônica', 'Dicoriônica', 'NA'];

    /**
     * Converte Consulta + Gestante para o schema PredictionInput da API GestRisk.
     *
     * @return array<string, mixed>
     */
    public function fromConsulta(Consulta $consulta, ?Gestante $gestante = null): array
    {
        $gestante = $gestante ?? $consulta->gestante;

        if (! $gestante) {
            throw new \InvalidArgumentException('Consulta sem gestante vinculada.');
        }

        $exposicao = $this->toInt($consulta->exposicao_ocupacional);
        $doppler = (string) ($consulta->doppler_ducto_venoso ?? '');
        $liquido = mb_strtolower((string) ($consulta->polidramnio_oligoidramnio ?? ''));

        $payload = [
            'id_gestante' => $this->formatIdGestante($gestante),
            'idade_materna' => $this->resolveIdadeMaterna($consulta, $gestante),
            'etnia' => $this->normalizeEtnia($consulta->etnia),
            'imc_pre_gestacional' => $this->clampFloat($consulta->imc_pre_gestacional, 24.0, 16.0, 45.0),
            'paridade' => $this->clampInt($consulta->paridade, 0, 0, 6),
            'abortamentos_previos' => $this->clampInt($consulta->abortamentos_previos, 0, 0, 4),
            'historia_natimorto' => $this->toInt($consulta->historico_natimorto),
            'filho_anterior_cardiopatia' => $this->toInt($consulta->historico_filho_anterior_chd),
            'historia_familiar_cardiopatia' => $this->toInt($consulta->historico_familiar_chd),
            'diabetes_pre_gestacional' => $this->toInt($consulta->diabetes_pre_gestacional),
            'diabetes_gestacional' => $this->toInt($consulta->diabetes_gestacional),
            'lupus' => $this->toInt($consulta->lupus_eritomatoso_sistemico),
            'fenilcetonuria' => $this->toInt($consulta->fenilcetonuria),
            'doenca_tireoide' => $this->toInt($consulta->doencas_tireoidianas),
            'hipertensao_cronica' => $this->toInt($consulta->hipertensao_cronica),
            'rubeola' => $this->toInt($consulta->rubeola),
            'citomegalovirus' => $this->toInt($consulta->citomegalovirus),
            'toxoplasmose' => $this->toInt($consulta->toxoplasmose),
            'sifilis' => $this->toInt($consulta->sifilis),
            'isotretinoina' => $this->toInt($consulta->uso_isotretinoina),
            'acido_valproico' => $this->toInt($consulta->uso_acido_valproico),
            'litio' => $this->toInt($consulta->uso_litio),
            'alcool' => $this->toInt($consulta->alcoolismo),
            'tabagismo' => $this->toInt($consulta->tabagismo),
            'drogas_ilicitas' => $this->toInt($consulta->drogas_ilicitas),
            'solventes' => $exposicao,
            'pesticidas' => $exposicao,
            'idade_gestacional' => $this->clampFloat($consulta->idade_gestacional, 20.0, 11.0, 28.0),
            'gestacao_gemelar' => $this->gestacaoGemelar($consulta->tipo_gestacao),
            'corionicidade' => $this->normalizeCorionicidade($consulta->corionicidade),
            'translucencia_nucal_aumentada' => $this->toInt($consulta->translucencia_nucal_aumentada),
            'alteracao_ducto_venoso' => $this->alteracaoDuctoVenoso($doppler),
            'regurgitacao_tricuspide' => $this->toInt($consulta->regurgitacao_tricuspide_fetal),
            'malformacao_extracardiaca' => $this->toInt($consulta->malformacoes_extracardiacas_associadas),
            'restricao_crescimento_fetal' => $this->toInt($consulta->crescimento_fetal_rcf_iugr),
            'polidramnio' => str_contains($liquido, 'polidram') ? 1 : 0,
            'oligodramnio' => (str_contains($liquido, 'oligodram') || str_contains($liquido, 'oligoidram')) ? 1 : 0,
        ];

        Log::debug('CCF Payload mapeado.', [
            'gestante_id' => $gestante->id,
            'consulta_id' => $consulta->id,
            'id_gestante' => $payload['id_gestante'],
        ]);

        return $payload;
    }

    private function formatIdGestante(Gestante $gestante): string
    {
        $codigo = trim((string) ($gestante->gestante_id ?? ''));

        if ($codigo !== '' && preg_match('/^GEST-/i', $codigo)) {
            return strtoupper($codigo);
        }

        return sprintf('GEST-%05d', $gestante->id);
    }

    private function resolveIdadeMaterna(Consulta $consulta, Gestante $gestante): float
    {
        if ($consulta->idade_materna !== null && $consulta->idade_materna > 0) {
            return $this->clampFloat($consulta->idade_materna, 28.0, 14.0, 48.0);
        }

        if ($gestante->data_nascimento && $consulta->data_consulta) {
            $nascimento = Carbon::parse($gestante->data_nascimento);
            $referencia = Carbon::parse($consulta->data_consulta);
            $anos = $nascimento->diffInYears($referencia, false);

            if ($anos >= 14 && $anos <= 48) {
                return (float) $anos;
            }
        }

        if ($consulta->idade !== null && $consulta->idade >= 14 && $consulta->idade <= 48) {
            return (float) $consulta->idade;
        }

        return 28.0;
    }

    private function normalizeEtnia(mixed $etnia): string
    {
        $valor = trim((string) ($etnia ?? ''));
        $mapa = [
            'indígena' => 'Indigena',
            'indigena' => 'Indigena',
            'branca' => 'Branca',
            'parda' => 'Parda',
            'preta' => 'Preta',
            'amarela' => 'Amarela',
        ];

        $chave = mb_strtolower($valor);
        if (isset($mapa[$chave])) {
            return $mapa[$chave];
        }

        if (in_array($valor, self::ETNIAS_VALIDAS, true)) {
            return $valor;
        }

        return 'Parda';
    }

    private function normalizeCorionicidade(mixed $valor): string
    {
        $texto = trim((string) ($valor ?? ''));

        if ($texto === '') {
            return 'NA';
        }

        foreach (self::CORIONICIDADES_VALIDAS as $opcao) {
            if (mb_strtolower($opcao) === mb_strtolower($texto)) {
                return $opcao;
            }
        }

        if (str_contains(mb_strtolower($texto), 'mono')) {
            return 'Monocoriônica';
        }

        if (str_contains(mb_strtolower($texto), 'di')) {
            return 'Dicoriônica';
        }

        return 'NA';
    }

    private function gestacaoGemelar(mixed $tipoGestacao): int
    {
        $tipo = mb_strtolower(trim((string) ($tipoGestacao ?? '')));

        return str_contains($tipo, 'gemel') ? 1 : 0;
    }

    private function alteracaoDuctoVenoso(string $doppler): int
    {
        if ($doppler === '') {
            return 0;
        }

        $normal = ['ausente', 'fluxo normal', 'normal', 'adequado'];
        $lower = mb_strtolower($doppler);

        foreach ($normal as $termo) {
            if (str_contains($lower, $termo)) {
                return 0;
            }
        }

        return 1;
    }

    private function toInt(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }

        return (int) (bool) $valor;
    }

    private function clampInt(mixed $valor, int $default, int $min, int $max): int
    {
        if ($valor === null || $valor === '') {
            return $default;
        }

        return max($min, min($max, (int) $valor));
    }

    private function clampFloat(mixed $valor, float $default, float $min, float $max): float
    {
        if ($valor === null || $valor === '') {
            return $default;
        }

        $num = (float) $valor;

        return max($min, min($max, $num));
    }
}
