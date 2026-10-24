@extends('layouts.app')

@section('title', 'Dashboard. Cardioprenatal')

@section('content')

@php
    use App\Services\DashboardAnaliseService;

    $dr = $distribuicaoRisco;
    $im = $indicadoresModelo;
    $gp = $graficosPopulacionais;
    $hh = $dashHealth ?? [];
    $temAnalises = ($dr['total_gestantes_analisadas'] ?? 0) > 0;

    $mm = $modelosMl ?? [];
    $mmOk = (bool) ($mm['disponivel'] ?? false) && ! empty($mm['modelos']);
    $mmDestaques = is_array($mm['destaques'] ?? null) ? $mm['destaques'] : [];
    $mmProducao = is_array($mm['producao'] ?? null) ? $mm['producao'] : [];
    $mmMelhorF1 = $mmDestaques['melhor_f1_cv']['modelo'] ?? null;
    $fmtPct = static fn ($v) => $v === null || $v === '' ? 'Não informado' : number_format((float) $v * 100, 1, ',', '.').'%';
    $destaqueLabels = [
        'melhor_f1_cv' => 'Melhor F1 (CV)',
        'melhor_f1_teste' => 'Melhor F1 (teste)',
        'melhor_roc_auc_teste' => 'Melhor ROC-AUC',
        'maior_acuracia_teste' => 'Maior acurácia',
    ];

    $ad = $analiseDescritiva ?? [];
    $gd = $graficosDescritivos ?? [];
    $gc = $graficosCohort ?? [];
    $slugsSaidaModelo = ['risco', 'probabilidade', 'prioridade_eco', 'importancia', 'experimentos'];
    $ehGraficoVariavel = static function (array $g) use ($slugsSaidaModelo): bool {
        return ! in_array((string) ($g['slug'] ?? ''), $slugsSaidaModelo, true);
    };
    $gcVariaveis = array_values(array_filter(is_array($gc) ? $gc : [], $ehGraficoVariavel));
    $gdVariaveis = array_values(array_filter(is_array($gd) ? $gd : [], $ehGraficoVariavel));
    $descOk = count($gdVariaveis) > 0 || count($gcVariaveis) > 0;
    $gruposVariaveis = [
        'sociodemograficas' => [
            'id' => 'socio',
            'titulo' => 'Características sociodemográficas',
            'texto' => 'Perfil da população segundo variáveis como etnia.',
            'graficos' => [],
            'figuras' => [],
        ],
        'antropometricas' => [
            'id' => 'antropo',
            'titulo' => 'Características antropométricas',
            'texto' => 'Idade, IMC e demais medidas já disponíveis na base.',
            'graficos' => [],
            'figuras' => [],
        ],
        'clinicas' => [
            'id' => 'clinicas',
            'titulo' => 'Características clínicas',
            'texto' => 'Comorbidades, intercorrências e desfechos agregados.',
            'graficos' => [],
            'figuras' => [],
        ],
        'obstetricas' => [
            'id' => 'obstetricas',
            'titulo' => 'Características obstétricas',
            'texto' => 'Idade gestacional e demais variáveis do pré-natal.',
            'graficos' => [],
            'figuras' => [],
        ],
    ];
    $mapaCanvas = [
        'etnia' => 'sociodemograficas',
        'distribuicao' => 'obstetricas',
        'fatores_risco' => 'clinicas',
        'intercorrencias_cat' => 'clinicas',
        'top_intercorrencias' => 'clinicas',
        'prevalencia' => 'clinicas',
    ];
    $mapaFiguras = [
        'etnia' => 'sociodemograficas',
        'distribuicao' => 'antropometricas',
        'fatores_risco' => 'clinicas',
        'intercorrencias_cat' => 'clinicas',
        'top_intercorrencias' => 'clinicas',
        'prevalencia' => 'clinicas',
    ];
    foreach ($gcVariaveis as $grafico) {
        $grupo = $mapaCanvas[(string) ($grafico['slug'] ?? '')] ?? null;
        if ($grupo !== null) {
            $gruposVariaveis[$grupo]['graficos'][] = $grafico;
        }
    }
    $gdOutros = [];
    foreach ($gdVariaveis as $figura) {
        $grupo = $mapaFiguras[(string) ($figura['slug'] ?? '')] ?? null;
        if ($grupo !== null) {
            $gruposVariaveis[$grupo]['figuras'][] = $figura;
        } else {
            $gdOutros[] = $figura;
        }
    }
@endphp

