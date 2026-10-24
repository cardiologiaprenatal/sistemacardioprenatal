<?php

namespace App\Http\Controllers;

use App\Models\Consulta;
use App\Models\Gestante;
use App\Models\User;
use App\Services\CcfApiClient;
use App\Services\DashboardAnaliseService;
use App\Services\PythonApiService;

class DashboardController extends Controller
{
    /** @var list<string> */
    private const ORDEM_GRAFICOS_DESCRITIVOS = [
        'prevalencia',
        'distribuicao',
        'fatores_risco',
        'risco',
        'probabilidade',
        'prioridade_eco',
        'intercorrencias_cat',
        'top_intercorrencias',
        'importancia',
        'experimentos',
        'etnia',
    ];

    public function __construct(
        private DashboardAnaliseService $dashboardAnalise,
        private CcfApiClient $ccfApi,
        private PythonApiService $pythonApi,
    ) {}

    public function index()
    {
        $this->authorize(User::ABILITY_DASHBOARD);
        $totalGestantes = Gestante::count();
        $totalConsultas = Consulta::count();
        $chdConfirmadas = Consulta::where('chd_confirmada', true)->count();
        $chdNegativas = $totalConsultas > 0 ? $totalConsultas - $chdConfirmadas : 0;

        $gestantesComConsulta = Gestante::has('consultas')->count();
        $taxaGestantesComConsulta = $totalGestantes > 0
            ? round(100 * $gestantesComConsulta / $totalGestantes, 0)
            : 0;

        $pctChdNasConsultas = $totalConsultas > 0
            ? round(100 * $chdConfirmadas / $totalConsultas, 1)
            : 0.0;

        $mediaIdadeGestacional = Consulta::query()->avg('idade_gestacional');
        $mediaIdadeGestacional = $mediaIdadeGestacional !== null
            ? round((float) $mediaIdadeGestacional, 1)
            : null;

        $consultasComDiabetes = Consulta::where('diabetes_gestacional', true)->count();
        $consultasComHipertensao = Consulta::where('hipertensao', true)->count();

        $consultasRecentes = Consulta::query()
            ->with('gestante')
            ->orderByDesc('data_consulta')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $distribuicaoRisco = $this->dashboardAnalise->distribuicaoRisco();
        $filaEcocardiograma = $this->dashboardAnalise->filaEcocardiograma(20);
        $indicadoresModelo = $this->dashboardAnalise->indicadoresModelo();
        $analisesRecentes = $this->dashboardAnalise->analisesRecentes(10);
        $graficosPopulacionais = $this->dashboardAnalise->dadosGraficosPopulacionais();

        $analiseDescritiva = $this->ccfApi->obterAnaliseDescritiva();
        $graficosDescritivos = $this->ordenarGraficosDescritivos(
            array_values(array_filter(
                $this->ccfApi->listarGraficosDescritivos(),
                static fn (array $g): bool => (bool) ($g['disponivel'] ?? false)
            ))
        );
        $graficosCohort = [];
        if ($graficosDescritivos === []) {
            $local = $this->dashboardAnalise->resumoCohortLocal();
            if ($analiseDescritiva === []) {
                $analiseDescritiva = $local;
            }
            $graficosCohort = $this->dashboardAnalise->dadosGraficosCohort();
        }

        $datasetMlTotal = null;
        try {
            $datasetMlTotal = $this->pythonApi->listGestantes(0, 1)['total'] ?? null;
        } catch (\Throwable) {
            $datasetMlTotal = $analiseDescritiva['total_gestantes'] ?? Gestante::query()->count();
        }

        $modelosMl = [
            'disponivel' => false,
            'total_modelos' => 0,
            'destaques' => [],
            'modelos' => [],
            'legendas' => [],
            'mensagem' => null,
        ];
        try {
            $modelosMl = $this->pythonApi->listModelos();
        } catch (\Throwable) {
            $modelosMl['mensagem'] = 'Não foi possível consultar GET /modelos na API ML.';
        }

        $modelInfo = null;
        try {
            $modelInfo = $this->pythonApi->modelInfo();
        } catch (\Throwable) {
            $modelInfo = null;
        }

        return view('dashboard', [
            'totalGestantes' => $totalGestantes,
            'totalConsultas' => $totalConsultas,
            'chdConfirmadas' => $chdConfirmadas,
            'chdNegativas' => $chdNegativas,
            'gestantesComConsulta' => $gestantesComConsulta,
            'taxaGestantesComConsulta' => $taxaGestantesComConsulta,
            'pctChdNasConsultas' => $pctChdNasConsultas,
            'mediaIdadeGestacional' => $mediaIdadeGestacional,
            'consultasComDiabetes' => $consultasComDiabetes,
            'consultasComHipertensao' => $consultasComHipertensao,
            'consultasRecentes' => $consultasRecentes,
            'distribuicaoRisco' => $distribuicaoRisco,
            'filaEcocardiograma' => $filaEcocardiograma,
            'indicadoresModelo' => $indicadoresModelo,
            'analisesRecentes' => $analisesRecentes,
            'graficosPopulacionais' => $graficosPopulacionais,
            'analiseDescritiva' => $analiseDescritiva,
            'graficosDescritivos' => $graficosDescritivos,
            'graficosCohort' => $graficosCohort,
            'datasetMlTotal' => $datasetMlTotal,
            'modelosMl' => $modelosMl,
            'modelInfo' => $modelInfo,
            'dashHealth' => [
                'api_modelos' => (bool) ($modelosMl['disponivel'] ?? false),
                'api_descritiva' => count($graficosDescritivos) > 0 || count($graficosCohort) > 0,
                'api_dataset' => $datasetMlTotal !== null,
                'tem_analises' => ($distribuicaoRisco['total_gestantes_analisadas'] ?? 0) > 0,
                'total_analises' => (int) ($indicadoresModelo['total_analises'] ?? 0),
                'total_erros' => (int) ($indicadoresModelo['total_erros'] ?? 0),
            ],
            'hoje' => now()->format('d/m/Y'),
        ]);
    }

    /**
     * @param  list<array{slug: string, titulo: string, arquivo?: string|null, disponivel?: bool}>  $graficos
     * @return list<array{slug: string, titulo: string, arquivo?: string|null, disponivel?: bool}>
     */
    private function ordenarGraficosDescritivos(array $graficos): array
    {
        $porSlug = [];
        foreach ($graficos as $item) {
            if (! empty($item['slug'])) {
                $porSlug[(string) $item['slug']] = $item;
            }
        }

        $ordenados = [];
        foreach (self::ORDEM_GRAFICOS_DESCRITIVOS as $slug) {
            if (isset($porSlug[$slug])) {
                $item = $porSlug[$slug];
                if (empty($item['titulo'])) {
                    $item['titulo'] = DashboardAnaliseService::tituloGraficoDescritivo($slug);
                }
                $ordenados[] = $item;
                unset($porSlug[$slug]);
            }
        }

        foreach ($porSlug as $slug => $restante) {
            if (empty($restante['titulo'])) {
                $restante['titulo'] = DashboardAnaliseService::tituloGraficoDescritivo((string) $slug);
            }
            $ordenados[] = $restante;
        }

        return $ordenados;
    }
}
