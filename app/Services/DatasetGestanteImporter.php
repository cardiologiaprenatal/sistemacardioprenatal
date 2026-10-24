<?php

namespace App\Services;

use App\Models\Consulta;
use App\Models\Gestante;
use Carbon\Carbon;

/**
 * Upsert de gestante + consulta a partir de uma linha clínica
 * (CSV/XLSX ML ou payload da API Python /gestantes/{id}).
 */
class DatasetGestanteImporter
{
    /**
     * @param  array<string, mixed>  $linha
     * @return 'created'|'updated'
     */
    public function upsert(array $linha): string
    {
        $codigo = strtoupper(trim((string) ($linha['id_gestante'] ?? '')));
        if ($codigo === '') {
            throw new \InvalidArgumentException('id_gestante vazio');
        }

        $idade = $this->toFloat($linha['idade_materna'] ?? $linha['idade'] ?? null) ?? 28.0;

        $gestante = Gestante::query()->where('gestante_id', $codigo)->first();
        $created = false;

        if (! $gestante) {
            $gestante = new Gestante;
            $gestante->nome = 'Gestante '.$codigo;
            $gestante->data_nascimento = Carbon::today()->subYears((int) round($idade));
            $gestante->telefone = null;
            $gestante->cpf = null;
            $gestante->save();
            $gestante->forceFill(['gestante_id' => $codigo])->saveQuietly();
            $created = true;
        }

        $clinicos = $this->mapearClinicos($linha, $idade);
        $clinicos['gestante_id'] = $gestante->id;
        $clinicos['consulta_numero'] = 1;

        $existing = Consulta::query()
            ->where('gestante_id', $gestante->id)
            ->where('consulta_numero', 1)
            ->first();

        if ($existing) {
            $existing->fill($clinicos)->save();

            return $created ? 'created' : 'updated';
        }

        Consulta::create(array_merge(
            $this->defaultsVitais($clinicos['imc_pre_gestacional'] ?? null, (bool) ($clinicos['hipertensao'] ?? false), (bool) ($clinicos['translucencia_nucal_aumentada'] ?? false)),
            $clinicos,
            ['data_consulta' => Carbon::today()->toDateString()],
        ));

        return $created ? 'created' : 'updated';
    }

