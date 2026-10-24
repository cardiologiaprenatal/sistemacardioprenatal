@extends('layouts.app')

@section('title', 'Fila de ecocardiograma. Cardioprenatal')

@section('content')
@php
    use App\Http\Controllers\AcompanhamentoController;
    use App\Models\Acompanhamento;
    use App\Services\GestanteTimelineService;
    $timelineLabels = app(GestanteTimelineService::class);
@endphp

<div class="page-header">
    <h1 class="page-title">Fila de ecocardiograma</h1>
    <p class="page-subtitle">Gestantes encaminhadas para ecocardiograma, com médico responsável e situação do agendamento.</p>
</div>

@if (session('success'))
    <div class="main-card" role="status" style="margin-bottom: 16px; border-left: 4px solid var(--success); background: rgba(39, 174, 96, 0.08);">
        <p style="margin: 0;">{{ session('success') }}</p>
    </div>
@endif

@if (($syncResult['acompanhamentos'] ?? 0) > 0 || ($syncResult['alertas'] ?? 0) > 0)
    <div class="main-card" role="status" style="margin-bottom: 16px; border-left: 4px solid var(--status-info); background: var(--status-info-bg);">
        <p style="margin: 0; font-size: 14px;">
            Fila atualizada: {{ $syncResult['acompanhamentos'] }} item(ns)
            e {{ $syncResult['alertas'] }} alerta(s) novos.
        </p>
    </div>
@endif

<div class="dash-kpis" style="margin-bottom: 18px;">
    <div class="dash-kpi">
        <div class="dash-kpi-label">Pendentes</div>
        <div class="dash-kpi-value dash-kpi-value--warn">{{ $kpis['pendentes'] }}</div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-label">Em andamento</div>
        <div class="dash-kpi-value">{{ $kpis['em_andamento'] }}</div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-label">Prioridade alta</div>
        <div class="dash-kpi-value dash-kpi-value--risk-high">{{ $kpis['urgentes'] }}</div>
    </div>
    <div class="dash-kpi">
        <div class="dash-kpi-label">Alertas abertos</div>
        <div class="dash-kpi-value dash-kpi-value--accent">{{ $kpis['alertas'] }}</div>
    </div>
</div>

