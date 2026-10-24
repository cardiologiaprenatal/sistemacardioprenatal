@extends('layouts.app')

@section('title', ($nomeExibicao ?? $idGestante ?? 'Gestante').'. Análise de risco')

@section('content')
@php
    use App\Services\DashboardAnaliseService;

    $clinicos = is_array($gestante['dados_clinicos'] ?? null) ? $gestante['dados_clinicos'] : [];
    $features = is_array($gestante['features_modelo'] ?? null) ? $gestante['features_modelo'] : [];
    $shapNorm = (! empty($predicao['shap']))
        ? DashboardAnaliseService::normalizeShapItems($predicao['shap'], 36)
        : [];

    $fmtNum = static function ($v, int $dec = 1) {
        if ($v === null || $v === '') {
            return 'Não informado';
        }
        if (! is_numeric($v)) {
            return (string) $v;
        }

        return number_format((float) $v, $dec, ',', '.');
    };

    $idade = $clinicos['idade_materna'] ?? $features['idade'] ?? null;
    $imc = $clinicos['imc_pre_gestacional'] ?? $features['imc'] ?? null;
    $ig = $clinicos['idade_gestacional'] ?? $features['idade_gestacional'] ?? null;
    $etnia = $clinicos['etnia'] ?? null;

    $risco = is_array($predicao) ? ($predicao['classificacao_risco'] ?? null) : null;
    $riscoClass = DashboardAnaliseService::pillClassRisco($risco);
@endphp