    /**
     * Campos que vêm do CSV/XLSX. Não inclui vitais inventados nem data_consulta.
     *
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>
     */
    private function mapearClinicos(array $linha, float $idade): array
    {
        $poli = $this->toBool($linha['polidramnio'] ?? null);
        $oligo = $this->toBool($linha['oligodramnio'] ?? null);
        $liquido = null;
        if ($poli) {
            $liquido = 'polidramnio';
        } elseif ($oligo) {
            $liquido = 'oligodramnio';
        }

        $gemelar = $this->toBool($linha['gestacao_gemelar'] ?? null);
        $exposicao = $this->toBool($linha['solventes'] ?? null) || $this->toBool($linha['pesticidas'] ?? null);
        $hipertensao = $this->toBool($linha['hipertensao_cronica'] ?? $linha['hipertensao'] ?? null);
        $chd = $this->toBool($linha['cardiopatia_congenita'] ?? null);
        $ducto = $this->toBool($linha['alteracao_ducto_venoso'] ?? null);
        $imc = $this->toFloat($linha['imc_pre_gestacional'] ?? $linha['imc'] ?? null);
        $idadeGestacional = (int) round($this->toFloat($linha['idade_gestacional'] ?? null) ?? 20);
        $usoMeds = $this->toBool($linha['isotretinoina'] ?? null)
            || $this->toBool($linha['acido_valproico'] ?? null)
            || $this->toBool($linha['litio'] ?? null);

        $diabetesGestacional = $this->toBool($linha['diabetes_gestacional'] ?? null);
        if (! array_key_exists('diabetes_gestacional', $linha) && array_key_exists('diabetes', $linha)) {
            $diabetesGestacional = $this->toBool($linha['diabetes']);
        }

        return [
            'idade_materna' => (int) round($idade),
            'idade_gestacional' => $idadeGestacional,
            'etnia' => $this->nullableString($linha['etnia'] ?? null),
            'imc_pre_gestacional' => $imc,
            'paridade' => (int) round($this->toFloat($linha['paridade'] ?? null) ?? 0),
            'abortamentos_previos' => (int) round($this->toFloat($linha['abortamentos_previos'] ?? null) ?? 0),
            'historico_natimorto' => $this->toBool($linha['historia_natimorto'] ?? null),
            'historico_filho_anterior_chd' => $this->toBool($linha['filho_anterior_cardiopatia'] ?? null),
            'historico_familiar_chd' => $this->toBool($linha['historia_familiar_cardiopatia'] ?? null),
            'diabetes_pre_gestacional' => $this->toBool($linha['diabetes_pre_gestacional'] ?? null),
            'diabetes_gestacional' => $diabetesGestacional,
            'hipertensao' => $hipertensao,
            'hipertensao_cronica' => $hipertensao,
            'hipertensao_pre_eclampsia' => false,
            'obesidade_pre_gestacional' => $imc !== null && $imc >= 30,
            'uso_medicamentos' => $usoMeds,
            'lupus_eritomatoso_sistemico' => $this->toBool($linha['lupus'] ?? null),
            'fenilcetonuria' => $this->toBool($linha['fenilcetonuria'] ?? null),
            'doencas_tireoidianas' => $this->toBool($linha['doenca_tireoide'] ?? null),
            'rubeola' => $this->toBool($linha['rubeola'] ?? null),
            'citomegalovirus' => $this->toBool($linha['citomegalovirus'] ?? null),
            'toxoplasmose' => $this->toBool($linha['toxoplasmose'] ?? null),
            'sifilis' => $this->toBool($linha['sifilis'] ?? null),
            'uso_isotretinoina' => $this->toBool($linha['isotretinoina'] ?? null),
            'uso_acido_valproico' => $this->toBool($linha['acido_valproico'] ?? null),
            'uso_litio' => $this->toBool($linha['litio'] ?? null),
            'tabagismo' => $this->toBool($linha['tabagismo'] ?? null),
            'alcoolismo' => $this->toBool($linha['alcool'] ?? null),
            'drogas_ilicitas' => $this->toBool($linha['drogas_ilicitas'] ?? null),
            'exposicao_ocupacional' => $exposicao,
            'tipo_gestacao' => $gemelar ? 'gemelar' : 'unica',
            'corionicidade' => $this->nullableString($linha['corionicidade'] ?? null) ?: ($gemelar ? null : 'NA'),
            'translucencia_nucal_aumentada' => $this->toBool($linha['translucencia_nucal_aumentada'] ?? null),
            'doppler_ducto_venoso' => $ducto ? 'alterado' : 'fluxo normal',
            'regurgitacao_tricuspide_fetal' => $this->toBool($linha['regurgitacao_tricuspide'] ?? null),
            'malformacoes_extracardiacas_associadas' => $this->toBool($linha['malformacao_extracardiaca'] ?? null),
            'crescimento_fetal_rcf_iugr' => $this->toBool($linha['restricao_crescimento_fetal'] ?? null),
            'polidramnio_oligoidramnio' => $liquido,
            'chd_confirmada' => $chd,
        ];
    }

    /**
     * Preenche colunas NOT NULL só na criação da consulta.
     *
     * @return array<string, mixed>
     */
    private function defaultsVitais(?float $imc, bool $hipertensao, bool $tnAumentada): array
    {
        $alturaCm = 162;
        $pesoKg = $imc !== null ? round($imc * (($alturaCm / 100) ** 2), 2) : 65.0;

        return [
            'pressao_sistolica' => $hipertensao ? 142 : 118,
            'bpm_materno' => 78,
            'saturacao' => 98,
            'temperatura_corporal' => 36.6,
            'altura' => $alturaCm,
            'peso' => $pesoKg,
            'frequencia_cardiaca_fetal' => 140,
            'circunferencia_cefalica_fetal_mm' => 250,
            'circunferencia_abdominal_mm' => 220,
            'comprimento_femur_mm' => 48,
            'translucencia_nucal_mm' => $tnAumentada ? 4 : 2,
            'eixo_cardiaco' => 'Normal',
            'quatro_camaras' => 'Visualização adequada',
        ];
    }

    /**
     * Normaliza payloads da API (lista resumida, detalhe com dados_clinicos, sync Laravel).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeApiPayload(array $payload): array
    {
        $clinicos = [];
        if (isset($payload['dados_clinicos']) && is_array($payload['dados_clinicos'])) {
            $clinicos = $payload['dados_clinicos'];
        }

        $linha = array_merge($clinicos, $payload);
        unset($linha['dados_clinicos'], $linha['features_modelo'], $linha['analise'], $linha['predicao'], $linha['intercorrencias'], $linha['recomendacoes'], $linha['qualidade_dados']);

        if (! isset($linha['id_gestante']) && isset($payload['id_gestante'])) {
            $linha['id_gestante'] = $payload['id_gestante'];
        }

        return $linha;
    }

    private function toBool(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value != 0.0;
        }

        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', '1.0', 'true', 'sim', 'yes'], true);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
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