@if ($alertasAbertos->isNotEmpty())
    <div class="main-card">
        <h2 class="card-title" style="font-size: 18px; margin: 0 0 14px;">Alertas ativos</h2>
        <div class="fila-alertas">
            @foreach ($alertasAbertos as $alerta)
                <div class="fila-alerta fila-alerta--{{ $alerta->severidade }}">
                    <div style="min-width:0; flex:1;">
                        <strong>{{ $alerta->titulo }}</strong>
                        <p style="margin: 4px 0 0; font-size: 13px; color: var(--muted);">
                            {{ $alerta->gestante?->nome_exibicao ?? 'Gestante #'.$alerta->gestante_id }}
                            · {{ $alerta->created_at?->format('d/m/Y H:i') }}
                        </p>
                        @if ($alerta->mensagem)
                            <p style="margin: 6px 0 0; font-size: 14px; line-height: 1.4;">{{ $alerta->mensagem }}</p>
                        @endif
                    </div>
                    <div class="gestante-header-actions" style="flex-shrink:0;">
                        <a href="{{ route('gestantes.show', $alerta->gestante_id) }}" class="btn-table btn-table--primary">Ver</a>
                        <form method="POST" action="{{ route('alertas.resolver', $alerta) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-table btn-table--secondary">Resolver</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="main-card">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 18px;">Fila de ecocardiograma</h2>
        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <form method="POST" action="{{ route('acompanhamentos.sync') }}">
                @csrf
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="motivo" value="{{ $motivo }}">
                <button type="submit" class="btn-secondary-outline" style="padding: 8px 14px; font-size: 13px;">Atualizar fila</button>
            </form>
            <form method="GET" action="{{ route('acompanhamentos.index') }}" class="fila-filtros">
                <select name="status" class="form-input" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                    <option value="abertos" @selected($status === 'abertos')>Abertos</option>
                    <option value="pendente" @selected($status === 'pendente')>Pendentes</option>
                    <option value="em_andamento" @selected($status === 'em_andamento')>Em andamento</option>
                    <option value="concluido" @selected($status === 'concluido')>Concluídos</option>
                    <option value="cancelado" @selected($status === 'cancelado')>Cancelados</option>
                </select>
                <select name="motivo" class="form-input" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
                    <option value="">Todos os motivos</option>
                    <option value="eco_recomendada" @selected($motivo === 'eco_recomendada')>Eco recomendada</option>
                    <option value="risco_alto" @selected($motivo === 'risco_alto')>Risco alto</option>
                </select>
            </form>
        </div>
    </div>

    <div class="table-container table-container--flush">
        <table class="data-table data-table--wide">
            <thead>
                <tr>
                    <th class="td-cell-nome">Gestante</th>
                    <th>Médico responsável</th>
                    <th>Motivo</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th class="td-date">Encaminhamento</th>
                    <th class="td-date">Agendado</th>
                    <th class="td-actions">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($itens as $item)
                    <tr>
                        <td class="td-cell-nome">
                            <strong>{{ $item->gestante?->nome_exibicao ?? '#'.$item->gestante_id }}</strong>
                            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">{{ $item->titulo }}</div>
                        </td>
                        <td>{{ $item->encaminhadoPor?->name ?? 'Não informado' }}</td>
                        <td>{{ AcompanhamentoController::labelMotivo($item->motivo) }}</td>
                        <td>
                            <span class="consulta-pill {{ $item->prioridade === 'urgente' || $item->prioridade === 'alta' ? 'consulta-pill--risk-high' : ($item->prioridade === 'moderada' ? 'consulta-pill--risk-mid' : 'consulta-pill--risk-low') }}">
                                {{ AcompanhamentoController::labelPrioridade($item->prioridade) }}
                            </span>
                        </td>
                        <td>
                            {{ $timelineLabels->labelStatus($item->status) }}
                            @if ($item->agendado_para)
                                <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Agendada</div>
                            @elseif ($item->status === Acompanhamento::STATUS_CONCLUIDO)
                                <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Atendida</div>
                            @else
                                <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Aguardando agendamento</div>
                            @endif
                        </td>
                        <td class="td-date">
                            @if ($item->encaminhado_em)
                                {{ $item->encaminhado_em->timezone(config('app.timezone'))->format('d/m/Y') }}
                                <div style="font-size: 12px; color: var(--muted);">{{ $item->encaminhado_em->timezone(config('app.timezone'))->format('H:i') }}</div>
                            @else
                                {{ $item->created_at?->format('d/m/Y') ?? 'Não informado' }}
                            @endif
                        </td>
                        <td class="td-date">
                                @if ($item->agendado_para)
                                    {{ $item->agendado_para->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @else
                                    Não informado
                                @endif
                        </td>
                        <td class="td-actions">
                            <div class="td-actions-inner td-actions-inner--icons">
                                <a href="{{ route('gestantes.show', $item->gestante_id) }}"
                                   class="btn-table btn-table--primary btn-table--icon"
                                   title="Consultar gestante"
                                   aria-label="Consultar gestante">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>
                                @if ($item->estaAberto())
                                    @if ($item->status === Acompanhamento::STATUS_PENDENTE)
                                        <form method="POST" action="{{ route('acompanhamentos.update', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="em_andamento">
                                            @if ($item->agendado_para)
                                                <input type="hidden" name="agendado_para" value="{{ $item->agendado_para->format('Y-m-d\\TH:i') }}">
                                            @endif
                                            <button type="submit"
                                                    class="btn-table btn-table--secondary btn-table--icon"
                                                    title="Iniciar ecocardiograma"
                                                    aria-label="Iniciar ecocardiograma">
                                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                    <path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86A1 1 0 0 0 8 5.14z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('acompanhamentos.update', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="concluido">
                                        <button type="submit"
                                                class="btn-table btn-table--secondary btn-table--icon"
                                                title="Concluir ecocardiograma"
                                                aria-label="Concluir ecocardiograma">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M20 6L9 17l-5-5"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="data-table-empty">
                        <td colspan="8">Nenhuma gestante na fila de ecocardiograma com esses filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 16px;">
        {{ $itens->links() }}
    </div>
</div>

<style>
    .fila-filtros { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .fila-alertas { display: grid; gap: 10px; }
    .fila-alerta {
        display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; justify-content: space-between;
        padding: 14px 16px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface);
    }
    .fila-alerta--urgente { border-left: 4px solid var(--risk-high); background: var(--risk-high-bg); }
    .fila-alerta--atencao { border-left: 4px solid var(--risk-mid); background: var(--risk-mid-bg); }
    .fila-alerta--info { border-left: 4px solid var(--status-info); background: var(--status-info-bg); }
</style>
@endsection