<div class="page-stack">

        <div class="main-card" style="margin-bottom: 0;">
            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px;">
                <div style="min-width: 0; flex: 1 1 220px;">
                    <p style="font-size: 12px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px;">
                        Análise de risco
                    </p>
                    <h1 class="page-title" style="margin: 0;">{{ $nomeExibicao ?? $idGestante }}</h1>
                    <p class="page-subtitle" style="margin: 8px 0 0;">
                        {{ $idGestante }}. Predição individual de risco de cardiopatia congênita.
                    </p>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                    <a href="{{ route('cohort.gestantes.index') }}" class="btn-secondary-outline" style="text-decoration: none;">
                        ← Voltar à lista
                    </a>
                    @if (! empty($gestanteLocalUrl))
                        <a href="{{ $gestanteLocalUrl }}" class="btn-secondary-outline" style="text-decoration: none;">
                            Abrir cadastro
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="main-card" role="status" style="margin-bottom: 0; border-left: 4px solid var(--success); background: rgba(39, 174, 96, 0.08);">
                <p style="margin: 0; font-size: 15px; color: var(--text);">{{ session('success') }}</p>
            </div>
        @endif
        @if (session('error') || $error)
            <div class="main-card" role="alert" style="margin-bottom: 0; border-left: 4px solid var(--accent); background: rgba(192, 57, 43, 0.06);">
                <p style="margin: 0; font-size: 15px; color: var(--text);">{{ session('error') ?? $error }}</p>
            </div>
        @endif

        @if ($gestante)
            <div class="dash-kpis" style="margin-bottom: 0;">
                <div class="dash-kpi">
                    <div class="dash-kpi-label">Idade</div>
                    <div class="dash-kpi-value">{{ $fmtNum($idade) }}</div>
                </div>
                <div class="dash-kpi">
                    <div class="dash-kpi-label">IMC</div>
                    <div class="dash-kpi-value">{{ $fmtNum($imc) }}</div>
                </div>
                <div class="dash-kpi">
                    <div class="dash-kpi-label">IG (sem.)</div>
                    <div class="dash-kpi-value">{{ $fmtNum($ig) }}</div>
                </div>
                <div class="dash-kpi">
                    <div class="dash-kpi-label">Etnia</div>
                    <div class="dash-kpi-value" style="font-size: 1.2rem;">{{ $etnia ?: 'Não informado' }}</div>
                </div>
            </div>

            <div class="main-card" style="margin-bottom: 0;">
                <div class="card-toolbar">
                    <div>
                        <h2 class="card-title" style="margin: 0; font-size: 18px;">Predição de risco</h2>
                        <p style="margin: 6px 0 0; font-size: 13px; color: var(--muted);">
                            Classificação assistida. Não altera o cadastro clínico
                        </p>
                    </div>
                    <form method="POST" action="{{ route('cohort.gestantes.predict', $idGestante) }}">
                        @csrf
                        <button type="submit" class="btn-primary-custom">
                            {{ $predicao ? 'Calcular novamente' : 'Calcular risco' }}
                        </button>
                    </form>
                </div>

                @if ($predicao)
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 14px 0 4px;">
                        @if ($risco)
                            <span class="consulta-pill {{ $riscoClass }}">{{ DashboardAnaliseService::labelRisco($risco) }}</span>
                        @endif
                        @if (isset($predicao['probabilidade_risco']))
                            <span style="font-size: 14px; color: var(--muted);">
                                {{ number_format((float) $predicao['probabilidade_risco'] * 100, 1, ',', '.') }}% de cardiopatia congênita
                            </span>
                        @endif
                    </div>

                    <div class="dash-kpis" style="margin-top: 12px; margin-bottom: 0;">
                        <div class="dash-kpi">
                            <div class="dash-kpi-label">Classificação</div>
                            <div class="dash-kpi-value {{ $risco === 'alto' ? 'dash-kpi-value--risk-high' : ($risco === 'moderado' ? 'dash-kpi-value--warn' : ($risco === 'baixo' ? 'dash-kpi-value--ok' : 'dash-kpi-value--accent')) }}" style="font-size: 1.35rem;">
                                {{ $risco ? DashboardAnaliseService::labelRisco($risco) : 'Não informado' }}
                            </div>
                        </div>
                        <div class="dash-kpi">
                            <div class="dash-kpi-label">Probabilidade</div>
                            <div class="dash-kpi-value">
                                {{ isset($predicao['probabilidade_risco']) ? number_format((float) $predicao['probabilidade_risco'] * 100, 1, ',', '.').'%' : 'Não informado' }}
                            </div>
                        </div>
                        <div class="dash-kpi">
                            <div class="dash-kpi-label">Classe</div>
                            <div class="dash-kpi-value">{{ $predicao['class'] ?? 'Não informado' }}</div>
                        </div>
                        <div class="dash-kpi">
                            <div class="dash-kpi-label">Tempo (ms)</div>
                            <div class="dash-kpi-value" style="font-size: 1.35rem;">{{ $predicao['execution_time_ms'] ?? 'Não informado' }}</div>
                        </div>
                        <div class="dash-kpi">
                            <div class="dash-kpi-label">Fonte</div>
                            <div class="dash-kpi-value" style="font-size: 1.15rem;">
                                {{ ($predicao['fonte'] ?? '') === 'catboost' ? 'CatBoost' : (($predicao['fonte'] ?? '') === 'heuristica' ? 'Estimativa' : ($predicao['modelo'] ?? 'Não informado')) }}
                            </div>
                        </div>
                    </div>

                    @if (! empty($predicao['shap']))
                        <h3 style="margin: 20px 0 10px; font-size: 15px;">Contribuição das variáveis</h3>
                        @if ($shapNorm !== [])
                            <div class="analise-shap-canvas" style="position:relative;height:{{ max(220, count($shapNorm) * 22) }}px;margin-bottom:12px;">
                                <canvas id="cohort-shap-chart" aria-label="Gráfico da contribuição das variáveis"></canvas>
                            </div>
                        @endif
                        <div class="table-container table-container--flush">
                            <table class="data-table data-table--compact">
                                <thead>
                                    <tr>
                                        <th>Feature</th>
                                        <th class="td-num">Contribuição</th>
                                        <th>Direção</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($shapNorm !== [] ? $shapNorm : $predicao['shap'] as $item)
                                        <tr>
                                            <td>{{ $item['nome'] ?? $item['feature'] ?? 'Não informado' }}</td>
                                            <td class="td-num">
                                                @if (isset($item['percentual_contribuicao']))
                                                    {{ number_format((float) $item['percentual_contribuicao'], 1, ',', '.') }}%
                                                @elseif (isset($item['impact']))
                                                    {{ number_format((float) $item['impact'], 4, ',', '.') }}
                                                @else
                                                    Não informado
                                                @endif
                                            </td>
                                            <td>
                                                @if (($item['direcao'] ?? '') === 'aumenta_risco' || (isset($item['impact']) && (float) $item['impact'] >= 0))
                                                    ↑ risco
                                                @else
                                                    ↓ risco
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($shapNorm !== [])
                            @push('scripts')
                                @include('partials.chart-lib')
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const items = @json($shapNorm);
                                        GestRiskCharts.ready(function () {
                                            GestRiskCharts.shapHorizontal('cohort-shap-chart', items);
                                        });
                                    });
                                </script>
                            @endpush
                        @endif
                    @endif
                @else
                    <p class="dash-empty" style="text-align: left; padding: 16px 0 4px;">
                        Ainda sem predição nesta sessão. Clique em <strong>Rodar predição</strong>.
                    </p>
                @endif
            </div>

            <div class="main-card" style="margin-bottom: 0;">
                <h2 class="card-title" style="font-size: 18px; margin-bottom: 12px;">Dados clínicos</h2>
                <div class="table-container table-container--flush">
                    <table class="data-table data-table--compact">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($clinicos as $campo => $valor)
                                <tr>
                                    <td><code>{{ $campo }}</code></td>
                                    <td>
                                        @if (is_bool($valor))
                                            {{ $valor ? 'sim' : 'não' }}
                                        @elseif (is_float($valor) || (is_numeric($valor) && str_contains((string) $valor, '.')))
                                            {{ $fmtNum($valor, 2) }}
                                        @elseif (is_scalar($valor) || $valor === null)
                                            {{ $valor ?? 'Não informado' }}
                                        @else
                                            {{ json_encode($valor, JSON_UNESCAPED_UNICODE) }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="dash-empty">Nenhum dado clínico retornado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif (! $error)
            <div class="main-card" style="margin-bottom: 0;">
                <p class="dash-empty">Gestante não encontrada no cohort.</p>
            </div>
        @endif
</div>
@endsection
