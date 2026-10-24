@php
    /** @var \App\Models\Gestante $gestante */
    /** @var \App\Models\Consulta|null $consultaAtual */
    /** @var \Illuminate\Support\Collection $historicoConsultas */
    $consultaAtual = $consultaAtual ?? $gestante->consultas->first();
    $historicoConsultas = $historicoConsultas ?? collect();
    $examesDocumentos = $gestante->documentos ?? collect();
    $podeConsultas = $podeConsultas ?? false;
    $podeAnalises = $podeAnalises ?? false;
    $podeVerAgenda = $podeVerAgenda ?? false;
    $podeAcompanhamentos = $podeAcompanhamentos ?? false;
    $podeDirecionarAtendimento = $podeDirecionarAtendimento ?? false;
    $podeGerenciarGestantes = $podeGerenciarGestantes ?? false;
    $atendimentoPersonalizadoLiberado = $atendimentoPersonalizadoLiberado ?? false;
    $encaminhadaParaFilaEco = $encaminhadaParaFilaEco ?? false;
    $temGraficosIa = $temGraficosIa ?? false;

    $idadeNaConsulta = function ($consulta) use ($gestante) {
        if ($gestante->data_nascimento && $consulta->data_consulta) {
            return (int) round(abs(
                \Carbon\Carbon::parse($gestante->data_nascimento)
                    ->diffInYears(\Carbon\Carbon::parse($consulta->data_consulta))
            ));
        }

        return $consulta->idade_materna !== null ? (int) $consulta->idade_materna : null;
    };

    $positivosClinicos = [];
    if ($consultaAtual) {
        $mapa = [
            'historico_natimorto' => 'Histórico de natimorto',
            'historico_filho_anterior_chd' => 'Filho anterior com cardiopatia congênita',
            'historico_familiar_chd' => 'Histórico familiar de cardiopatia congênita',
            'diabetes_pre_gestacional' => 'Diabetes pré-gestacional',
            'diabetes_gestacional' => 'Diabetes gestacional',
            'hipertensao_cronica' => 'Hipertensão crônica',
            'hipertensao' => 'Hipertensão (atual)',
            'hipertensao_pre_eclampsia' => 'Pré-eclâmpsia',
            'obesidade_pre_gestacional' => 'Obesidade pré-gestacional',
            'lupus_eritomatoso_sistemico' => 'Lúpus',
            'fenilcetonuria' => 'Fenilcetonúria',
            'doencas_tireoidianas' => 'Doenças tireoidianas',
            'rubeola' => 'Rubéola',
            'citomegalovirus' => 'Citomegalovírus',
            'toxoplasmose' => 'Toxoplasmose',
            'sifilis' => 'Sífilis',
            'uso_isotretinoina' => 'Isotretinoína',
            'uso_acido_valproico' => 'Ácido valproico',
            'uso_litio' => 'Lítio',
            'uso_medicamentos' => 'Outros medicamentos',
            'tabagismo' => 'Tabagismo',
            'alcoolismo' => 'Álcool',
            'drogas_ilicitas' => 'Drogas ilícitas',
            'exposicao_ocupacional' => 'Exposição ocupacional',
        ];
        foreach ($mapa as $campo => $label) {
            if ($consultaAtual->{$campo}) {
                $positivosClinicos[] = $label;
            }
        }
    }

    $observacoes = [];
    if ($podeAnalises && $gestante->ultimaInterpretacao?->resumo_medico) {
        $observacoes[] = $gestante->ultimaInterpretacao->resumo_medico;
    }
    if ($podeAcompanhamentos) {
        foreach ($gestante->acompanhamentos ?? collect() as $acomp) {
            $nota = trim((string) $acomp->observacao);
            if ($nota !== '') {
                $observacoes[] = $acomp->titulo ? $acomp->titulo.': '.$nota : $nota;
            }
        }
    }
@endphp