<style>
    .dash { max-width: 1120px; margin: 0 auto; }

    .dash-hero {
        margin: 0 0 18px;
        padding: clamp(18px, 3vw, 24px);
        border-radius: 16px;
        border: 1px solid var(--border);
        background: var(--surface);
    }
    .dash-hero-top {
        display: flex; flex-wrap: wrap; align-items: flex-start;
        justify-content: space-between; gap: 16px 28px;
        margin-bottom: 16px;
    }
    .dash-brand {
        display: block;
        margin: 0 0 8px;
        line-height: 0;
    }
    .dash-hero h1 {
        font-family: 'DM Sans', sans-serif;
        font-size: 13px; font-weight: 600;
        letter-spacing: 0.04em; text-transform: uppercase;
        color: var(--muted); margin: 0; line-height: 1.2;
    }
    .dash-hero-actions {
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
    }
    .dash-date {
        font-size: 12px; font-weight: 600; color: var(--muted);
        padding: 8px 12px; border-radius: 10px;
        border: 1px solid var(--border); background: #fff;
    }
    .dash-btn {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; text-decoration: none;
        padding: 9px 14px; border-radius: 10px; border: 1px solid var(--border);
        background: #fff; color: var(--primary);
        transition: background .15s ease, border-color .15s ease;
    }
    .dash-btn:hover { background: var(--accent-light); border-color: #e8bcbc; }
    .dash-btn--primary {
        background: var(--primary);
        color: #fff; border-color: var(--primary);
    }
    .dash-btn--primary:hover {
        background: var(--accent);
        border-color: var(--accent);
    }

    .dash-snapshot {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    .dash-snap {
        padding: 12px 14px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid var(--border);
        text-decoration: none; color: inherit;
        transition: border-color .15s ease;
    }
    a.dash-snap:hover { border-color: #e8bcbc; }
    .dash-snap-label {
        font-size: 11px; font-weight: 600;
        color: var(--muted); margin-bottom: 4px;
    }
    .dash-snap-value {
        font-family: 'DM Serif Display', serif;
        font-size: clamp(1.25rem, 2.8vw, 1.55rem);
        color: var(--text); line-height: 1.1;
        font-variant-numeric: tabular-nums;
    }
    .dash-snap-value--accent { color: var(--accent); }
    .dash-snap-value--warn { color: var(--risk-high); }
    .dash-snap-hint { margin-top: 3px; font-size: 11px; color: var(--muted); }

    .dash-anchor {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        margin: 0 0 28px;
        padding: 4px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--surface);
    }
    .dash-anchor a,
    .dash-anchor button {
        flex: 1 1 auto;
        min-width: 7.5rem;
        text-align: center;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        color: var(--muted);
        padding: 10px 14px;
        border-radius: 10px;
        border: none;
        background: transparent;
        font-family: inherit;
        cursor: pointer;
        transition: color .15s ease, background .15s ease;
    }
    .dash-anchor a:hover,
    .dash-anchor button:hover {
        color: var(--primary);
        background: var(--accent-light);
    }
    .dash-anchor a:focus-visible,
    .dash-anchor button:focus-visible {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
    }
    .dash-anchor button.is-active {
        color: #fff;
        background: linear-gradient(135deg, var(--primary), #a01020);
    }
    .dash-tab-panel[hidden] { display: none !important; }
    .dash-subnav {
        display: flex; flex-wrap: wrap; gap: 8px;
        margin: 0 0 22px;
    }
    .dash-subnav a {
        font-size: 12px; font-weight: 600; text-decoration: none;
        color: var(--muted); padding: 6px 10px; border-radius: 8px;
        border: 1px solid var(--border); background: #fff;
    }
    .dash-subnav a:hover { color: var(--primary); border-color: #e8bcbc; }
    .dash-pick { cursor: pointer; }
    .dash-pick.is-selected td { background: var(--accent-light) !important; }
    .dash-individual-layout {
        display: grid; gap: 16px; margin-bottom: 18px;
    }
    @media (min-width: 960px) {
        .dash-individual-layout { grid-template-columns: 1.2fr 0.8fr; align-items: start; }
    }
    .dash-individual-detalhe { position: sticky; top: 80px; }
    .dash-individual-kpis {
        display: grid; gap: 8px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin: 12px 0 14px;
    }
    .dash-ccf-track {
        height: 10px; border-radius: 99px; background: rgba(240, 213, 213, 0.85);
        overflow: hidden; margin-top: 8px;
    }
    .dash-ccf-fill { height: 100%; background: var(--risk-high); }

    @media (max-width: 960px) {
        .dash-snapshot { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 420px) {
        .dash-snapshot { grid-template-columns: 1fr; }
        .dash-snap { padding: 14px; }
    }

    .dash-area { margin-bottom: 44px; scroll-margin-top: 72px; }
    .dash-area-head { margin-bottom: 18px; }
    .dash-area-kicker {
        font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--muted); margin: 0 0 4px;
    }
    .dash-area-head h2 {
        font-family: 'DM Serif Display', serif;
        font-size: 1.45rem; color: var(--primary); margin: 0 0 4px;
    }
    .dash-area-head p { margin: 0; font-size: 13px; color: var(--muted); line-height: 1.45; }

    .dash-zone { margin-bottom: 36px; scroll-margin-top: 72px; }
    .dash-zone-head {
        display: flex; flex-wrap: wrap; align-items: baseline;
        justify-content: space-between; gap: 8px 16px;
        margin-bottom: 14px;
    }
    .dash-zone-head h2 {
        font-family: 'DM Serif Display', serif;
        font-size: 1.2rem; color: var(--primary); margin: 0;
    }
    .dash-zone-head p { margin: 0; font-size: 13px; color: var(--muted); }
    .dash-count {
        font-size: 13px; font-weight: 600; color: var(--muted);
        text-decoration: none;
    }
    a.dash-count:hover { color: var(--accent); }

    .dash-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 132px), 1fr));
        gap: 10px; margin-bottom: 18px;
    }
    .dash-kpi {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 12px; padding: 14px 16px;
    }
    .dash-kpi-label {
        font-size: 11px; font-weight: 600;
        color: var(--muted);
    }
    .dash-kpi-value {
        font-family: 'DM Serif Display', serif;
        font-size: clamp(1.35rem, 3.5vw, 1.75rem);
        color: var(--text); margin-top: 4px; line-height: 1.1;
    }
    .dash-kpi-value a { color: inherit; text-decoration: none; }
    .dash-kpi-value a:hover { color: var(--accent); }
    .dash-kpi-value--accent { color: var(--accent); }
    .dash-kpi-value--warn { color: var(--risk-mid); }

    .dash-grid-2 {
        display: grid; gap: 16px; margin-bottom: 18px;
    }
    @media (min-width: 900px) {
        .dash-grid-2 { grid-template-columns: 1.15fr 0.85fr; }
    }

    .dash-panel {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 14px; overflow: hidden;
    }
    .dash-panel-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; padding: 14px 16px 0; flex-wrap: wrap;
    }
    .dash-panel-head h3 {
        margin: 0; font-size: 14px; font-weight: 600; color: var(--text);
    }
    .dash-panel-body { padding: 12px 16px 16px; }
    .dash-panel-body--flush { padding: 0; }

    .dash-charts {
        display: grid; gap: 12px; margin-bottom: 18px;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
    }
    .dash-chart {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 14px; padding: 16px; min-height: 250px;
        display: flex; flex-direction: column;
    }
    .dash-chart h3 { margin: 0 0 12px; font-size: 13px; font-weight: 600; color: var(--text); }
    .dash-chart-wrap { position: relative; flex: 1; min-height: 180px; }

    .dash-risco-legend {
        display: flex; flex-wrap: wrap; gap: 10px 16px;
        font-size: 12px; color: var(--muted); margin-top: 4px;
    }
    .dash-risco-legend strong { color: var(--text); font-weight: 600; }

    .dash-empty {
        text-align: center; padding: 28px 16px;
        color: var(--muted); font-size: 13px; line-height: 1.45;
    }
    .dash-empty a { color: var(--accent); font-weight: 600; }

    .dash-eco-priority {
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
    }
    .dash-eco-priority--urgente { color: var(--risk-high); }
    .dash-eco-priority--alta { color: var(--accent); }
    .dash-eco-priority--moderada { color: var(--risk-mid); }
    .dash-eco-priority--baixa { color: var(--muted); }

    .dash-bars {
        display: grid; gap: 12px; margin-bottom: 14px;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
    }
    .dash-bar-block { padding: 14px 16px; }
    .dash-bar-block h4 { font-size: 13px; font-weight: 600; margin: 0 0 10px; color: var(--text); }
    .dash-bar-track {
        height: 10px; border-radius: 99px; background: rgba(240, 213, 213, 0.85);
        overflow: hidden;
    }
    .dash-bar-seg { height: 100%; }
    .dash-bar-seg--ok { background: var(--risk-low); }
    .dash-bar-seg--risk { background: var(--risk-high); }
    .dash-bar-meta { margin-top: 8px; font-size: 12px; color: var(--muted); }
    .dash-bar-meta strong { color: var(--text); }

    .dash-mini {
        display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px;
    }
    .dash-mini span {
        font-size: 12px; padding: 8px 12px; border-radius: 10px;
        background: #fff; border: 1px solid var(--border); color: var(--text);
    }
    .dash-mini strong { color: var(--primary); }

    .dash-model-hi {
        display: grid; gap: 10px; margin-bottom: 14px;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
    }
    .dash-model-hi article {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 12px; padding: 12px 14px;
    }
    .dash-model-hi-label {
        font-size: 11px; font-weight: 600;
        color: var(--muted); margin-bottom: 4px;
    }
    .dash-model-hi-name {
        font-family: 'DM Serif Display', serif; font-size: 1.05rem;
        color: var(--primary); line-height: 1.2;
    }
    .dash-model-hi-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .dash-model-hi-value { font-size: 16px; font-weight: 700; margin-top: 6px; }

    .dash-details {
        border: 1px solid var(--border); border-radius: 12px;
        background: var(--surface); overflow: hidden;
    }
    .dash-details > summary {
        cursor: pointer; list-style: none;
        padding: 14px 16px; font-size: 13px; font-weight: 600; color: var(--primary);
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        user-select: none;
    }
    .dash-details > summary::-webkit-details-marker { display: none; }
    .dash-details > summary::after {
        content: 'Mostrar'; font-size: 12px; font-weight: 600; color: var(--muted);
    }
    .dash-details[open] > summary::after { content: 'Ocultar'; }
    .dash-details-body { border-top: 1px solid var(--border); }

    .dash-model-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .dash-model-table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 700px; }
    .dash-model-table th, .dash-model-table td {
        padding: 9px 11px; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap;
    }
    .dash-model-table th {
        font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em;
        color: var(--muted); font-weight: 700; background: #faf7f7;
    }
    .dash-model-table td.num { font-variant-numeric: tabular-nums; text-align: right; }
    .dash-model-table tr.is-best { background: rgba(39, 174, 96, 0.06); }
    .dash-model-badge {
        display: inline-block; font-size: 9px; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; padding: 2px 6px; border-radius: 5px; margin-left: 5px;
        background: rgba(39, 174, 96, 0.15); color: #1e8449;
    }
    .dash-footnote { font-size: 12px; color: var(--muted); margin: 10px 2px 0; line-height: 1.4; }

    .dash-desc-grid {
        display: grid; gap: 14px;
        grid-template-columns: 1fr;
    }
    @media (min-width: 900px) {
        .dash-desc-grid { grid-template-columns: 1fr 1fr; }
    }
    .dash-desc-card {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 12px; padding: 12px;
    }
    .dash-desc-card h4 {
        margin: 0 0 10px; font-size: 13px; font-weight: 600; color: var(--text);
    }
    .dash-desc-card img {
        display: block; width: 100%; height: auto; border-radius: 10px;
        border: 1px solid var(--border); background: #fff;
    }

    .dash .data-table { font-size: 13px; }
    .dash .table-container { margin: 0; }

    .dash-desc-fig img,
    .dash-chart-frame img,
    .dash-chart img {
        width: 100%;
        height: auto;
        display: block;
    }

    @media (max-width: 640px) {
        .dash-hero-top { flex-direction: column; align-items: flex-start; }
        .dash-hero-actions { width: 100%; }
        .dash-hero-actions .dash-btn {
            flex: 1 1 calc(50% - 4px);
            justify-content: center;
        }
        .dash-hero-actions .dash-date {
            flex: 1 1 100%;
            text-align: center;
        }
        .dash-anchor a,
        .dash-anchor button { min-width: 0; flex: 1 1 100%; }
        .dash-panel { border-radius: 12px; }
        .dash-model-table { min-width: 520px; }
        .dash-chart { min-height: 200px; padding: 14px; }
    }

    @media (max-width: 420px) {
        .dash-hero-actions .dash-btn {
            flex: 1 1 100%;
        }
    }
