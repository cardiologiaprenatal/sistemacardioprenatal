<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CcfApiClient;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GraficosController extends Controller
{
    /** @var array<string, string> */
    private const ARQUIVOS_PADRAO = [
        'prevalencia' => '01_prevalencia_ccf.png',
        'distribuicao' => '02_distribuicao_idade_imc.png',
        'fatores_risco' => '03_ccf_por_fatores_risco.png',
        'intercorrencias_cat' => '04_intercorrencias_por_categoria.png',
        'top_intercorrencias' => '05_top_intercorrencias.png',
        'risco' => '06_distribuicao_risco.png',
        'probabilidade' => '07_distribuicao_probabilidade.png',
        'prioridade_eco' => '08_prioridade_ecocardiograma.png',
        'importancia' => '09_importancia_features.png',
        'experimentos' => '10_comparativo_experimentos.png',
        'etnia' => '11_prevalencia_por_etnia.png',
    ];

    public function __construct(
        private CcfApiClient $apiClient,
    ) {}

    public function chartJs(): HttpResponse
    {
        $path = storage_path('app/assets/chart.umd.min.js');
        if (! is_readable($path)) {
            abort(404, 'Chart.js não instalado em storage/app/assets/.');
        }

        return response()->file($path, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function descritivo(string $slug): Response|HttpResponse
    {
        $this->authorize(User::ABILITY_DASHBOARD);
        if (! array_key_exists($slug, self::ARQUIVOS_PADRAO)) {
            abort(404);
        }

        $local = $this->resolveLocalFigure($slug);
        if ($local !== null) {
            return $this->pngResponse($local);
        }

        try {
            $response = $this->apiClient->fetchGraficoDescritivo($slug);
            if ($response->successful()) {
                return response($response->body(), 200, [
                    'Content-Type' => $response->header('Content-Type') ?? 'image/png',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }
        } catch (\Throwable) {
            // ignorar — tentamos fallbacks acima
        }

        abort(404, 'Gráfico indisponível. Execute: php storage/app/sync_figuras_descritivas.php');
    }

    private function pngResponse(string $path): HttpResponse
    {
        if (! is_readable($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function resolveLocalFigure(string $slug): ?string
    {
        $nome = self::ARQUIVOS_PADRAO[$slug];

        $bundledDir = storage_path('app/gestrisk/figuras');
        $bundled = $bundledDir.DIRECTORY_SEPARATOR.$nome;
        if (is_readable($bundled)) {
            return $bundled;
        }

        $configuredDir = config('services.ccf_api.figures_path');
        if (is_string($configuredDir) && is_dir($configuredDir)) {
            $candidato = $configuredDir.DIRECTORY_SEPARATOR.$nome;
            if (is_readable($candidato)) {
                return $candidato;
            }

            $glob = File::glob($configuredDir.DIRECTORY_SEPARATOR.'*'.$slug.'*.png');
            if (! empty($glob) && is_readable($glob[0])) {
                return $glob[0];
            }
        }

        $metaPath = config('services.ccf_api.meta_json');
        if (is_string($metaPath) && is_readable($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($meta) && isset($meta['graficos'][$slug])) {
                $fromMeta = (string) $meta['graficos'][$slug];
                if (is_readable($fromMeta)) {
                    return $fromMeta;
                }
            }
        }

        return null;
    }
}
