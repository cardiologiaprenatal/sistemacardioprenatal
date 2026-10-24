@php
    use App\Services\DashboardAnaliseService;
@endphp

@if ($analises->isEmpty())
    <div class="main-card" style="text-align: center; color: var(--muted); padding: 32px 20px;">
        <p style="margin: 0 0 8px;">Nenhuma análise registrada para esta gestante.</p>
        @if (isset($gestante) && $gestante->consultas->isNotEmpty())
            <p style="margin: 0; font-size: 13px; line-height: 1.45;">
                Há consulta cadastrada. A análise pode estar em processamento. Atualize a página em instantes.
            </p>
        @else
            <p style="margin: 0; font-size: 13px;">Cadastre uma consulta para disparar a análise automaticamente.</p>
        @endif
    </div>
@else
    <div class="main-card">
        <h2 class="card-title" style="font-size: 20px; margin-bottom: 6px;">Histórico de análises</h2>
        <p class="page-subtitle" style="margin: 0 0 20px;">Inclui classificação de risco e a contribuição das variáveis do modelo.</p>

        <div class="analise-timeline">
            @foreach ($analises as $idx => $analise)
                @php
                    $numero = $analises->count() - $idx;
                    $risco = $analise->classificacao_risco;
                    $riscoClass = DashboardAnaliseService::pillClassRisco($risco);
                    $shapTop = collect(DashboardAnaliseService::normalizeShapItems($analise->shap, 36));
                    $resumo = $analise->analise['resumo_clinico']['simplificado']
                        ?? $analise->analise['resumo_clinico']
                        ?? null;
                    if (is_array($resumo)) {
                        $resumo = $resumo['simplificado'] ?? null;
                    }
                @endphp
                <article class="analise-timeline-item">
                    <div class="analise-timeline-marker" aria-hidden="true"></div>
                    <div class="analise-timeline-body">
                        <div class="analise-timeline-head">
                            <div>
                                <span class="analise-timeline-num">Análise #{{ $numero }}</span>
                                <span class="analise-timeline-evento">{{ DashboardAnaliseService::labelEvento($analise->evento_tipo) }}</span>
                                <p class="analise-timeline-data">{{ $analise->created_at?->format('d/m/Y H:i') ?? 'Não informado' }}</p>
                            </div>
                            <div class="analise-timeline-badges">
                                @if ($analise->status === 'erro')
                                    <span class="consulta-pill consulta-pill--alert">Erro</span>
                                @else
                                    <span class="consulta-pill {{ $riscoClass }}">{{ DashboardAnaliseService::labelRisco($risco) }}</span>
                                    @php
                                        $fonteAnalise = $analise->analise['fonte'] ?? $analise->predicao['fonte'] ?? null;
                                    @endphp
                                    @if ($fonteAnalise === 'catboost')
                                        <span class="consulta-pill">Modelo de risco</span>
                                    @elseif ($fonteAnalise === 'heuristica')
                                        <span class="consulta-pill consulta-pill--alert">Estimativa</span>
                                    @endif
                                    @if ($analise->probabilidade_ccf !== null)
                                        <span class="analise-timeline-prob">{{ number_format($analise->probabilidade_ccf * 100, 1) }}% de cardiopatia congênita</span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if ($analise->status === 'concluida')
                            <div class="analise-timeline-grid">
                                @if ($analise->recomenda_ecocardiograma)
                                    <div class="analise-timeline-meta">
                                        <span class="form-label">Ecocardiograma</span>
                                        <p class="consulta-valor">
                                            Recomendado:
                                            {{ DashboardAnaliseService::labelPrioridadeEco($analise->prioridade_ecocardiograma) }}
                                            @if ($analise->score_prioridade !== null)
                                                (score {{ number_format($analise->score_prioridade, 0) }})
                                            @endif
                                        </p>
                                    </div>
                                @endif
                                @if (is_string($resumo) && $resumo !== '')
                                    <div class="analise-timeline-meta analise-timeline-meta--wide">
                                        <span class="form-label">Resumo clínico</span>
                                        <p class="consulta-valor" style="font-weight: 400; line-height: 1.5;">{{ $resumo }}</p>
                                    </div>
                                @endif
                            </div>

                            @if ($shapTop->isNotEmpty())
                                <div class="analise-shap-chart-wrap">
                                    <p class="form-label" style="margin-bottom: 10px;">Contribuição das variáveis. Análise completa ({{ $shapTop->count() }} variáveis)</p>
                                    <div class="analise-shap-canvas" style="height: {{ max(280, $shapTop->count() * 24) }}px; min-height: {{ max(280, $shapTop->count() * 24) }}px;">
                                        <canvas id="shap-chart-{{ $analise->id }}" aria-label="Gráfico da contribuição das variáveis"></canvas>
                                    </div>
                                </div>
                                <details class="analise-timeline-shap">
                                    <summary>Ver lista de fatores</summary>
                                    <ul>
                                        @foreach ($shapTop as $item)
                                            <li>
                                                <strong>{{ $item['nome'] }}</strong>
                                                : {{ number_format($item['percentual_contribuicao'], 1) }}%
                                                <span style="color: var(--muted);">
                                                    ({{ ($item['direcao'] ?? '') === 'aumenta_risco' ? '↑ risco' : '↓ risco' }})
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <p style="margin: 10px 0 0; font-size: 13px; color: var(--muted);">
                                    Esta análise não possui a contribuição das variáveis.
                                </p>
                            @endif
                        @elseif ($analise->erro_mensagem)
                            <p style="margin: 8px 0 0; font-size: 13px; color: var(--accent);">{{ $analise->erro_mensagem }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
@endif

<style>
    .analise-timeline { position: relative; padding-left: 20px; border-left: 2px solid var(--border); }
    .analise-timeline-item { position: relative; padding-bottom: 24px; }
    .analise-timeline-item:last-child { padding-bottom: 0; }
    .analise-timeline-marker {
        position: absolute; left: -27px; top: 6px;
        width: 12px; height: 12px; border-radius: 50%;
        background: var(--primary); border: 2px solid var(--surface);
        box-shadow: 0 0 0 2px var(--border);
    }
    .analise-timeline-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
    .analise-timeline-num { font-weight: 700; color: var(--primary); font-size: 15px; margin-right: 8px; }
    .analise-timeline-evento { font-size: 13px; color: var(--muted); }
    .analise-timeline-data { margin: 4px 0 0; font-size: 13px; color: var(--muted); }
    .analise-timeline-badges { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .analise-timeline-prob { font-size: 13px; font-weight: 600; color: var(--text); }
    .analise-timeline-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .analise-timeline-meta--wide { grid-column: 1 / -1; }
    .analise-timeline-shap { margin-top: 12px; font-size: 13px; }
    .analise-timeline-shap summary { cursor: pointer; font-weight: 600; color: var(--primary); }
    .analise-timeline-shap ul { margin: 8px 0 0; padding-left: 18px; color: var(--text); }
    .analise-shap-chart-wrap { margin-top: 14px; }
    .analise-shap-canvas {
        position: relative; width: 100%; max-width: 100%;
        height: 280px; min-height: 280px;
        background: rgba(253, 240, 240, 0.35);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 8px 6px;
    }
    .analise-shap-canvas canvas { width: 100% !important; height: 100% !important; }
</style>

@php
    $shapChartPayload = $analises
        ->filter(fn ($a) => $a->status === 'concluida')
        ->mapWithKeys(function ($a) {
            $items = DashboardAnaliseService::normalizeShapItems($a->shap, 36);

            return $items === [] ? [] : [$a->id => $items];
        })
        ->all();
@endphp

@if (! empty($shapChartPayload))
    @push('scripts')
        @include('partials.chart-lib')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const payloads = @json($shapChartPayload);
                GestRiskCharts.ready(function () {
                    Object.entries(payloads).forEach(function (entry) {
                        GestRiskCharts.shapHorizontal('shap-chart-' + entry[0], entry[1]);
                    });
                });
            });
        </script>
    @endpush
@endif