<nav class="gestante-area-nav" aria-label="Seções da consulta">
    <a href="#ficha-dados">Dados clínicos</a>
    <a href="#ficha-consulta-atual">Consulta atual</a>
    <a href="#ficha-historico">Histórico</a>
    <a href="#ficha-exames">Exames</a>
    @if ($podeAnalises)
        <a href="#ficha-analises">Análises</a>
    @endif
    <a href="#ficha-observacoes">Observações</a>
    <a href="#ficha-acoes">Ações</a>
</nav>

<section class="gestante-area" id="ficha-dados">
    <p class="gestante-area-kicker">Dados clínicos da gestante</p>
    <div class="main-card">
        <h2 class="card-title" style="font-size: 20px; margin: 0 0 16px;">Identificação e resumo clínico</h2>
        <div class="form-field-grid form-field-grid--2">
            <div class="form-field" style="margin-bottom: 0;">
                <span class="form-label">Nome</span>
                <p class="consulta-valor">{{ $gestante->nome_exibicao }}</p>
            </div>
            <div class="form-field" style="margin-bottom: 0;">
                <span class="form-label">Cadastro</span>
                <p class="consulta-valor">#{{ $gestante->id }}</p>
            </div>
            <div class="form-field" style="margin-bottom: 0;">
                <span class="form-label">Nascimento</span>
                <p class="consulta-valor">{{ $gestante->data_nascimento ? \Carbon\Carbon::parse($gestante->data_nascimento)->format('d/m/Y') : 'Não informada' }}</p>
            </div>
            <div class="form-field" style="margin-bottom: 0;">
                <span class="form-label">CPF</span>
                <p class="consulta-valor">{{ $gestante->cpf ? $gestante->cpf_formatado : 'Não informado' }}</p>
            </div>
            <div class="form-field" style="margin-bottom: 0;">
                <span class="form-label">Telefone</span>
                <p class="consulta-valor">{{ $gestante->telefone ? $gestante->telefone_formatado : 'Não informado' }}</p>
            </div>
            <div class="form-field" style="margin-bottom: 0;">
                <span class="form-label">E-mail</span>
                <p class="consulta-valor">{{ $gestante->email ?: 'Não informado' }}</p>
            </div>
            <div class="form-field" style="margin-bottom: 0;">
                <span class="form-label">Consultas registradas</span>
                <p class="consulta-valor">{{ $gestante->consultas->count() }}</p>
            </div>
            @if ($consultaAtual)
                <div class="form-field" style="margin-bottom: 0;">
                    <span class="form-label">Idade gestacional (atual)</span>
                    <p class="consulta-valor">{{ $consultaAtual->idade_gestacional ? $consultaAtual->idade_gestacional.' sem' : 'Não informado' }}</p>
                </div>
            @endif
        </div>
        @if ($positivosClinicos !== [])
            <div style="margin-top: 18px;">
                <span class="form-label">Antecedentes e comorbidades ativos</span>
                <p class="consulta-valor" style="font-weight: 500; line-height: 1.5;">{{ implode(' · ', $positivosClinicos) }}</p>
            </div>
        @endif
        @if ($podeAnalises && $gestante->ultimaAnalise && $gestante->ultimaAnalise->status === 'concluida')
            @php $ua = $gestante->ultimaAnalise; @endphp
            <p style="margin: 16px 0 0; font-size: 14px; color: var(--muted);">
                Último risco:
                <strong style="color: var(--text);">{{ \App\Services\DashboardAnaliseService::labelRisco($ua->classificacao_risco) }}</strong>
                @if ($ua->probabilidade_ccf !== null)
                    · {{ number_format($ua->probabilidade_ccf * 100, 1) }}% de cardiopatia congênita
                @endif
                @if ($ua->recomenda_ecocardiograma)
                    · Eco {{ \App\Services\DashboardAnaliseService::labelPrioridadeEco($ua->prioridade_ecocardiograma) }}
                @endif
            </p>
        @endif
    </div>
</section>

