@php
    use App\Services\DashboardAnaliseService;
@endphp

        <nav class="dash-subnav" aria-label="Seções da análise da população">
            <a href="#indicadores">Visão geral</a>
            <a href="#socio">Sociodemográficas</a>
            <a href="#antropo">Antropométricas</a>
            <a href="#clinicas">Clínicas</a>
            <a href="#obstetricas">Obstétricas</a>
            <a href="#graficos">Variáveis</a>
            <a href="#analises">Modelos</a>
        </nav>

    <section class="dash-area" id="indicadores">
        <div class="dash-area-head">
            <p class="dash-area-kicker">Visão geral</p>
            <h2>Panorama da base</h2>
            <p>Totais e cobertura do cadastro, sem classificação individual.</p>
        </div>

        <div class="dash-snapshot" aria-label="Indicadores principais">
            <a class="dash-snap" href="#indicadores">
                <div class="dash-snap-label">Gestantes</div>
                <div class="dash-snap-value">{{ number_format($totalGestantes, 0, ',', '.') }}</div>
                <div class="dash-snap-hint">Cadastro local</div>
            </a>
            <a class="dash-snap" href="#indicadores">
                <div class="dash-snap-label">Consultas</div>
                <div class="dash-snap-value">{{ number_format($totalConsultas, 0, ',', '.') }}</div>
                <div class="dash-snap-hint">{{ $gestantesComConsulta }} com consulta ({{ $taxaGestantesComConsulta }}%)</div>
            </a>
            <a class="dash-snap" href="#graficos">
                <div class="dash-snap-label">Cardiopatia congênita nas consultas</div>
                <div class="dash-snap-value dash-snap-value--accent">{{ $totalConsultas > 0 ? number_format($pctChdNasConsultas, 1, ',', '.').'%' : 'Não informado' }}</div>
                <div class="dash-snap-hint">{{ number_format($chdConfirmadas, 0, ',', '.') }} confirmadas</div>
            </a>
            <a class="dash-snap" href="#graficos">
                <div class="dash-snap-label">Conjunto de treino</div>
                <div class="dash-snap-value">{{ isset($datasetMlTotal) ? number_format((int) $datasetMlTotal, 0, ',', '.') : 'Não informado' }}</div>
                <div class="dash-snap-hint">Base do modelo</div>
            </a>
        </div>
    </section>

    <section class="dash-zone" id="cobertura">
        <div class="dash-zone-head">
            <div>
                <h2>Cobertura</h2>
                <p>Consultas registradas e comorbidades observadas na base.</p>
            </div>
        </div>

        @if ($totalConsultas > 0)
            <div class="dash-bars">
                <div class="dash-panel dash-bar-block">
                    <h4>Cobertura de consultas</h4>
                    <div class="dash-bar-track">
                        <div class="dash-bar-seg dash-bar-seg--ok" style="width: {{ $taxaGestantesComConsulta }}%"></div>
                    </div>
                    <div class="dash-bar-meta">
                        <strong>{{ $gestantesComConsulta }}</strong> de <strong>{{ $totalGestantes }}</strong>
                        gestantes ({{ $taxaGestantesComConsulta }}%)
                    </div>
                </div>
                <div class="dash-panel dash-bar-block">
                    <h4>Cardiopatia congênita nas consultas</h4>
                    @php $wChd = round(100 * $chdConfirmadas / max($totalConsultas, 1), 2); @endphp
                    <div class="dash-bar-track">
                        <div class="dash-bar-seg dash-bar-seg--risk" style="width: {{ $wChd }}%"></div>
                    </div>
                    <div class="dash-bar-meta">
                        Sim: <strong>{{ $chdConfirmadas }}</strong> ({{ $pctChdNasConsultas }}%)
                        · Não: <strong>{{ $chdNegativas }}</strong>
                    </div>
                </div>
            </div>
            <div class="dash-mini">
                <span>Idade gestacional média: <strong>{{ $mediaIdadeGestacional !== null ? $mediaIdadeGestacional.' sem' : 'Não informado' }}</strong></span>
                <span>Diabetes gestacional: <strong>{{ $consultasComDiabetes }}</strong></span>
                <span>Hipertensão: <strong>{{ $consultasComHipertensao }}</strong></span>
            </div>
        @else
            <div class="dash-panel">
                <p class="dash-empty">Ainda não há consultas cadastradas.</p>
            </div>
        @endif
    </section>

    <section class="dash-area" id="graficos">
        <div class="dash-area-head">
            <p class="dash-area-kicker">Análise por variáveis</p>
            <h2>Características da população</h2>
            <p>
                Leitura descritiva e exploratória da base, agrupada por tipo de variável.
                @if (($ad['fonte'] ?? '') === 'cadastro local')
                    Cadastro local. Dados da análise de risco.
                @endif
            </p>
        </div>

        @if (! $descOk)
            <div class="dash-panel">
                <p class="dash-empty">
                    Cadastre consultas para ver a distribuição das variáveis.
                </p>
            </div>
        @else
            @foreach ($gruposVariaveis as $grupo)
                @php
                    $graficosGrupo = $grupo['graficos'] ?? [];
                    $figurasGrupo = $grupo['figuras'] ?? [];
                @endphp
                @if ($graficosGrupo !== [] || $figurasGrupo !== [])
                    <section class="dash-zone" id="{{ $grupo['id'] }}">
                        <div class="dash-zone-head">
                            <div>
                                <h2>{{ $grupo['titulo'] }}</h2>
                                @if (! empty($grupo['texto']))
                                    <p>{{ $grupo['texto'] }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($graficosGrupo !== [])
                            <div class="dash-charts">
                                @foreach ($graficosGrupo as $grafico)
                                    <div class="dash-chart">
                                        <h3>{{ $grafico['titulo'] }}</h3>
                                        <div class="dash-chart-wrap"><canvas id="chart-cohort-{{ $grafico['slug'] }}"></canvas></div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if ($figurasGrupo !== [])
                            <div class="dash-desc-grid" style="{{ $graficosGrupo !== [] ? 'margin-top: 14px;' : '' }}">
                                @foreach ($figurasGrupo as $figura)
                                    <article class="dash-desc-card">
                                        <h4>{{ $figura['titulo'] ?? DashboardAnaliseService::tituloGraficoDescritivo((string) ($figura['slug'] ?? '')) }}</h4>
                                        <img
                                            src="{{ route('dashboard.graficos.descritivo', $figura['slug']) }}"
                                            alt="{{ $figura['titulo'] ?? $figura['slug'] }}"
                                            loading="lazy"
                                        >
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif
            @endforeach

            @if (($gdOutros ?? []) !== [])
                <section class="dash-zone" id="variaveis-extras">
                    <div class="dash-zone-head">
                        <div>
                            <h2>Distribuição das variáveis</h2>
                            <p>Demais figuras descritivas já geradas para a base.</p>
                        </div>
                    </div>
                    <div class="dash-desc-grid">
                        @foreach ($gdOutros as $figura)
                            <article class="dash-desc-card">
                                <h4>{{ $figura['titulo'] ?? DashboardAnaliseService::tituloGraficoDescritivo((string) ($figura['slug'] ?? '')) }}</h4>
                                <img
                                    src="{{ route('dashboard.graficos.descritivo', $figura['slug']) }}"
                                    alt="{{ $figura['titulo'] ?? $figura['slug'] }}"
                                    loading="lazy"
                                >
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </section>

    <section class="dash-area" id="analises">
        <div class="dash-area-head">
            <p class="dash-area-kicker">Consulta complementar</p>
            <h2>Catálogo de experimentos</h2>
            <p>Métricas de modelos, em segundo plano depois da leitura da população.</p>
        </div>

        <details class="dash-details">
            <summary>Ver catálogo de modelos</summary>
            <div class="dash-details-body" style="padding: 14px;">
    <section class="dash-zone" id="modelos" style="margin: 0;">
        <div class="dash-zone-head">
            <div>
                <h2>Catálogo</h2>
                <p>
                    @if (! empty($mm['experimento']))
                        {{ $mm['experimento'] }}
                    @else
                        Métricas dos experimentos disponíveis.
                    @endif
                </p>
            </div>
        </div>

        @if ($mmProducao !== [])
            <div class="dash-panel" style="margin-bottom: 16px;">
                <div class="dash-panel-head">
                    <h3>Modelo em produção</h3>
                    <span class="dash-count">
                        {{ ($mmProducao['carregado'] ?? false) ? 'publicado' : 'artefato ausente' }}
                    </span>
                </div>
                <div class="dash-panel-body">
                    <p>
                        {{ $mmProducao['nome_exibicao'] ?? $mmProducao['modelo'] ?? 'Não informado' }}
                        @if (! empty($mmProducao['balanceamento']))
                            · {{ $mmProducao['balanceamento'] }}
                        @endif
                    </p>
                    @if (($mmProducao['modo_inferencia'] ?? '') === 'heuristica')
                        <p class="dash-footnote" style="margin-top: 8px;">
                            {{ $mmProducao['mensagem'] ?? 'A predição atual usa regras clínicas determinísticas (heurística), não um modelo do catálogo.' }}
                        </p>
                    @endif
                </div>
            </div>
        @endif

        @if (! $mmOk)
            <div class="dash-panel" style="margin-bottom: 16px;">
                <p class="dash-empty">
                    {{ $mm['mensagem'] ?? 'Catálogo de modelos indisponível no momento.' }}
                </p>
            </div>
        @else
            @if ($mmDestaques !== [])
                <div class="dash-model-hi">
                    @foreach ($destaqueLabels as $key => $label)
                        @php $d = $mmDestaques[$key] ?? null; @endphp
                        @if (is_array($d))
                            <article>
                                <div class="dash-model-hi-label">{{ $label }}</div>
                                <div class="dash-model-hi-name">{{ $d['nome_exibicao'] ?? $d['modelo'] ?? 'Não informado' }}</div>
                                <div class="dash-model-hi-meta">{{ $d['balanceamento'] ?? 'Não informado' }}</div>
                                <div class="dash-model-hi-value">{{ $fmtPct($d['valor'] ?? null) }}</div>
                            </article>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="dash-panel" style="margin-bottom: 16px;">
                <div class="dash-panel-head">
                    <h3>Métricas</h3>
                    <span class="dash-count">{{ (int) ($mm['total_modelos'] ?? count($mm['modelos'])) }} modelos</span>
                </div>
                <div class="dash-panel-body dash-panel-body--flush">
                    <div class="dash-model-table-wrap">
                        <table class="dash-model-table">
                            <thead>
                                <tr>
                                    <th>Modelo</th>
                                    <th>Balanceamento</th>
                                    <th class="num">F1 CV</th>
                                    <th class="num">F1 teste</th>
                                    <th class="num">ROC-AUC</th>
                                    <th class="num">Sens.</th>
                                    <th class="num">Espec.</th>
                                    <th class="num">Acurácia</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mm['modelos'] as $m)
                                    @php
                                        $isBest = $mmMelhorF1 && ($m['modelo'] ?? null) === $mmMelhorF1;
                                        $treino = is_array($m['treino_cv'] ?? null) ? $m['treino_cv'] : [];
                                        $teste = is_array($m['teste'] ?? null) ? $m['teste'] : [];
                                    @endphp
                                    <tr @class(['is-best' => $isBest])>
                                        <td>
                                            {{ $m['nome_exibicao'] ?? $m['modelo'] ?? 'Não informado' }}
                                            @if ($isBest)<span class="dash-model-badge">melhor F1 CV</span>@endif
                                        </td>
                                        <td>{{ $m['balanceamento'] ?? 'Não informado' }}</td>
                                        <td class="num">{{ $fmtPct($treino['f1'] ?? null) }}</td>
                                        <td class="num">{{ $fmtPct($teste['f1'] ?? null) }}</td>
                                        <td class="num">{{ $fmtPct($teste['roc_auc'] ?? null) }}</td>
                                        <td class="num">{{ $fmtPct($teste['sensibilidade'] ?? null) }}</td>
                                        <td class="num">{{ $fmtPct($teste['especificidade'] ?? null) }}</td>
                                        <td class="num">{{ $fmtPct($teste['acuracia'] ?? null) }}</td>
                                        <td>{{ $m['status'] ?? 'Não informado' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (! empty($mm['legendas']))
                        <details class="dash-details" style="border: 0; border-radius: 0; border-top: 1px solid var(--border);">
                            <summary>Notas das métricas</summary>
                            <div class="dash-details-body" style="padding: 0 14px 14px;">
                                @foreach ($mm['legendas'] as $legenda)
                                    @if (is_string($legenda) && $legenda !== '')
                                        <p class="dash-footnote">{{ $legenda }}</p>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            </div>
        @endif
    </section>
            </div>
        </details>
    </section>
