@extends('layouts.app')

@section('title', 'Consultas. Cardioprenatal')

@section('content')
@php
    use App\Models\AgendamentoSolicitacao;
    use App\Models\ConsultaAgendamento;
    $profissionalPadrao = old('profissional_id', auth()->id());
@endphp

<div class="page-header">
    <h1 class="page-title">Consultas</h1>
    <p class="page-subtitle">Cadastro, alteração, cancelamento e reagendamento. Vinculado à gestante; o gerenciamento acontece só nesta tela.</p>
</div>

@if (session('success'))
    <div class="main-card" role="status" style="margin-bottom: 16px; border-left: 4px solid var(--success); background: rgba(39, 174, 96, 0.08);">
        <p style="margin: 0;">{{ session('success') }}</p>
    </div>
@endif
@if ($errors->any())
    <div class="main-card" role="alert" style="margin-bottom: 16px; border-left: 4px solid var(--accent); background: rgba(139, 21, 48, 0.06);">
        <p style="margin: 0;">{{ $errors->first() }}</p>
    </div>
@endif

<div class="main-card" style="margin-bottom: 22px;">
    <form method="GET" action="{{ route('agenda.index') }}" class="form-section" style="margin-bottom: 0; padding: 20px 22px;">
        <h3 class="form-section-title" style="font-size: 17px; margin-bottom: 16px;">Filtros</h3>
        <div class="form-field-grid form-field-grid--2">
            <div class="form-field">
                <label class="form-label" for="filtro_gestante">Gestante</label>
                <input class="form-input" type="text" name="gestante_id" id="filtro_gestante" value="{{ $gestanteFiltro }}"
                       placeholder="Nome ou identificador" autocomplete="off">
            </div>
            <div class="form-field">
                <label class="form-label" for="filtro_tipo">Tipo</label>
                <select class="form-input" name="tipo" id="filtro_tipo">
                    <option value="">Todos</option>
                    <option value="{{ AgendamentoSolicitacao::TIPO_CONSULTA }}" @selected($tipo === AgendamentoSolicitacao::TIPO_CONSULTA)>Consulta</option>
                    <option value="{{ AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA }}" @selected($tipo === AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA)>Ecocardiograma</option>
                    <option value="{{ AgendamentoSolicitacao::TIPO_RETORNO }}" @selected($tipo === AgendamentoSolicitacao::TIPO_RETORNO)>Retorno</option>
                </select>
            </div>
        </div>
        <div class="form-actions" style="border-top: none; padding-top: 12px; margin-top: 8px;">
            <a href="{{ route('agenda.index') }}" class="btn-secondary-outline">Limpar</a>
            <button type="submit" class="btn-primary-custom">Filtrar</button>
        </div>
    </form>
</div>

