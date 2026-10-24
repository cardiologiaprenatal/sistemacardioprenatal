@php
    use App\Models\AgendamentoSolicitacao;
    $agendamentos = $gestante->consultaAgendamentos ?? collect();
    $indicacoes = ($gestante->agendamentoSolicitacoes ?? collect())
        ->where('status', AgendamentoSolicitacao::STATUS_PENDENTE)
        ->values();
    $proxima = $agendamentos
        ->first(fn ($item) => $item->estaAgendada() && $item->agendado_para && $item->agendado_para->gte(now()));
@endphp
<div class="main-card">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 20px;">Próxima consulta</h2>
    </div>

    @if ($proxima)
        <p style="margin: 0 0 12px; font-size: 15px; line-height: 1.5;">
            {{ $proxima->labelTipo() }} em {{ $proxima->dataFormatada() }} às {{ $proxima->horarioFormatado() }}
            @if ($proxima->profissional_nome)
                · {{ $proxima->profissional_nome }}
            @endif
        </p>
    @elseif ($indicacoes->isNotEmpty())
        <p style="margin: 0 0 12px; font-size: 14px; color: var(--muted);">
            Há indicação aguardando agendamento.
        </p>
    @else
        <p class="dash-empty" style="text-align:left; padding: 8px 0; margin: 0;">Nenhuma consulta agendada.</p>
    @endif

    @if ($agendamentos->isNotEmpty())
        <p class="table-scroll-hint">↔ Deslize horizontalmente para ver todas as colunas.</p>
        <div class="table-container table-container--flush">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="td-date">Data</th>
                        <th class="td-nowrap">Horário</th>
                        <th class="td-nowrap">Tipo</th>
                        <th>Profissional</th>
                        <th class="td-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agendamentos as $item)
                        <tr>
                            <td class="td-date">{{ $item->dataFormatada() }}</td>
                            <td class="td-nowrap">{{ $item->horarioFormatado() }}</td>
                            <td class="td-nowrap">{{ $item->labelTipo() }}</td>
                            <td>{{ $item->profissional_nome ?: 'Não informado' }}</td>
                            <td class="td-nowrap">
                                <span class="consulta-pill {{ $item->status === 'agendada' ? 'consulta-pill--info' : ($item->status === 'cancelada' ? 'consulta-pill--alert' : 'consulta-pill--ok') }}" style="margin:0;">
                                    {{ $item->labelStatus() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
