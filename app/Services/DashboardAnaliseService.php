<?php

namespace App\Services;

use App\Models\AnaliseHistorico;
use App\Models\Consulta;
use App\Models\Gestante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardAnaliseService
{
    /**
     * IDs da última análise concluída por gestante (leve — só IDs).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function idsUltimasAnalisesPorGestante(): Collection
    {
        return AnaliseHistorico::query()
            ->select(DB::raw('MAX(id) as id'))
            ->where('status', 'concluida')
            ->groupBy('gestante_id')
            ->pluck('id');
    }

    /**
     * Última análise concluída por gestante (com relações).
     * Evite em agregados do dashboard — preferir SQL.
     */
    public function obterUltimasAnalisesPorGestante(): Collection
    {
        $ids = $this->idsUltimasAnalisesPorGestante();

        if ($ids->isEmpty()) {
            return collect();
        }

        return AnaliseHistorico::query()
            ->with(['gestante', 'consulta'])
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @return array{
     *     total_gestantes_analisadas: int,
     *     baixo: int, moderado: int, alto: int,
     *     pct_baixo: float, pct_moderado: float, pct_alto: float
     * }
     */
    public function distribuicaoRisco(): array
    {
        $ids = $this->idsUltimasAnalisesPorGestante();
        if ($ids->isEmpty()) {
            return [
                'total_gestantes_analisadas' => 0,
                'baixo' => 0,
                'moderado' => 0,
                'alto' => 0,
                'pct_baixo' => 0.0,
                'pct_moderado' => 0.0,
                'pct_alto' => 0.0,
            ];
        }

        $rows = AnaliseHistorico::query()
            ->whereIn('id', $ids)
            ->select('classificacao_risco', DB::raw('COUNT(*) as total'))
            ->groupBy('classificacao_risco')
            ->pluck('total', 'classificacao_risco');

        $baixo = (int) ($rows['baixo'] ?? 0);
        $moderado = (int) ($rows['moderado'] ?? 0);
        $alto = (int) ($rows['alto'] ?? 0);
        $total = $baixo + $moderado + $alto;

        return [
            'total_gestantes_analisadas' => $total,
            'baixo' => $baixo,
            'moderado' => $moderado,
            'alto' => $alto,
            'pct_baixo' => $total > 0 ? round(100 * $baixo / $total, 1) : 0.0,
            'pct_moderado' => $total > 0 ? round(100 * $moderado / $total, 1) : 0.0,
            'pct_alto' => $total > 0 ? round(100 * $alto / $total, 1) : 0.0,
        ];
    }

    public function filaEcocardiograma(int $limite = 20): Collection
    {
        $ids = $this->idsUltimasAnalisesPorGestante();
        if ($ids->isEmpty()) {
            return collect();
        }

        return AnaliseHistorico::query()
            ->with(['gestante'])
            ->whereIn('id', $ids)
            ->where('recomenda_ecocardiograma', true)
            ->orderByDesc('score_prioridade')
            ->limit($limite)
            ->get([
                'id',
                'gestante_id',
                'consulta_id',
                'classificacao_risco',
                'probabilidade_ccf',
                'recomenda_ecocardiograma',
                'prioridade_ecocardiograma',
                'score_prioridade',
            ]);
    }

    /**
     * @return array{
     *     total_analises: int,
     *     total_erros: int,
     *     media_probabilidade_ccf: float|null,
     *     gestantes_com_eco_recomendada: int
     * }
     */
    public function indicadoresModelo(): array
    {
        $ids = $this->idsUltimasAnalisesPorGestante();
        $media = null;
        $eco = 0;

        if ($ids->isNotEmpty()) {
            $media = AnaliseHistorico::query()
                ->whereIn('id', $ids)
                ->avg('probabilidade_ccf');
            $eco = AnaliseHistorico::query()
                ->whereIn('id', $ids)
                ->where('recomenda_ecocardiograma', true)
                ->count();
        }

        return [
            'total_analises' => AnaliseHistorico::where('status', 'concluida')->count(),
            'total_erros' => AnaliseHistorico::where('status', 'erro')->count(),
            'media_probabilidade_ccf' => $media !== null ? round((float) $media * 100, 2) : null,
            'gestantes_com_eco_recomendada' => $eco,
        ];
    }

    public function analisesRecentes(int $limite = 10): Collection
    {
        return AnaliseHistorico::query()
            ->with(['gestante'])
            ->where('status', 'concluida')
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get([
                'id',
                'gestante_id',
                'evento_tipo',
                'classificacao_risco',
                'probabilidade_ccf',
                'recomenda_ecocardiograma',
                'prioridade_ecocardiograma',
                'score_prioridade',
                'created_at',
            ]);
    }

    /**
     * @return array{urgente: int, alta: int, moderada: int, baixa: int, sem_eco: int}
     */
    public function distribuicaoPrioridadeEco(): array
    {
        $ids = $this->idsUltimasAnalisesPorGestante();
        if ($ids->isEmpty()) {
            return [
                'urgente' => 0,
                'alta' => 0,
                'moderada' => 0,
                'baixa' => 0,
                'sem_eco' => 0,
            ];
        }

        $porPrioridade = AnaliseHistorico::query()
            ->whereIn('id', $ids)
            ->where('recomenda_ecocardiograma', true)
            ->select('prioridade_ecocardiograma', DB::raw('COUNT(*) as total'))
            ->groupBy('prioridade_ecocardiograma')
            ->pluck('total', 'prioridade_ecocardiograma');

        $comEco = (int) $porPrioridade->sum();
        $total = $ids->count();

        return [
            'urgente' => (int) ($porPrioridade['urgente'] ?? 0),
            'alta' => (int) ($porPrioridade['alta'] ?? 0),
            'moderada' => (int) ($porPrioridade['moderada'] ?? 0),
            'baixa' => (int) ($porPrioridade['baixa'] ?? 0),
            'sem_eco' => max(0, $total - $comEco),
        ];
    }

    /**
     * Histograma de probabilidade CCF (última análise por gestante).
     *
     * @return array{labels: list<string>, valores: list<int>}
     */
    public function histogramaProbabilidadeCcf(int $faixas = 10): array
    {
        $labels = [];
        $valores = array_fill(0, $faixas, 0);

        for ($i = 0; $i < $faixas; $i++) {
            $min = $i * 10;
            $max = ($i + 1) * 10;
            $labels[] = $i === $faixas - 1
                ? sprintf('%d-100%%', $min)
                : sprintf('%d-%d%%', $min, $max);
        }

        $ids = $this->idsUltimasAnalisesPorGestante();
        if ($ids->isEmpty()) {
            return ['labels' => $labels, 'valores' => $valores];
        }

        // Só probabilidade — sem shap/JSON.
        $probs = AnaliseHistorico::query()
            ->whereIn('id', $ids)
            ->whereNotNull('probabilidade_ccf')
            ->pluck('probabilidade_ccf');

        foreach ($probs as $prob) {
            $pct = (float) $prob * 100;
            $idx = min($faixas - 1, (int) floor($pct / 10));
            $valores[$idx]++;
        }

        return ['labels' => $labels, 'valores' => $valores];
    }

    /**
     * Dados agregados para Chart.js no dashboard.
     *
     * @return array<string, mixed>
     */
    public function dadosGraficosPopulacionais(): array
    {
        $risco = $this->distribuicaoRisco();
        $eco = $this->distribuicaoPrioridadeEco();
        $hist = $this->histogramaProbabilidadeCcf();

        return [
            'risco' => [
                'labels' => ['Baixo', 'Moderado', 'Alto'],
                'valores' => [$risco['baixo'], $risco['moderado'], $risco['alto']],
            ],
            'prioridade_eco' => [
                'labels' => ['Urgente', 'Alta', 'Moderada', 'Baixa', 'Sem eco'],
                'valores' => [$eco['urgente'], $eco['alta'], $eco['moderada'], $eco['baixa'], $eco['sem_eco']],
            ],
            'probabilidade' => $hist,
        ];
    }

    /**
     * Totais do cohort no cadastro local (quando a API ML não publica /analise/descritiva).
     *
     * @return array{fonte: string, total_gestantes: int, total_consultas: int, prevalencia_ccf: float}
     */
    public function resumoCohortLocal(): array
    {
        $totalGestantes = (int) Gestante::query()->count();
        $totalConsultas = (int) Consulta::query()->count();
        $chd = (int) Consulta::query()->where('chd_confirmada', true)->count();

        return [
            'fonte' => 'cadastro local',
            'total_gestantes' => $totalGestantes,
            'total_consultas' => $totalConsultas,
            'prevalencia_ccf' => $totalConsultas > 0 ? round($chd / $totalConsultas, 4) : 0.0,
        ];
    }

    /**
     * Gráficos Chart.js do cohort a partir de consultas/análises locais.
     *
     * @return list<array{slug: string, titulo: string, tipo: string, labels: list<string>, valores: list<int|float>}>
     */
    public function dadosGraficosCohort(): array
    {
        $totalConsultas = (int) Consulta::query()->count();
        if ($totalConsultas === 0) {
            return [];
        }

        $chdSim = (int) Consulta::query()->where('chd_confirmada', true)->count();
        $charts = [
            [
                'slug' => 'prevalencia',
                'titulo' => 'Prevalência de CCF',
                'tipo' => 'doughnut',
                'labels' => ['Cardiopatia congênita confirmada', 'Sem cardiopatia congênita confirmada'],
                'valores' => [$chdSim, max(0, $totalConsultas - $chdSim)],
            ],
        ];

        $etniaExpr = "COALESCE(NULLIF(TRIM(etnia), ''), 'Não informada')";
        $etnias = Consulta::query()
            ->selectRaw("{$etniaExpr} as rotulo, COUNT(*) as total")
            ->groupByRaw($etniaExpr)
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'rotulo');

        if ($etnias->isNotEmpty()) {
            $charts[] = [
                'slug' => 'etnia',
                'titulo' => 'Distribuição por etnia',
                'tipo' => 'bar',
                'labels' => $etnias->keys()->values()->all(),
                'valores' => $etnias->values()->map(fn ($n) => (int) $n)->all(),
            ];
        }

        $igFaixas = [
            '1º trim. (<14)' => (int) Consulta::query()->where('idade_gestacional', '<', 14)->count(),
            '2º trim. (14-27)' => (int) Consulta::query()
                ->where('idade_gestacional', '>=', 14)
                ->where('idade_gestacional', '<', 28)
                ->count(),
            '3º trim. (≥28)' => (int) Consulta::query()->where('idade_gestacional', '>=', 28)->count(),
        ];
        $charts[] = [
            'slug' => 'distribuicao',
            'titulo' => 'Idade gestacional',
            'tipo' => 'bar',
            'labels' => array_keys($igFaixas),
            'valores' => array_values($igFaixas),
        ];

        $fatores = [
            'Diabetes' => (int) Consulta::query()
                ->where(function ($q) {
                    $q->where('diabetes_gestacional', true)->orWhere('diabetes_pre_gestacional', true);
                })->count(),
            'HAS' => (int) Consulta::query()
                ->where(function ($q) {
                    $q->where('hipertensao', true)->orWhere('hipertensao_cronica', true);
                })->count(),
            'Tabagismo' => (int) Consulta::query()->where('tabagismo', true)->count(),
            'Obesidade' => (int) Consulta::query()->where('obesidade_pre_gestacional', true)->count(),
        ];
        $charts[] = [
            'slug' => 'fatores_risco',
            'titulo' => 'Fatores de risco (consultas)',
            'tipo' => 'bar',
            'labels' => array_keys($fatores),
            'valores' => array_values($fatores),
        ];

        $risco = $this->distribuicaoRisco();
        if ($risco['total_gestantes_analisadas'] > 0) {
            $charts[] = [
                'slug' => 'risco',
                'titulo' => 'Distribuição de risco',
                'tipo' => 'doughnut',
                'labels' => ['Baixo', 'Moderado', 'Alto'],
                'valores' => [$risco['baixo'], $risco['moderado'], $risco['alto']],
            ];

            $hist = $this->histogramaProbabilidadeCcf();
            $charts[] = [
                'slug' => 'probabilidade',
                'titulo' => 'Distribuição de probabilidade',
                'tipo' => 'bar',
                'labels' => $hist['labels'],
                'valores' => $hist['valores'],
            ];

            $eco = $this->distribuicaoPrioridadeEco();
            $charts[] = [
                'slug' => 'prioridade_eco',
                'titulo' => 'Priorização ecocardiograma',
                'tipo' => 'bar',
                'labels' => ['Urgente', 'Alta', 'Moderada', 'Baixa', 'Sem eco'],
                'valores' => [$eco['urgente'], $eco['alta'], $eco['moderada'], $eco['baixa'], $eco['sem_eco']],
            ];
        }

        return $charts;
    }

    /**
     * Slugs e rótulos dos gráficos descritivos servidos pela API GestRisk.
     *
     * @return list<array{slug: string, titulo: string}>
     */
    public static function catalogoGraficosDescritivos(): array
    {
        return [
            ['slug' => 'prevalencia', 'titulo' => 'Prevalência de CCF'],
            ['slug' => 'distribuicao', 'titulo' => 'Idade, IMC e IG'],
            ['slug' => 'fatores_risco', 'titulo' => 'Cardiopatia congênita por fatores de risco'],
            ['slug' => 'risco', 'titulo' => 'Distribuição de risco (cohort)'],
            ['slug' => 'probabilidade', 'titulo' => 'Distribuição de probabilidade'],
            ['slug' => 'prioridade_eco', 'titulo' => 'Priorização ecocardiograma'],
            ['slug' => 'intercorrencias_cat', 'titulo' => 'Intercorrências por categoria'],
            ['slug' => 'top_intercorrencias', 'titulo' => 'Top intercorrências'],
            ['slug' => 'importancia', 'titulo' => 'Importância das features'],
            ['slug' => 'experimentos', 'titulo' => 'Comparativo de experimentos'],
            ['slug' => 'etnia', 'titulo' => 'Distribuição por etnia'],
        ];
    }

    /**
     * Título amigável do gráfico descritivo (fallback = slug humanizado).
     */
    public static function tituloGraficoDescritivo(string $slug): string
    {
        foreach (self::catalogoGraficosDescritivos() as $item) {
            if (($item['slug'] ?? '') === $slug) {
                return (string) $item['titulo'];
            }
        }

        return ucfirst(str_replace('_', ' ', $slug));
    }

    public static function labelEvento(string $tipo): string
    {
        return match ($tipo) {
            AnaliseHistorico::EVENTO_CADASTRO => 'Cadastro',
            AnaliseHistorico::EVENTO_CONSULTA => 'Nova consulta',
            AnaliseHistorico::EVENTO_ALTERACAO => 'Alteração clínica',
            AnaliseHistorico::EVENTO_EXAME => 'Exame',
            AnaliseHistorico::EVENTO_INTERCORRENCIA => 'Intercorrência',
            AnaliseHistorico::EVENTO_REPROCESSAMENTO => 'Reprocessamento',
            default => ucfirst(str_replace('_', ' ', $tipo)),
        };
    }

    public static function labelRisco(?string $risco): string
    {
        return match ($risco) {
            'baixo' => 'Baixo',
            'moderado' => 'Moderado',
            'alto' => 'Alto',
            default => 'Não informado',
        };
    }

    /**
     * Classe CSS da pill de risco clínico (tokens --risk-*).
     */
    public static function pillClassRisco(?string $risco): string
    {
        return match ($risco) {
            'baixo' => 'consulta-pill--risk-low',
            'moderado' => 'consulta-pill--risk-mid',
            'alto' => 'consulta-pill--risk-high',
            default => 'consulta-pill--info',
        };
    }

    public static function labelPrioridadeEco(?string $prioridade): string
    {
        return match ($prioridade) {
            'urgente' => 'Urgente',
            'alta' => 'Alta',
            'moderada' => 'Moderada',
            'baixa' => 'Baixa',
            default => 'Não informado',
        };
    }

    /**
     * Normaliza SHAP da API ({feature, impact}) ou GestRisk ({top_contribuicoes: [...]})
     * para o formato do gráfico Chart.js. Default: análise completa (36 features).
     *
     * @return list<array{nome: string, percentual_contribuicao: float, direcao: string, impact: float}>
     */
    public static function normalizeShapItems(mixed $shap, int $limit = 36): array
    {
        if (! is_array($shap) || $shap === []) {
            return [];
        }

        if (isset($shap['top_contribuicoes']) && is_array($shap['top_contribuicoes'])) {
            $raw = $shap['top_contribuicoes'];
        } elseif (array_is_list($shap)) {
            $raw = $shap;
        } else {
            return [];
        }

        $rows = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $nome = (string) ($item['nome'] ?? $item['campo'] ?? $item['feature'] ?? 'Não informado');
            $impact = (float) ($item['impact'] ?? $item['impacto'] ?? $item['impacto_absoluto'] ?? 0);

            if (isset($item['percentual_contribuicao']) && is_numeric($item['percentual_contribuicao'])) {
                $pct = (float) $item['percentual_contribuicao'];
                $direcao = (string) ($item['direcao'] ?? ($impact >= 0 ? 'aumenta_risco' : 'reduz_risco'));
            } else {
                $pct = abs($impact);
                $direcao = (string) ($item['direcao'] ?? ($impact >= 0 ? 'aumenta_risco' : 'reduz_risco'));
            }

            $rows[] = [
                'nome' => $nome,
                'percentual_contribuicao' => $pct,
                'direcao' => $direcao,
                'impact' => $impact,
            ];
        }

        if ($rows === []) {
            return [];
        }

        // Se vieram impactos brutos (soma típica << 100), converte para % relativa.
        $soma = array_sum(array_column($rows, 'percentual_contribuicao'));
        if ($soma > 0 && $soma <= 1.5) {
            foreach ($rows as &$row) {
                $row['percentual_contribuicao'] = round(($row['percentual_contribuicao'] / $soma) * 100, 1);
            }
            unset($row);
        } elseif ($soma > 1.5 && $soma < 40) {
            foreach ($rows as &$row) {
                $row['percentual_contribuicao'] = round(($row['percentual_contribuicao'] / $soma) * 100, 1);
            }
            unset($row);
        }

        usort($rows, static fn ($a, $b) => $b['percentual_contribuicao'] <=> $a['percentual_contribuicao']);

        return array_slice($rows, 0, max(1, $limit));
    }
}
