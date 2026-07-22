<?php

namespace App\Services;

use App\Models\AnaliseHistorico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardAnaliseService
{
    /**
     * Última análise concluída por gestante (uma linha por gestante).
     */
    public function obterUltimasAnalisesPorGestante(): Collection
    {
        $ids = AnaliseHistorico::query()
            ->select(DB::raw('MAX(id) as id'))
            ->where('status', 'concluida')
            ->groupBy('gestante_id')
            ->pluck('id');

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
        $ultimas = $this->obterUltimasAnalisesPorGestante();
        $total = $ultimas->count();

        $baixo = $ultimas->where('classificacao_risco', 'baixo')->count();
        $moderado = $ultimas->where('classificacao_risco', 'moderado')->count();
        $alto = $ultimas->where('classificacao_risco', 'alto')->count();

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
        return $this->obterUltimasAnalisesPorGestante()
            ->filter(fn (AnaliseHistorico $a) => $a->recomenda_ecocardiograma)
            ->sortByDesc(fn (AnaliseHistorico $a) => (float) ($a->score_prioridade ?? 0))
            ->take($limite)
            ->values();
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
        $ultimas = $this->obterUltimasAnalisesPorGestante();
        $media = $ultimas->avg('probabilidade_ccf');

        return [
            'total_analises' => AnaliseHistorico::where('status', 'concluida')->count(),
            'total_erros' => AnaliseHistorico::where('status', 'erro')->count(),
            'media_probabilidade_ccf' => $media !== null ? round((float) $media * 100, 2) : null,
            'gestantes_com_eco_recomendada' => $ultimas->filter(fn ($a) => $a->recomenda_ecocardiograma)->count(),
        ];
    }

    public function analisesRecentes(int $limite = 10): Collection
    {
        return AnaliseHistorico::query()
            ->with(['gestante'])
            ->where('status', 'concluida')
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get();
    }

    /**
     * @return array{urgente: int, alta: int, moderada: int, baixa: int, sem_eco: int}
     */
    public function distribuicaoPrioridadeEco(): array
    {
        $ultimas = $this->obterUltimasAnalisesPorGestante();

        return [
            'urgente' => $ultimas->where('prioridade_ecocardiograma', 'urgente')->count(),
            'alta' => $ultimas->where('prioridade_ecocardiograma', 'alta')->count(),
            'moderada' => $ultimas->where('prioridade_ecocardiograma', 'moderada')->count(),
            'baixa' => $ultimas->where('prioridade_ecocardiograma', 'baixa')->count(),
            'sem_eco' => $ultimas->filter(fn ($a) => ! $a->recomenda_ecocardiograma)->count(),
        ];
    }

    /**
     * Histograma de probabilidade CCF (última análise por gestante).
     *
     * @return array{labels: list<string>, valores: list<int>}
     */
    public function histogramaProbabilidadeCcf(int $faixas = 10): array
    {
        $ultimas = $this->obterUltimasAnalisesPorGestante()
            ->filter(fn (AnaliseHistorico $a) => $a->probabilidade_ccf !== null);

        $labels = [];
        $valores = array_fill(0, $faixas, 0);

        for ($i = 0; $i < $faixas; $i++) {
            $min = $i * 10;
            $max = ($i + 1) * 10;
            $labels[] = $i === $faixas - 1
                ? sprintf('%d–100%%', $min)
                : sprintf('%d–%d%%', $min, $max);
        }

        foreach ($ultimas as $analise) {
            $pct = (float) $analise->probabilidade_ccf * 100;
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
     * Slugs e rótulos dos gráficos descritivos servidos pela API GestRisk.
     *
     * @return list<array{slug: string, titulo: string}>
     */
    public static function catalogoGraficosDescritivos(): array
    {
        return [
            ['slug' => 'prevalencia', 'titulo' => 'Prevalência de CCF'],
            ['slug' => 'distribuicao', 'titulo' => 'Idade, IMC e IG'],
            ['slug' => 'fatores_risco', 'titulo' => 'CCF por fatores de risco'],
            ['slug' => 'risco', 'titulo' => 'Distribuição de risco (cohort)'],
            ['slug' => 'probabilidade', 'titulo' => 'Distribuição de probabilidade'],
            ['slug' => 'prioridade_eco', 'titulo' => 'Priorização ecocardiograma'],
            ['slug' => 'importancia', 'titulo' => 'Importância das features'],
            ['slug' => 'intercorrencias_cat', 'titulo' => 'Intercorrências por categoria'],
        ];
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
            default => '—',
        };
    }

    public static function labelPrioridadeEco(?string $prioridade): string
    {
        return match ($prioridade) {
            'urgente' => 'Urgente',
            'alta' => 'Alta',
            'moderada' => 'Moderada',
            'baixa' => 'Baixa',
            default => '—',
        };
    }
}
