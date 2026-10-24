@php
    use App\Services\DashboardAnaliseService;

    $fmtCcf = static function ($v) {
        if ($v === null || $v === '') {
            return '';
        }

        return number_format((float) $v * 100, 1, ',', '.').'%';
    };
    $temFila = $filaEcocardiograma->isNotEmpty();
    $temRecentes = $analisesRecentes->isNotEmpty();
    $temConsultas = $consultasRecentes->isNotEmpty();
@endphp

        <section class="dash-area" id="tabelas">
            <div class="dash-area-head">
                <p class="dash-area-kicker">Análise individual</p>
                <h2>Registro selecionado</h2>
                <p>Escolha uma gestante nas listas para ver o resultado, os indicadores e o atalho da ficha. Os dados são os que o painel já carrega.</p>
            </div>

            <div class="dash-individual-layout">
                <div>
                    <section class="dash-zone" id="fila-eco" style="margin-bottom: 16px;">
                        <div class="dash-zone-head">
                            <div>
                                <h2>Fila de ecocardiograma</h2>
                                <p>Gestantes com ecocardiograma recomendado, por prioridade.</p>
                            </div>
                            @can(\App\Models\User::ABILITY_ACOMPANHAMENTOS)
                            <a class="dash-count" href="{{ route('acompanhamentos.index') }}">
                                {{ $filaEcocardiograma->count() }} na fila · ver completa
                            </a>
                            @else
                            <span class="dash-count">{{ $filaEcocardiograma->count() }} na fila</span>
                            @endcan
                        </div>
                        <div class="dash-panel">
                            <div class="dash-panel-head">
                                <h3>Prioridade (até 20)</h3>
                            </div>
                            <div class="dash-panel-body dash-panel-body--flush">
                                <div class="table-container table-container--flush" style="border: 0; border-radius: 0; box-shadow: none;">
                                    @if (! $temFila)
                                        <p class="dash-empty">Nenhuma gestante com ecocardiograma recomendado.</p>
                                    @else
                                        <table class="data-table data-table--compact">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Gestante</th>
                                                    <th class="td-num">Score</th>
                                                    <th>Prioridade</th>
                                                    <th class="td-num">Cardiopatia congênita</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($filaEcocardiograma as $pos => $item)
                                                    @php
                                                        $gestanteItem = $item->gestante;
                                                        $ccfTxt = $fmtCcf($item->probabilidade_ccf);
                                                    @endphp
                                                    <tr class="dash-pick"
                                                        data-id="{{ $gestanteItem?->id }}"
                                                        data-nome="{{ $gestanteItem?->nome_exibicao }}"
                                                        data-risco="{{ DashboardAnaliseService::labelRisco($item->classificacao_risco) }}"
                                                        data-ccf="{{ $ccfTxt }}"
                                                        data-ccf-num="{{ $item->probabilidade_ccf !== null ? round((float) $item->probabilidade_ccf * 100, 1) : '' }}"
                                                        data-eco="{{ DashboardAnaliseService::labelPrioridadeEco($item->prioridade_ecocardiograma) }}"
                                                        data-score="{{ $item->score_prioridade !== null ? number_format($item->score_prioridade, 0) : '' }}"
                                                        data-quando=""
                                                        data-ig=""
                                                        data-chd=""
                                                        data-url="{{ $gestanteItem ? route('gestantes.show', $gestanteItem) : '' }}"
                                                        data-ml="{{ ($podeMl && $gestanteItem) ? route('cohort.gestantes.show', $gestanteItem->gestante_id) : '' }}">
                                                        <td class="td-num">{{ $pos + 1 }}</td>
                                                        <td>
                                                            @if ($gestanteItem)
                                                                {{ \Illuminate\Support\Str::limit($gestanteItem->nome_exibicao, 28) }}
                                                            @else Não informado @endif
                                                        </td>
                                                        <td class="td-num">{{ $item->score_prioridade !== null ? number_format($item->score_prioridade, 0) : 'Não informado' }}</td>
                                                        <td>
                                                            <span class="dash-eco-priority dash-eco-priority--{{ $item->prioridade_ecocardiograma ?? 'baixa' }}">
                                                                {{ DashboardAnaliseService::labelPrioridadeEco($item->prioridade_ecocardiograma) }}
                                                            </span>
                                                        </td>
                                                        <td class="td-num">{{ $ccfTxt !== '' ? $ccfTxt : 'Não informado' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="dash-zone" style="margin-bottom: 0;">
                        <div class="dash-panel">
                            <div class="dash-panel-head">
                                <h3>Análises recentes</h3>
                            </div>
                            <div class="dash-panel-body dash-panel-body--flush">
                                <div class="table-container table-container--flush" style="border: 0; border-radius: 0; box-shadow: none;">
                                    @if (! $temRecentes)
                                        <p class="dash-empty">Nenhuma análise registrada.</p>
                                    @else
                                        <table class="data-table data-table--compact">
                                            <thead>
                                                <tr>
                                                    <th>Quando</th>
                                                    <th>Gestante</th>
                                                    <th>Risco</th>
                                                    <th class="td-num">Cardiopatia congênita</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($analisesRecentes->take(10) as $a)
                                                    @php
                                                        $gestanteItem = $a->gestante;
                                                        $ccfTxt = $fmtCcf($a->probabilidade_ccf);
                                                    @endphp
                                                    <tr class="dash-pick"
                                                        data-id="{{ $gestanteItem?->id }}"
                                                        data-nome="{{ $gestanteItem?->nome_exibicao }}"
                                                        data-risco="{{ DashboardAnaliseService::labelRisco($a->classificacao_risco) }}"
                                                        data-ccf="{{ $ccfTxt }}"
                                                        data-ccf-num="{{ $a->probabilidade_ccf !== null ? round((float) $a->probabilidade_ccf * 100, 1) : '' }}"
                                                        data-eco="{{ $a->recomenda_ecocardiograma ? DashboardAnaliseService::labelPrioridadeEco($a->prioridade_ecocardiograma) : '' }}"
                                                        data-score="{{ $a->score_prioridade !== null ? number_format($a->score_prioridade, 0) : '' }}"
                                                        data-quando="{{ $a->created_at?->format('d/m H:i') ?? '' }}"
                                                        data-ig=""
                                                        data-chd=""
                                                        data-url="{{ $gestanteItem ? route('gestantes.show', $gestanteItem) : '' }}"
                                                        data-ml="{{ ($podeMl && $gestanteItem) ? route('cohort.gestantes.show', $gestanteItem->gestante_id) : '' }}">
                                                        <td>{{ $a->created_at?->format('d/m H:i') ?? 'Não informado' }}</td>
                                                        <td>
                                                            @if ($gestanteItem)
                                                                {{ \Illuminate\Support\Str::limit($gestanteItem->nome_exibicao, 24) }}
                                                            @else Não informado @endif
                                                        </td>
                                                        <td>{{ DashboardAnaliseService::labelRisco($a->classificacao_risco) }}</td>
                                                        <td class="td-num">{{ $ccfTxt !== '' ? $ccfTxt : 'Não informado' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="dash-panel dash-individual-detalhe" id="dash-individual-detalhe" aria-live="polite">
                    <div class="dash-panel-head">
                        <h3>Ficha resumida</h3>
                    </div>
                    <div class="dash-panel-body">
                        <div id="dash-individual-vazio" class="dash-empty" style="padding: 18px 8px;">
                            Selecione uma linha à esquerda para ver os indicadores daquela gestante.
                        </div>
                        <div id="dash-individual-conteudo" hidden>
                            <p class="dash-area-kicker" style="margin-bottom: 4px;">Gestante</p>
                            <h3 id="dash-ind-nome" style="margin: 0 0 4px; font-family: 'DM Serif Display', serif; font-size: 1.35rem; color: var(--primary);"></h3>
                            <p id="dash-ind-quando" style="margin: 0; font-size: 13px; color: var(--muted);"></p>

                            <div class="dash-individual-kpis">
                                <div class="dash-kpi" style="margin: 0;">
                                    <div class="dash-kpi-label">Risco</div>
                                    <div class="dash-kpi-value" id="dash-ind-risco" style="font-size: 1.25rem;">Não informado</div>
                                </div>
                                <div class="dash-kpi" style="margin: 0;">
                                    <div class="dash-kpi-label">Prob. cardiopatia</div>
                                    <div class="dash-kpi-value dash-kpi-value--accent" id="dash-ind-ccf" style="font-size: 1.25rem;">Não informado</div>
                                </div>
                                <div class="dash-kpi" style="margin: 0;">
                                    <div class="dash-kpi-label">Ecocardiograma</div>
                                    <div class="dash-kpi-value" id="dash-ind-eco" style="font-size: 1.15rem;">Não informado</div>
                                </div>
                                <div class="dash-kpi" style="margin: 0;">
                                    <div class="dash-kpi-label">Score</div>
                                    <div class="dash-kpi-value" id="dash-ind-score" style="font-size: 1.15rem;">Não informado</div>
                                </div>
                            </div>

                            <p class="dash-kpi-label">Probabilidade de cardiopatia congênita</p>
                            <div class="dash-ccf-track" aria-hidden="true">
                                <div class="dash-ccf-fill" id="dash-ind-ccf-bar" style="width: 0%"></div>
                            </div>
                            <p id="dash-ind-extra" class="dash-footnote"></p>

                            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top: 16px;">
                                <a id="dash-ind-url" class="dash-btn dash-btn--primary" href="#">Abrir cadastro</a>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="dash-zone">
            <div class="dash-zone-head">
                <div>
                    <h2>Últimas consultas</h2>
                    <p>Registros clínicos recentes. Clique para selecionar a gestante.</p>
                </div>
                @if ($totalConsultas > 0)
                    <a class="dash-count" href="{{ route('gestantes.index') }}">Ver gestantes</a>
                @endif
            </div>
            <div class="dash-panel" style="margin-bottom: 0;">
                <div class="dash-panel-body dash-panel-body--flush">
                    <div class="table-container table-container--flush" style="border: 0; border-radius: 0; box-shadow: none;">
                        @if (! $temConsultas)
                            <p class="dash-empty">Ainda não há consultas.</p>
                        @else
                            <table class="data-table data-table--compact">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Gestante</th>
                                        <th class="td-num">IG</th>
                                        <th>Cardiopatia congênita</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($consultasRecentes as $c)
                                        @php $gestanteItem = $c->gestante; @endphp
                                        <tr class="dash-pick"
                                            data-id="{{ $gestanteItem?->id }}"
                                            data-nome="{{ $gestanteItem?->nome_exibicao }}"
                                            data-risco=""
                                            data-ccf=""
                                            data-ccf-num=""
                                            data-eco=""
                                            data-score=""
                                            data-quando="{{ $c->data_consulta?->format('d/m/Y') ?? '' }}"
                                            data-ig="{{ $c->idade_gestacional ?? '' }}"
                                            data-chd="{{ $c->chd_confirmada ? 'Sim' : 'Não' }}"
                                            data-url="{{ $gestanteItem ? route('gestantes.show', $gestanteItem) : '' }}"
                                            data-ml="{{ ($podeMl && $gestanteItem) ? route('cohort.gestantes.show', $gestanteItem->gestante_id) : '' }}">
                                            <td>{{ $c->data_consulta?->format('d/m/Y') ?? 'Não informado' }}</td>
                                            <td>
                                                @if ($gestanteItem)
                                                    <strong>{{ \Illuminate\Support\Str::limit($gestanteItem->nome_exibicao, 40) }}</strong>
                                                @else
                                                    Não informado
                                                @endif
                                            </td>
                                            <td class="td-num">{{ $c->idade_gestacional ?? 'Não informado' }}</td>
                                            <td>
                                                @if ($c->chd_confirmada)
                                                    <span class="consulta-pill consulta-pill--alert" style="margin:0;">Sim</span>
                                                @else
                                                    <span class="consulta-pill consulta-pill--ok" style="margin:0;">Não</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </section>
