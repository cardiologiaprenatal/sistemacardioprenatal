@php
    use App\Http\Controllers\AcompanhamentoController;
    use App\Services\GestanteTimelineService;
    $timelineLabels = app(GestanteTimelineService::class);
    $alertasAbertos = $gestante->alertas->whereNull('resolvido_em')->values();
    $acompAbertos = $gestante->acompanhamentos->filter(fn ($a) => $a->estaAberto())->values();
@endphp

@if ($alertasAbertos->isNotEmpty() || $acompAbertos->isNotEmpty())
    <div class="main-card" style="margin-bottom: 0;">
        <h2 class="card-title" style="font-size: 18px; margin: 0 0 14px;">Acompanhamento e alertas</h2>

        @if ($alertasAbertos->isNotEmpty())
            <div class="fila-alertas" style="margin-bottom: 14px;">
                @foreach ($alertasAbertos as $alerta)
                    <div class="fila-alerta fila-alerta--{{ $alerta->severidade }}">
                        <div style="min-width:0; flex:1;">
                            <strong>{{ $alerta->titulo }}</strong>
                            @if ($alerta->mensagem)
                                <p style="margin: 6px 0 0; font-size: 14px; line-height: 1.4;">{{ $alerta->mensagem }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('alertas.resolver', $alerta) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-table btn-table--secondary">Resolver</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($acompAbertos->isNotEmpty())
            <div style="display: grid; gap: 10px;">
                @foreach ($acompAbertos as $item)
                    <div style="display:grid; gap:12px; padding: 12px 14px; border: 1px solid var(--border); border-radius: 12px;">
                        <div style="display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:flex-start;">
                            <div style="min-width:0;">
                                <strong>{{ $item->titulo }}</strong>
                                <p style="margin: 4px 0 0; font-size: 13px; color: var(--muted);">
                                    {{ AcompanhamentoController::labelMotivo($item->motivo) }}
                                    · {{ $timelineLabels->labelStatus($item->status) }}
                                    · {{ AcompanhamentoController::labelPrioridade($item->prioridade) }}
                                    @if ($item->agendado_para)
                                        · Agendado: {{ $item->agendado_para->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    @endif
                                </p>
                            </div>
                            <div class="gestante-header-actions">
                                @if ($item->status === 'pendente')
                                    <form method="POST" action="{{ route('acompanhamentos.update', $item) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="em_andamento">
                                        @if ($item->agendado_para)
                                            <input type="hidden" name="agendado_para" value="{{ $item->agendado_para->format('Y-m-d\\TH:i') }}">
                                        @endif
                                        <button type="submit" class="btn-table btn-table--secondary">Iniciar</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('acompanhamentos.update', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="concluido">
                                    <button type="submit" class="btn-table btn-table--primary">Concluir</button>
                                </form>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('acompanhamentos.update', $item) }}" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $item->status }}">
                            <div class="form-field" style="margin:0; flex:1 1 200px;">
                                <label class="form-label" for="agendado_para_{{ $item->id }}">Data/hora do retorno</label>
                                <input
                                    type="datetime-local"
                                    class="form-input"
                                    id="agendado_para_{{ $item->id }}"
                                    name="agendado_para"
                                    value="{{ $item->agendado_para?->timezone(config('app.timezone'))->format('Y-m-d\\TH:i') }}"
                                >
                            </div>
                            <button type="submit" class="btn-table btn-table--secondary">Salvar agenda</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <style>
        .fila-alertas { display: grid; gap: 10px; }
        .fila-alerta {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; justify-content: space-between;
            padding: 14px 16px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface);
        }
        .fila-alerta--urgente { border-left: 4px solid var(--risk-high); background: var(--risk-high-bg); }
        .fila-alerta--atencao { border-left: 4px solid var(--risk-mid); background: var(--risk-mid-bg); }
        .fila-alerta--info { border-left: 4px solid var(--status-info); background: var(--status-info-bg); }
    </style>
@endif