<section class="gestante-area" id="ficha-consulta-atual">
    <p class="gestante-area-kicker">Consulta atual</p>
    @if ($consultaAtual)
        @php $idade = $idadeNaConsulta($consultaAtual); @endphp
        <div class="main-card">
            <div class="consulta-resumo-head">
                <div>
                    <h2 class="card-title" style="font-size: 22px; margin: 0;">Consulta nº {{ $consultaAtual->consulta_numero }}</h2>
                    <p style="color: var(--muted); font-size: 14px; margin-top: 10px; line-height: 1.5;">
                        {{ $consultaAtual->data_consulta ? $consultaAtual->data_consulta->format('d/m/Y') : 'Não informado' }}
                        @if ($consultaAtual->idade_gestacional)
                            · {{ $consultaAtual->idade_gestacional }} sem
                        @endif
                    </p>
                </div>
                <span class="consulta-pill {{ $consultaAtual->chd_confirmada ? 'consulta-pill--alert' : 'consulta-pill--ok' }}">
                    Cardiopatia congênita {{ $consultaAtual->chd_confirmada ? 'confirmada' : 'não confirmada' }}
                </span>
            </div>

            <div class="form-page-grid form-page-grid--2" style="margin-top: 16px;">
                <div class="form-section">
                    <h3 class="form-section-title">Dados da consulta</h3>
                    <div class="form-field-grid form-field-grid--2">
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Idade na consulta</span>
                            <p class="consulta-valor">{{ $idade !== null ? $idade.' anos' : 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Idade gestacional</span>
                            <p class="consulta-valor">{{ $consultaAtual->idade_gestacional ? $consultaAtual->idade_gestacional.' sem' : 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Tipo de gestação</span>
                            <p class="consulta-valor">{{ $consultaAtual->tipo_gestacao ?: 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Etnia</span>
                            <p class="consulta-valor">{{ $consultaAtual->etnia ?: 'Não informado' }}</p>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="form-section-title">Sinais vitais</h3>
                    <div class="form-field-grid form-field-grid--2">
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Pressão sistólica</span>
                            <p class="consulta-valor">{{ $consultaAtual->pressao_sistolica ?? 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">BPM materno</span>
                            <p class="consulta-valor">{{ $consultaAtual->bpm_materno ?? 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Saturação</span>
                            <p class="consulta-valor">{{ $consultaAtual->saturacao !== null && $consultaAtual->saturacao !== '' ? $consultaAtual->saturacao.'%' : 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Temperatura</span>
                            <p class="consulta-valor">{{ $consultaAtual->temperatura_corporal !== null && $consultaAtual->temperatura_corporal !== '' ? $consultaAtual->temperatura_corporal.' °C' : 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Altura</span>
                            <p class="consulta-valor">{{ $consultaAtual->altura ? $consultaAtual->altura.' cm' : 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Peso</span>
                            <p class="consulta-valor">{{ $consultaAtual->peso ? $consultaAtual->peso.' kg' : 'Não informado' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($podeConsultas)
        <div class="main-card">
            <h2 class="card-title" style="font-size: 20px; margin: 0 0 8px;">Registrar consulta</h2>
            <p class="page-subtitle" style="margin: 0 0 20px;">Esta tela é o fluxo da consulta. Preencha e salve para iniciar o acompanhamento clínico.</p>
            @if ($errors->any())
                <div class="form-alert-error">
                    <strong>Verifique os campos abaixo:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('consultas.store', ['id' => $gestante->id]) }}" method="POST">
                @csrf
                @include('consultas.partials.fields', ['consulta' => null])
                <div class="form-actions" style="margin-top: 22px;">
                    <div class="form-actions-end">
                        <button type="submit" class="btn-primary-custom">Salvar consulta</button>
                    </div>
                </div>
            </form>
        </div>
    @else
        <div class="main-card" style="text-align: center; color: var(--muted); padding: 40px 24px;">
            Nenhuma consulta registrada.
        </div>
    @endif
</section>

<section class="gestante-area" id="ficha-historico">
    <p class="gestante-area-kicker">Histórico de consultas</p>
    <div class="main-card">
        <h2 class="card-title" style="font-size: 20px; margin: 0 0 16px;">Consultas anteriores</h2>
        @if ($historicoConsultas->isEmpty())
            <p class="dash-empty" style="text-align:left; padding: 8px 0; margin: 0;">
                {{ $consultaAtual ? 'Não há consultas anteriores além da atual.' : 'Nenhuma consulta no histórico.' }}
            </p>
        @else
            <p class="table-scroll-hint">↔ Deslize horizontalmente para ver todas as colunas.</p>
            <div class="table-container table-container--flush">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="td-nowrap">Nº</th>
                            <th class="td-date">Data</th>
                            <th class="td-nowrap">IG</th>
                            <th>Cardiopatia congênita</th>
                            @if ($podeConsultas)
                                <th class="td-actions">Ação</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historicoConsultas as $consulta)
                            <tr>
                                <td class="td-nowrap">{{ $consulta->consulta_numero }}</td>
                                <td class="td-date">{{ $consulta->data_consulta ? $consulta->data_consulta->format('d/m/Y') : 'Não informado' }}</td>
                                <td class="td-nowrap">{{ $consulta->idade_gestacional ? $consulta->idade_gestacional.' sem' : 'Não informado' }}</td>
                                <td>{{ $consulta->chd_confirmada ? 'Confirmada' : 'Não confirmada' }}</td>
                                @if ($podeConsultas)
                                    <td class="td-actions">
                                        <a href="{{ route('consultas.edit', $consulta->id) }}" class="btn-table btn-table--secondary">Editar</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>

<section class="gestante-area" id="ficha-exames">
    <p class="gestante-area-kicker">Exames</p>
    <div class="main-card">
        <h2 class="card-title" style="font-size: 20px; margin: 0 0 16px;">Exames da consulta e documentos</h2>
        @if ($consultaAtual)
            <div class="form-page-grid form-page-grid--2" style="margin-bottom: 18px;">
                <div class="form-section">
                    <h3 class="form-section-title">Laboratório</h3>
                    <div class="form-field-grid form-field-grid--2">
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Glicemia jejum</span>
                            <p class="consulta-valor">{{ $consultaAtual->glicemia_jejum ?? 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Glicemia pós-prandial</span>
                            <p class="consulta-valor">{{ $consultaAtual->glicemia_pos_prandial ?? 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">HbA1c</span>
                            <p class="consulta-valor">{{ $consultaAtual->hba1c !== null && $consultaAtual->hba1c !== '' ? $consultaAtual->hba1c.'%' : 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">USG precoce</span>
                            <p class="consulta-valor">{{ $consultaAtual->usg_precoce_confirmada ? 'Confirmada' : 'Não informado' }}</p>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="form-section-title">Dados fetais</h3>
                    <div class="form-field-grid form-field-grid--2">
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">FC fetal</span>
                            <p class="consulta-valor">{{ $consultaAtual->frequencia_cardiaca_fetal ?? 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">TN (mm)</span>
                            <p class="consulta-valor">{{ $consultaAtual->translucencia_nucal_mm ?? 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Circ. cefálica</span>
                            <p class="consulta-valor">{{ $consultaAtual->circunferencia_cefalica_fetal_mm ?? 'Não informado' }}</p>
                        </div>
                        <div class="form-field" style="margin-bottom: 0;">
                            <span class="form-label">Quatro câmaras</span>
                            <p class="consulta-valor">{{ $consultaAtual->quatro_camaras ?: 'Não informado' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        @if ($examesDocumentos->isEmpty())
            <p class="dash-empty" style="text-align:left; padding: 8px 0; margin: 0;">Nenhum exame documental cadastrado.</p>
        @else
            <div class="table-container table-container--flush">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Exame</th>
                            <th class="td-date">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($examesDocumentos as $doc)
                            <tr>
                                <td>{{ $doc->titulo ?: 'Exame' }}</td>
                                <td class="td-date">{{ $doc->data_documento ? $doc->data_documento->format('d/m/Y') : 'Não informado' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>

@if ($podeAnalises)
<section class="gestante-area" id="ficha-analises">
    <p class="gestante-area-kicker">Análises / risco</p>
    @include('partials.interpretacao-clinica', [
        'interpretacao' => $gestante->ultimaInterpretacao,
    ])
    @include('partials.analise-historico-timeline', [
        'analises' => $gestante->analisesHistorico,
        'gestante' => $gestante,
    ])
    @if ($temGraficosIa)
        @php $resultado_ia = session('resultado_analise_ia'); @endphp
        <div class="main-card">
            <h2 class="card-title" style="font-size: 20px; margin-bottom: 16px;">Gráficos da análise</h2>
            <div class="form-page-grid form-page-grid--3">
                @foreach ($resultado_ia['imagens'] as $titulo => $imagem_base64)
                    <div class="form-section" style="padding: 18px;">
                        <h3 class="form-section-title" style="font-size: 16px; margin-bottom: 12px;">{{ str_replace('_', ' ', $titulo) }}</h3>
                        <img src="{{ $imagem_base64 }}" alt="Gráfico de {{ $titulo }}" style="width: 100%; height: auto; border-radius: 12px;">
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endif

<section class="gestante-area" id="ficha-observacoes">
    <p class="gestante-area-kicker">Observações</p>
    <div class="main-card">
        <h2 class="card-title" style="font-size: 20px; margin: 0 0 16px;">Notas clínicas</h2>
        @if ($observacoes === [])
            <p class="dash-empty" style="text-align:left; padding: 8px 0; margin: 0;">Nenhuma observação clínica registrada.</p>
        @else
            <div style="display: grid; gap: 12px;">
                @foreach ($observacoes as $nota)
                    <p style="margin: 0; font-size: 14px; line-height: 1.55; white-space: pre-wrap;">{{ $nota }}</p>
                @endforeach
            </div>
        @endif
    </div>
</section>

<section class="gestante-area" id="ficha-acoes">
    <p class="gestante-area-kicker">Ações da consulta</p>
    <div class="main-card">
        <h2 class="card-title" style="font-size: 20px; margin: 0 0 16px;">Ações relacionadas à consulta</h2>
        <p class="consulta-acoes-status">
            Status:
            <strong>{{ $gestante->labelFluxoAtendimento() }}</strong>
        </p>
        @if ($podeDirecionarAtendimento)
            <div class="consulta-check-list">
                @include('partials.consulta-acao-check', [
                    'done' => $atendimentoPersonalizadoLiberado,
                    'enabled' => (bool) $consultaAtual,
                    'action' => route('gestantes.atendimento-personalizado', $gestante),
                    'title' => 'Aceitar atendimento personalizado',
                    'hint' => 'Permitir acompanhamento personalizado desta gestante.',
                    'hintDone' => 'Atendimento personalizado autorizado.',
                ])
                @include('partials.consulta-acao-check', [
                    'done' => $encaminhadaParaFilaEco,
                    'enabled' => (bool) $consultaAtual,
                    'action' => route('gestantes.encaminhar-ecocardiograma', $gestante),
                    'title' => 'Encaminhar para fila do ecocardiograma',
                    'hint' => 'Adicionar a gestante à fila de acompanhamento do exame.',
                    'hintDone' => 'Gestante encaminhada para a fila do ecocardiograma.',
                ])
            </div>
            @if (! $consultaAtual)
                <p style="margin: 4px 0 0; font-size: 13px; color: var(--muted);">Registre a consulta para liberar o aceite e o encaminhamento.</p>
            @endif
        @endif

        @if (($podeConsultas && $consultaAtual) || $podeGerenciarGestantes)
            <div class="consulta-outras-acoes">
                <h3>Outras ações</h3>
                <div class="consulta-outras-acoes-list">
                    @if ($podeConsultas && $consultaAtual)
                        <a href="{{ route('consultas.edit', $consultaAtual->id) }}" class="consulta-acao-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                            Editar consulta atual
                        </a>
                        <a href="{{ route('consultas.create', ['id' => $gestante->id]) }}" class="consulta-acao-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4"/></svg>
                            Registrar próxima consulta
                        </a>
                    @endif
                    @if ($podeGerenciarGestantes)
                        <a href="{{ route('gestantes.edit', $gestante) }}" class="consulta-acao-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Editar cadastro
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>

@if ($podeVerAgenda)
    @include('partials.gestante-consultas-agendadas', ['gestante' => $gestante])
@endif