@if ($podeIndicar)
<div class="main-card" style="margin-bottom: 22px;">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 20px;">Solicitar agendamento</h2>
    </div>
    <p style="margin: 0 0 16px; color: var(--muted); font-size: 14px; line-height: 1.5;">
        A secretaria recebe a indicação e marca data, horário e profissional nesta mesma tela.
    </p>
    <form method="POST" action="{{ route('agenda.indicar') }}" class="form-section" style="margin: 0; padding: 0;">
        @csrf
        <div class="form-field-grid form-field-grid--2">
            <div class="form-field">
                <label class="form-label" for="indicar_gestante">Gestante</label>
                <select class="form-input" name="gestante_id" id="indicar_gestante" required>
                    <option value="">Selecione</option>
                    @foreach ($gestantes as $g)
                        <option value="{{ $g->id }}" @selected((string) old('gestante_id', $gestanteFiltro) === (string) $g->id)>{{ $g->nome_exibicao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="indicar_tipo">Tipo</label>
                <select class="form-input" name="tipo" id="indicar_tipo" required>
                    <option value="consulta" @selected(old('tipo', 'consulta') === 'consulta')>Consulta</option>
                    <option value="ecocardiograma" @selected(old('tipo') === 'ecocardiograma')>Ecocardiograma</option>
                    <option value="retorno" @selected(old('tipo') === 'retorno')>Retorno</option>
                </select>
            </div>
        </div>
        <div class="form-field" style="margin-top: 12px;">
            <label class="form-label" for="indicar_observacao">Observação para a secretaria</label>
            <textarea class="form-input" name="observacao" id="indicar_observacao" rows="3" maxlength="2000"
                      style="resize: vertical; min-height: 72px; font-family: inherit; line-height: 1.5;"
                      placeholder="Ex.: ecocardiograma fetal, prioridade alta">{{ old('observacao') }}</textarea>
        </div>
        <div class="form-actions" style="border-top: none; padding-top: 12px;">
            <button type="submit" class="btn-primary-custom">Enviar indicação</button>
        </div>
    </form>
</div>
@endif

@if ($podeAgendar)
<div class="main-card" style="margin-bottom: 22px;">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 20px;">Nova consulta</h2>
    </div>
    <form method="POST" action="{{ route('agenda.store') }}" class="form-section" style="margin: 0; padding: 0;">
        @csrf
        <div class="form-field-grid form-field-grid--2">
            <div class="form-field">
                <label class="form-label" for="nova_gestante">Gestante</label>
                <select class="form-input" name="gestante_id" id="nova_gestante" required>
                    <option value="">Selecione</option>
                    @foreach ($gestantes as $g)
                        <option value="{{ $g->id }}" @selected((string) old('gestante_id', $gestanteFiltro) === (string) $g->id)>{{ $g->nome_exibicao }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="nova_tipo">Tipo</label>
                <select class="form-input" name="tipo" id="nova_tipo" required>
                    <option value="consulta" @selected(old('tipo', 'consulta') === 'consulta')>Consulta</option>
                    <option value="ecocardiograma" @selected(old('tipo') === 'ecocardiograma')>Ecocardiograma</option>
                    <option value="retorno" @selected(old('tipo') === 'retorno')>Retorno</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="nova_data">Data</label>
                <input class="form-input" type="date" name="data" id="nova_data" required
                       min="{{ now(config('app.timezone'))->toDateString() }}"
                       value="{{ old('data') }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="nova_horario">Horário</label>
                <input class="form-input" type="time" name="horario" id="nova_horario" required step="60"
                       value="{{ old('horario') }}">
            </div>
            <div class="form-field">
                <label class="form-label" for="nova_profissional">Profissional</label>
                <select class="form-input" name="profissional_id" id="nova_profissional" required>
                    <option value="">Selecione</option>
                    @foreach ($profissionais as $profissional)
                        <option value="{{ $profissional->id }}" @selected((string) $profissionalPadrao === (string) $profissional->id)>
                            {{ $profissional->name }}@if ($profissional->crm) · {{ $profissional->crm }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="nova_observacao">Observação</label>
                <input class="form-input" type="text" name="observacao" id="nova_observacao" maxlength="2000" value="{{ old('observacao') }}">
            </div>
        </div>
        <div class="form-actions" style="border-top: none; padding-top: 12px;">
            <button type="submit" class="btn-primary-custom">Agendar</button>
        </div>
    </form>
</div>
@endif

<div class="main-card" style="margin-bottom: 22px;">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 20px;">Pendências</h2>
    </div>
    <p style="margin: 0 0 16px; color: var(--muted); font-size: 14px; line-height: 1.5;">
        Indicações aguardando data e hora.
    </p>

    <p class="table-scroll-hint">↔ Deslize horizontalmente para ver todas as colunas.</p>
    <div class="table-container table-container--flush">
        <table class="data-table data-table--wide">
            <thead>
                <tr>
                    <th class="td-cell-nome">Gestante</th>
                    <th class="td-nowrap">Tipo</th>
                    <th>Indicação</th>
                    <th class="td-date">Solicitado em</th>
                    @if ($podeAgendar)
                        <th class="td-actions">Agendar</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($pendencias as $item)
                    @php $gestante = $item['gestante']; @endphp
                    <tr>
                        <td class="td-cell-nome">
                            <strong>{{ $gestante?->nome_exibicao ?? 'Gestante' }}</strong>
                            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">#{{ $gestante?->id }}</div>
                        </td>
                        <td class="td-nowrap">
                            <span class="consulta-pill {{ $item['tipo'] === 'ecocardiograma' ? 'consulta-pill--warn' : ($item['tipo'] === 'retorno' ? 'consulta-pill--info' : 'consulta-pill--ok') }}" style="margin:0;">
                                {{ AgendamentoSolicitacao::labelParaTipo($item['tipo']) }}
                            </span>
                        </td>
                        <td>
                            <div>{{ $item['titulo'] }}</div>
                            @if ($item['solicitante'])
                                <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">{{ $item['solicitante'] }}</div>
                            @endif
                            @if ($item['observacao'])
                                <div style="font-size: 12px; color: var(--muted); margin-top: 4px; line-height: 1.4;">{{ \Illuminate\Support\Str::limit($item['observacao'], 140) }}</div>
                            @endif
                        </td>
                        <td class="td-date">{{ $item['criado_em']?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Não informado' }}</td>
                        @if ($podeAgendar)
                        <td class="td-actions">
                            @if ($gestante)
                                <form method="POST" action="{{ route('agenda.store') }}" style="display:grid; gap:6px; min-width: 220px;">
                                    @csrf
                                    <input type="hidden" name="gestante_id" value="{{ $gestante->id }}">
                                    <input type="hidden" name="tipo" value="{{ $item['tipo'] }}">
                                    @if ($item['solicitacao_id'])
                                        <input type="hidden" name="solicitacao_id" value="{{ $item['solicitacao_id'] }}">
                                    @endif
                                    @if ($item['acompanhamento_id'])
                                        <input type="hidden" name="acompanhamento_id" value="{{ $item['acompanhamento_id'] }}">
                                    @endif
                                    @if ($tipo !== '')
                                        <input type="hidden" name="tipo_filtro" value="{{ $tipo }}">
                                    @endif
                                    <input class="form-input" type="date" name="data" required min="{{ now(config('app.timezone'))->toDateString() }}" style="padding: 6px 8px; font-size: 12px;">
                                    <input class="form-input" type="time" name="horario" required step="60" style="padding: 6px 8px; font-size: 12px;">
                                    <select class="form-input" name="profissional_id" required style="padding: 6px 8px; font-size: 12px;">
                                        <option value="">Profissional</option>
                                        @foreach ($profissionais as $profissional)
                                            <option value="{{ $profissional->id }}">{{ $profissional->name }}@if ($profissional->crm) · {{ $profissional->crm }}@endif</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-table btn-table--primary">Confirmar</button>
                                </form>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr class="data-table-empty">
                        <td colspan="{{ $podeAgendar ? 5 : 4 }}">Nenhuma pendência de agendamento neste filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="main-card">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 20px;">Consultas agendadas</h2>
    </div>
    <p class="table-scroll-hint">↔ Deslize horizontalmente para ver todas as colunas.</p>
    <div class="table-container table-container--flush">
        <table class="data-table data-table--wide">
            <thead>
                <tr>
                    <th class="td-cell-nome">Gestante</th>
                    <th class="td-date">Data</th>
                    <th class="td-nowrap">Horário</th>
                    <th>Profissional</th>
                    <th class="td-nowrap">Status</th>
                    <th class="td-nowrap">Tipo</th>
                    <th class="td-actions">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agendados as $item)
                    @php
                        $statusClass = $item->status === ConsultaAgendamento::STATUS_CANCELADA
                            ? 'consulta-pill--alert'
                            : ($item->status === ConsultaAgendamento::STATUS_REALIZADA ? 'consulta-pill--ok' : 'consulta-pill--info');
                    @endphp
                    <tr>
                        <td class="td-cell-nome">
                            <strong>{{ $item->gestante?->nome_exibicao ?? 'Gestante #'.$item->gestante_id }}</strong>
                            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">#{{ $item->gestante_id }}</div>
                        </td>
                        <td class="td-date">{{ $item->dataFormatada() }}</td>
                        <td class="td-nowrap">{{ $item->horarioFormatado() }}</td>
                        <td>{{ $item->profissional_nome ?: 'Não informado' }}</td>
                        <td class="td-nowrap">
                            <span class="consulta-pill {{ $statusClass }}" style="margin:0;">{{ $item->labelStatus() }}</span>
                        </td>
                        <td class="td-nowrap">{{ $item->labelTipo() }}</td>
                        <td class="td-actions">
                            <div style="display:grid; gap:8px; min-width: {{ $podeAgendar && $item->estaAgendada() ? '220px' : 'auto' }};">
                                <a href="{{ route('gestantes.show', $item->gestante_id) }}" class="btn-table btn-table--secondary">Ver gestante</a>
                                @if ($podeAgendar && $item->estaAgendada())
                                    <form method="POST" action="{{ route('agenda.update', $item) }}" style="display:grid; gap:6px;">
                                        @csrf
                                        @method('PUT')
                                        <input class="form-input" type="date" name="data" required
                                               min="{{ now(config('app.timezone'))->toDateString() }}"
                                               value="{{ $item->agendado_para?->timezone(config('app.timezone'))->toDateString() }}"
                                               style="padding: 6px 8px; font-size: 12px;">
                                        <input class="form-input" type="time" name="horario" required step="60"
                                               value="{{ $item->horarioFormatado() }}"
                                               style="padding: 6px 8px; font-size: 12px;">
                                        <select class="form-input" name="profissional_id" required style="padding: 6px 8px; font-size: 12px;">
                                            @foreach ($profissionais as $profissional)
                                                <option value="{{ $profissional->id }}" @selected((string) $item->profissional_id === (string) $profissional->id)>
                                                    {{ $profissional->name }}@if ($profissional->crm) · {{ $profissional->crm }}@endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn-table btn-table--primary">Reagendar</button>
                                    </form>
                                    <form method="POST" action="{{ route('agenda.cancel', $item) }}"
                                          onsubmit="return confirm('Cancelar esta consulta?');">
                                        @csrf
                                        <button type="submit" class="btn-table btn-table--danger">Cancelar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="data-table-empty">
                        <td colspan="7">Nenhuma consulta neste filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if (method_exists($agendados, 'hasPages') && $agendados->hasPages())
        <div class="table-pagination">
            {{ $agendados->links() }}
        </div>
    @endif
</div>
@endsection