</style>

<div class="dash">
    <header class="dash-hero">
        <div class="dash-hero-top">
            <div>
                <p class="dash-brand">@include('partials.brand-logo', ['variant' => 'dash'])</p>
                <h1>Perfil da população</h1>
                <p class="page-subtitle" style="margin: 6px 0 0;">Rastreio de cardiopatias congênitas em gestantes diabéticas.</p>
            </div>
            <div class="dash-hero-actions">
                <span class="dash-date">{{ $hoje }}</span>
                <a class="dash-btn" href="{{ route('gestantes.index') }}">Gestantes</a>
            </div>
        </div>
    </header>

    @include('partials.dashboard-geral')

</div>

@if (count($gcVariaveis) > 0)
    @push('scripts')
        @include('partials.chart-lib')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const cohort = @json($gcVariaveis);
                const p = window.GestRiskCharts.palette;
                GestRiskCharts.ready(function () {
                    cohort.forEach(function (g) {
                        const id = 'chart-cohort-' + g.slug;
                        const colors = g.tipo === 'doughnut'
                            ? [p.accent, p.ok, p.riskMid, p.riskHigh, '#bdc3c7', p.muted]
                            : g.valores.map(function () { return 'rgba(127, 12, 26, 0.72)'; });
                        if (g.tipo === 'doughnut') {
                            GestRiskCharts.doughnut(id, g.labels, g.valores, colors.slice(0, g.labels.length));
                        } else {
                            GestRiskCharts.bar(id, g.labels, g.valores, colors);
                        }
                    });
                });
            });
        </script>
    @endpush
@endif

@endsection
