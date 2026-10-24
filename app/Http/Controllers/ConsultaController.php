<?php

namespace App\Http\Controllers;

use App\Jobs\ExecutarAnaliseGestante;
use App\Models\AnaliseHistorico;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Models\User;
use App\Services\DatasetGestanteImporter;
use App\Services\PlanilhaGestanteReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConsultaController extends Controller
{
    /** Colunas do formulário / CSV legado (schema MySQL). */
    private const COLUNAS_LEGADO = [
        'data_consulta',
        'gestante_id',
        'consulta_numero',
        'idade_materna',
        'idade_gestacional',
        'etnia',
        'imc_pre_gestacional',
        'paridade',
        'abortamentos_previos',
        'historico_natimorto',
        'historico_filho_anterior_chd',
        'historico_familiar_chd',
        'diabetes_pre_gestacional',
        'diabetes_gestacional',
        'lupus_eritomatoso_sistemico',
        'fenilcetonuria',
        'doencas_tireoidianas',
        'hipertensao_cronica',
        'rubeola',
        'citomegalovirus',
        'toxoplasmose',
        'sifilis',
        'uso_isotretinoina',
        'uso_acido_valproico',
        'uso_litio',
        'uso_medicamentos',
        'tabagismo',
        'alcoolismo',
        'drogas_ilicitas',
        'exposicao_ocupacional',
        'usg_precoce_confirmada',
        'tipo_gestacao',
        'corionicidade',
        'altura',
        'peso',
        'pressao_sistolica',
        'bpm_materno',
        'saturacao',
        'temperatura_corporal',
        'frequencia_cardiaca_fetal',
        'circunferencia_cefalica_fetal_mm',
        'circunferencia_abdominal_mm',
        'comprimento_femur_mm',
        'translucencia_nucal_mm',
        'translucencia_nucal_aumentada',
        'doppler_ducto_venoso',
        'regurgitacao_tricuspide_fetal',
        'malformacoes_extracardiacas_associadas',
        'crescimento_fetal_rcf_iugr',
        'polidramnio_oligoidramnio',
        'eixo_cardiaco',
        'quatro_camaras',
        'chd_confirmada',
        'tipo_chd',
    ];

    /** Colunas mínimas do dataset ML (CardioPrenatal / AnalisePython). */
    private const COLUNAS_ML_MINIMAS = [
        'id_gestante',
        'idade_materna',
        'imc_pre_gestacional',
        'paridade',
        'idade_gestacional',
    ];

    public function index()
    {
        $this->authorize(User::ABILITY_CONSULTAS_IMPORT);

        return view('consultas.import');
    }

    private function storeFromForm(Request $request, $id)
    {
        $validatedData = $request->validate($this->regrasFormularioConsulta());
        $validatedData = $this->aplicarDefaultsBooleanos($validatedData);
        $validatedData['gestante_id'] = $id;
        $validatedData['consulta_numero'] = (int) Consulta::where('gestante_id', $id)->max('consulta_numero') + 1;

        Consulta::create($validatedData);

        return redirect()->route('gestantes.show', $id)->with('success', 'Consulta cadastrada com sucesso!');
    }

    public function store(Request $request, $id)
    {
        $this->authorize(User::ABILITY_CONSULTAS_MANAGE);

        return $this->storeFromForm($request, $id);
    }

    public function import(Request $request)
    {
        $this->authorize(User::ABILITY_CONSULTAS_IMPORT);
        if (! $request->hasFile('csv')) {
            $mensagem = 'Nenhum arquivo CSV ou Excel foi enviado.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $mensagem], 422);
            }

            return redirect()->back()->with('error', $mensagem);
        }

        return $this->importStore($request);
    }

    private function toBoolean($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function importStore(Request $request)
    {
        $this->authorize(User::ABILITY_CONSULTAS_IMPORT);
        set_time_limit(600);

        $request->validate([
            'csv' => 'required|file|max:65536',
        ], [
            'csv.required' => 'Selecione um arquivo CSV ou Excel.',
            'csv.max' => 'O arquivo deve ter no máximo 64 MB.',
        ]);

        $file = $request->file('csv');
        $reader = app(PlanilhaGestanteReader::class);

        if (! $reader->extensaoValida($file->getClientOriginalName())) {
            return response()->json([
                'message' => 'Envie um arquivo .csv ou .xlsx.',
            ], 422);
        }

        try {
            $planilha = $reader->ler($file);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Não foi possível ler o arquivo.',
            ], 422);
        }

        $header = $planilha['header'];

        if (! in_array('id_gestante', $header, true)) {
            return response()->json([
                'message' => 'A planilha precisa da coluna id_gestante.',
                'error' => [
                    'formato' => 'invalido',
                    'dica' => 'Use CSV ou Excel com a coluna id_gestante (dataset clínico).',
                ],
            ], 422);
        }

        return $this->importDatasetMl($header, $planilha['rows'], (int) $planilha['skipped']);
    }

    /**
     * Importa CSV/XLSX no formato do cohort ML (id_gestante + features clínicas).
     *
     * @param  list<string>  $header
     * @param  list<array<string, string>>  $linhas
     */
    private function importDatasetMl(array $header, array $linhas, int $skippedLeitura = 0): JsonResponse
    {
        $faltando = array_values(array_diff(self::COLUNAS_ML_MINIMAS, $header));
        if ($faltando !== []) {
            return response()->json([
                'message' => 'Planilha incompleta: faltam colunas obrigatórias.',
                'error' => [
                    'formato' => 'ml',
                    'faltando' => $faltando,
                    'dica' => 'Inclua id_gestante, idade_materna, imc_pre_gestacional, paridade e idade_gestacional.',
                ],
            ], 422);
        }

        $importer = app(DatasetGestanteImporter::class);
        $imported = 0;
        $updated = 0;
        $skipped = $skippedLeitura;
        $errors = [];
        $linhaCsv = 1;

        Consulta::withoutEvents(function () use ($linhas, $importer, &$imported, &$updated, &$skipped, &$errors, &$linhaCsv) {
            DB::beginTransaction();
            try {
                foreach ($linhas as $linha) {
                    $linhaCsv++;

                    if (trim((string) ($linha['id_gestante'] ?? '')) === '') {
                        $skipped++;

                        continue;
                    }

                    try {
                        $result = $importer->upsert($linha);
                        if ($result === 'created') {
                            $imported++;
                        } else {
                            $updated++;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'linha' => $linhaCsv,
                            'id_gestante' => $linha['id_gestante'] ?? null,
                            'erro' => $e->getMessage(),
                        ];
                        if (count($errors) >= 20) {
                            break;
                        }
                    }

                    if (($imported + $updated) > 0 && ($imported + $updated) % 500 === 0) {
                        DB::commit();
                        DB::beginTransaction();
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        });

        if ($errors !== [] && ($imported + $updated) === 0) {
            return response()->json([
                'message' => 'Erro ao importar a planilha.',
                'error' => $errors,
            ], 422);
        }

        $analisesEnfileiradas = 0;
        if ($imported > 0) {
            $analisesEnfileiradas = $this->enfileirarAnalisesAusentes();
        }

        return response()->json([
            'message' => 'Importação concluída.',
            'formato' => 'ml',
            'criadas' => $imported,
            'atualizadas' => $updated,
            'ignoradas' => $skipped,
            'erros' => count($errors),
            'detalhe_erros' => $errors,
            'analises_enfileiradas' => $analisesEnfileiradas,
            'gestantes' => Gestante::count(),
            'consultas' => Consulta::count(),
        ]);
    }

    /**
     * Enfileira análise só para gestantes que ainda não têm análise concluída.
     */
    private function enfileirarAnalisesAusentes(): int
    {
        $jaAnalisadas = AnaliseHistorico::query()
            ->where('status', 'concluida')
            ->distinct()
            ->pluck('gestante_id');

        $gestantes = Gestante::query()
            ->whereHas('consultas')
            ->when($jaAnalisadas->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $jaAnalisadas))
            ->get(['id']);

        foreach ($gestantes as $gestante) {
            $consultaId = Consulta::query()
                ->where('gestante_id', $gestante->id)
                ->latest('id')
                ->value('id');

            try {
                ExecutarAnaliseGestante::dispatch(
                    (int) $gestante->id,
                    $consultaId ? (int) $consultaId : null,
                    AnaliseHistorico::EVENTO_REPROCESSAMENTO,
                );
            } catch (\Throwable $e) {
                Log::warning('Importação: falha ao enfileirar análise.', [
                    'gestante_id' => $gestante->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $gestantes->count();
    }

    /**
     * Importa CSV no formato legado (colunas iguais à tabela consultas).
     *
     * @param  resource  $handle
     * @param  list<string>  $header
     */
    private function importCsvLegado($handle, array $header)
    {
        $colunasFaltando = array_diff(self::COLUNAS_LEGADO, $header);
        if (! empty($colunasFaltando)) {
            fclose($handle);

            return response()->json([
                'message' => 'Estrutura do CSV legado inválida.',
                'error' => [
                    'formato' => 'legado',
                    'faltando' => array_values($colunasFaltando),
                    'dica' => 'Se o arquivo for do dataset ML, ele deve ter a coluna id_gestante.',
                ],
            ], 422);
        }

        $dados = [];
        $erros = [];
        $numeroLinha = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $numeroLinha++;
            if (count($header) !== count($row)) {
                $erros[] = [
                    'linha_csv' => $numeroLinha,
                    'erro' => 'Quantidade de colunas inválida',
                ];

                continue;
            }

            $linha = array_combine($header, $row);

            $camposBooleanos = [
                'historico_natimorto',
                'historico_filho_anterior_chd',
                'historico_familiar_chd',
                'diabetes_pre_gestacional',
                'diabetes_gestacional',
                'lupus_eritomatoso_sistemico',
                'fenilcetonuria',
                'doencas_tireoidianas',
                'hipertensao_cronica',
                'rubeola',
                'citomegalovirus',
                'toxoplasmose',
                'sifilis',
                'uso_isotretinoina',
                'uso_acido_valproico',
                'uso_litio',
                'uso_medicamentos',
                'tabagismo',
                'alcoolismo',
                'drogas_ilicitas',
                'exposicao_ocupacional',
                'usg_precoce_confirmada',
                'translucencia_nucal_aumentada',
                'regurgitacao_tricuspide_fetal',
                'malformacoes_extracardiacas_associadas',
                'crescimento_fetal_rcf_iugr',
                'chd_confirmada',
            ];

            foreach ($camposBooleanos as $campo) {
                if (isset($linha[$campo])) {
                    $valor = strtolower(trim((string) $linha[$campo]));

                    if (in_array($valor, ['1', 'true', 'sim', 'yes'], true)) {
                        $linha[$campo] = true;
                    } elseif (in_array($valor, ['0', 'false', 'nao', 'não', 'no'], true)) {
                        $linha[$campo] = false;
                    } else {
                        $linha[$campo] = null;
                    }
                }
            }

            $validator = Validator::make($linha, [
                'data_consulta' => 'required|date',
                'gestante_id' => 'required',
                'consulta_numero' => 'required|integer',
                'idade_materna' => 'nullable|integer',
                'idade_gestacional' => 'required|integer',
                'etnia' => 'nullable|string|max:50',
                'imc_pre_gestacional' => 'nullable|numeric',
                'paridade' => 'nullable|integer',
                'abortamentos_previos' => 'nullable|integer',
                'historico_natimorto' => 'required|boolean',
                'historico_filho_anterior_chd' => 'required|boolean',
                'historico_familiar_chd' => 'required|boolean',
                'diabetes_pre_gestacional' => 'required|boolean',
                'diabetes_gestacional' => 'required|boolean',
                'lupus_eritomatoso_sistemico' => 'required|boolean',
                'fenilcetonuria' => 'required|boolean',
                'doencas_tireoidianas' => 'required|boolean',
                'hipertensao_cronica' => 'required|boolean',
                'rubeola' => 'required|boolean',
                'citomegalovirus' => 'required|boolean',
                'toxoplasmose' => 'required|boolean',
                'sifilis' => 'required|boolean',
                'uso_isotretinoina' => 'required|boolean',
                'uso_acido_valproico' => 'required|boolean',
                'uso_litio' => 'required|boolean',
                'uso_medicamentos' => 'required|boolean',
                'tabagismo' => 'required|boolean',
                'alcoolismo' => 'required|boolean',
                'drogas_ilicitas' => 'required|boolean',
                'exposicao_ocupacional' => 'required|boolean',
                'usg_precoce_confirmada' => 'required|boolean',
                'tipo_gestacao' => 'nullable|string|max:32',
                'corionicidade' => 'nullable|string|max:50',
                'altura' => 'nullable|numeric',
                'peso' => 'nullable|numeric',
                'pressao_sistolica' => 'nullable|integer',
                'bpm_materno' => 'nullable|integer',
                'saturacao' => 'nullable|integer',
                'temperatura_corporal' => 'nullable|numeric',
                'frequencia_cardiaca_fetal' => 'nullable|integer',
                'circunferencia_cefalica_fetal_mm' => 'nullable|numeric',
                'circunferencia_abdominal_mm' => 'nullable|numeric',
                'comprimento_femur_mm' => 'nullable|numeric',
                'translucencia_nucal_mm' => 'nullable|numeric',
                'translucencia_nucal_aumentada' => 'nullable|boolean',
                'doppler_ducto_venoso' => 'nullable|string',
                'regurgitacao_tricuspide_fetal' => 'nullable|boolean',
                'malformacoes_extracardiacas_associadas' => 'nullable|boolean',
                'crescimento_fetal_rcf_iugr' => 'nullable|boolean',
                'polidramnio_oligoidramnio' => 'nullable|string|max:32',
                'eixo_cardiaco' => 'nullable|string',
                'quatro_camaras' => 'nullable|string',
                'chd_confirmada' => 'required|boolean',
                'tipo_chd' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                $erros[] = [
                    'linha_csv' => $numeroLinha,
                    'erros' => $validator->errors(),
                ];
            } else {
                $dados[] = $this->aplicarDefaultsBooleanos($linha);
            }
        }

        fclose($handle);

        if (! empty($erros)) {
            return response()->json([
                'message' => 'Erro de validação nos dados.',
                'error' => $erros,
            ], 422);
        }

        foreach ($dados as $item) {
            Consulta::create($item);
        }

        return response()->json([
            'message' => 'Importação realizada com sucesso!',
            'formato' => 'legado',
            'importadas' => count($dados),
        ]);
    }

    public function create($id)
    {
        $this->authorize(User::ABILITY_CONSULTAS_MANAGE);
        $gestante = Gestante::findOrFail($id);

        return view('consultas.create', compact('gestante'));
    }

    public function edit($id)
    {
        $this->authorize(User::ABILITY_CONSULTAS_MANAGE);
        $consulta = Consulta::with('gestante')->findOrFail($id);
        $gestante = $consulta->gestante;

        return view('consultas.edit', compact('consulta', 'gestante'));
    }

    public function update(Request $request, $id)
    {
        $this->authorize(User::ABILITY_CONSULTAS_MANAGE);
        $consulta = Consulta::findOrFail($id);

        $validatedData = $request->validate($this->regrasFormularioConsulta());
        $validatedData = $this->aplicarDefaultsBooleanos($validatedData);
        $consulta->update($validatedData);

        return redirect()->route('gestantes.show', $consulta->gestante_id)->with('success', 'Consulta atualizada com sucesso!');
    }

    /**
     * @return array<string, string>
     */
    private function regrasFormularioConsulta(): array
    {
        return [
            'data_consulta' => 'required|date',
            'idade_materna' => 'nullable|integer',
            'idade_gestacional' => 'required|integer',
            'etnia' => 'nullable|string|max:50',
            'imc_pre_gestacional' => 'nullable|numeric',
            'paridade' => 'nullable|integer',
            'abortamentos_previos' => 'nullable|integer',
            'historico_natimorto' => 'nullable|boolean',
            'historico_filho_anterior_chd' => 'nullable|boolean',
            'historico_familiar_chd' => 'nullable|boolean',
            'diabetes_pre_gestacional' => 'nullable|boolean',
            'diabetes_gestacional' => 'nullable|boolean',
            'lupus_eritomatoso_sistemico' => 'nullable|boolean',
            'fenilcetonuria' => 'nullable|boolean',
            'doencas_tireoidianas' => 'nullable|boolean',
            'hipertensao_cronica' => 'nullable|boolean',
            'rubeola' => 'nullable|boolean',
            'citomegalovirus' => 'nullable|boolean',
            'toxoplasmose' => 'nullable|boolean',
            'sifilis' => 'nullable|boolean',
            'uso_isotretinoina' => 'nullable|boolean',
            'uso_acido_valproico' => 'nullable|boolean',
            'uso_litio' => 'nullable|boolean',
            'uso_medicamentos' => 'nullable|boolean',
            'tabagismo' => 'nullable|boolean',
            'alcoolismo' => 'nullable|boolean',
            'drogas_ilicitas' => 'nullable|boolean',
            'exposicao_ocupacional' => 'nullable|boolean',
            'usg_precoce_confirmada' => 'nullable|boolean',
            'tipo_gestacao' => 'nullable|string|max:32',
            'corionicidade' => 'nullable|string|max:50',
            'altura' => 'nullable|numeric',
            'peso' => 'nullable|numeric',
            'pressao_sistolica' => 'nullable|integer',
            'bpm_materno' => 'nullable|integer',
            'saturacao' => 'nullable|integer',
            'temperatura_corporal' => 'nullable|numeric',
            'glicemia_jejum' => 'nullable|numeric',
            'glicemia_pos_prandial' => 'nullable|numeric',
            'hba1c' => 'nullable|numeric',
            'frequencia_cardiaca_fetal' => 'nullable|integer',
            'circunferencia_cefalica_fetal_mm' => 'nullable|numeric',
            'circunferencia_abdominal_mm' => 'nullable|numeric',
            'comprimento_femur_mm' => 'nullable|numeric',
            'translucencia_nucal_mm' => 'nullable|numeric',
            'translucencia_nucal_aumentada' => 'nullable|boolean',
            'doppler_ducto_venoso' => 'nullable|string|max:255',
            'regurgitacao_tricuspide_fetal' => 'nullable|boolean',
            'malformacoes_extracardiacas_associadas' => 'nullable|boolean',
            'crescimento_fetal_rcf_iugr' => 'nullable|boolean',
            'polidramnio_oligoidramnio' => 'nullable|string|max:32',
            'eixo_cardiaco' => 'nullable|string|max:255',
            'quatro_camaras' => 'nullable|string|max:255',
            'obesidade_pre_gestacional' => 'nullable|boolean',
            'hipertensao' => 'nullable|boolean',
            'hipertensao_pre_eclampsia' => 'nullable|boolean',
            'chd_confirmada' => 'nullable|boolean',
            'tipo_chd' => 'nullable|string|max:255',
        ];
    }

    /**
     * Fatores clínicos Sim/Não: ausentes = falso (padrão "Não").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function aplicarDefaultsBooleanos(array $data): array
    {
        foreach ([
            'historico_natimorto',
            'historico_filho_anterior_chd',
            'historico_familiar_chd',
            'diabetes_pre_gestacional',
            'diabetes_gestacional',
            'lupus_eritomatoso_sistemico',
            'fenilcetonuria',
            'doencas_tireoidianas',
            'hipertensao_cronica',
            'rubeola',
            'citomegalovirus',
            'toxoplasmose',
            'sifilis',
            'uso_isotretinoina',
            'uso_acido_valproico',
            'uso_litio',
            'uso_medicamentos',
            'tabagismo',
            'alcoolismo',
            'drogas_ilicitas',
            'exposicao_ocupacional',
            'usg_precoce_confirmada',
            'obesidade_pre_gestacional',
            'hipertensao',
            'hipertensao_pre_eclampsia',
            'translucencia_nucal_aumentada',
            'regurgitacao_tricuspide_fetal',
            'malformacoes_extracardiacas_associadas',
            'crescimento_fetal_rcf_iugr',
            'chd_confirmada',
        ] as $campo) {
            $data[$campo] = (bool) ($data[$campo] ?? false);
        }

        return $data;
    }
}
