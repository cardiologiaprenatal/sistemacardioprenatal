{{-- Laudo clínico gerado pela camada Gemini (não altera score CatBoost). --}}
@php
    /** @var \App\Models\InterpretacaoClinica|null $interpretacao */
    $interpretacao = $interpretacao ?? null;
@endphp

@if ($interpretacao)
    <div class="main-card" style="margin-bottom: 0;">
        <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px;">
            <div>
                <h2 class="card-title" style="font-size: 20px; margin: 0 0 6px;">Laudo clínico</h2>
                <p class="page-subtitle" style="margin: 0;">
                    Narrativa interpretativa. O risco calculado permanece inalterado.
                </p>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                @if ($interpretacao->status === 'concluida' || $interpretacao->status === 'cache_hit')
                    <span class="consulta-pill consulta-pill--info">{{ $interpretacao->mock ? 'Mock' : 'Gemini' }}</span>
                @elseif ($interpretacao->status === 'pendente')
                    <span class="consulta-pill consulta-pill--warn">Gerando…</span>
                @elseif ($interpretacao->status === 'erro')
                    <span class="consulta-pill consulta-pill--alert">Erro</span>
                @endif
                @if ($interpretacao->model_id)
                    <span style="font-size: 12px; color: var(--muted);">{{ $interpretacao->model_id }}</span>
                @endif
            </div>
        </div>

        @if ($interpretacao->status === 'erro')
            <p style="margin: 0; color: var(--muted); font-size: 14px;">
                {{ $interpretacao->erro_mensagem ?: 'Falha ao gerar interpretação.' }}
            </p>
        @elseif ($interpretacao->status === 'pendente')
            <p style="margin: 0; color: var(--muted); font-size: 14px;">
                Interpretação em andamento. Atualize a página em instantes.
            </p>
        @else
            @php
                $fatores = is_array($interpretacao->fatores_explicados) ? $interpretacao->fatores_explicados : [];
                $recs = is_array($interpretacao->recomendacoes) ? $interpretacao->recomendacoes : [];
                $deltas = is_array($interpretacao->deltas) ? $interpretacao->deltas : [];
            @endphp

            @if (count($deltas) > 0)
                <div class="clinical-msg-evolucao form-section">
                    <span class="form-label" style="display: block; margin-bottom: 10px;">Evolução entre consultas</span>
                    <ul style="margin: 0; padding-left: 18px; display: grid; gap: 8px;">
                        @foreach ($deltas as $delta)
                            <li style="font-size: 14px; line-height: 1.5; color: var(--text);">{{ $delta }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($interpretacao->resumo_medico)
                <div class="form-section" style="padding: 18px; margin-bottom: 16px;">
                    <span class="form-label">Resumo para a equipe</span>
                    <p class="consulta-valor" style="font-weight: 400; line-height: 1.55; white-space: pre-wrap;">{{ $interpretacao->resumo_medico }}</p>
                </div>
            @endif

            @if ($interpretacao->resumo_gestante)
                <div class="clinical-msg-gestante form-section">
                    <span class="form-label">Mensagem para a gestante</span>
                    <p class="consulta-valor" style="font-weight: 400; line-height: 1.6; white-space: pre-wrap; font-size: 15px;">{{ $interpretacao->resumo_gestante }}</p>
                </div>
            @endif

            @if (count($fatores) > 0)
                <div style="margin-bottom: 16px;">
                    <span class="form-label" style="display: block; margin-bottom: 10px;">Fatores explicados</span>
                    <ul style="margin: 0; padding-left: 18px; display: grid; gap: 8px;">
                        @foreach ($fatores as $fator)
                            @php
                                $nome = is_array($fator) ? ($fator['feature'] ?? $fator['nome'] ?? 'Não informado') : 'Não informado';
                                $expl = is_array($fator) ? ($fator['explicacao'] ?? '') : '';
                                $imp = is_array($fator) ? ($fator['impacto'] ?? null) : null;
                            @endphp
                            <li style="font-size: 14px; line-height: 1.45; color: var(--text);">
                                <strong>{{ $nome }}</strong>
                                @if ($imp !== null && $imp !== '')
                                    <span style="color: var(--muted);">(impacto {{ is_numeric($imp) ? number_format((float) $imp, 3) : $imp }})</span>
                                @endif
                                @if ($expl)
                                    : {{ $expl }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($recs) > 0)
                <div style="margin-bottom: 16px;">
                    <span class="form-label" style="display: block; margin-bottom: 10px;">Recomendações</span>
                    <ul style="margin: 0; padding-left: 18px; display: grid; gap: 6px;">
                        @foreach ($recs as $rec)
                            <li style="font-size: 14px; line-height: 1.45; color: var(--text);">{{ $rec }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p style="margin: 18px 0 0; font-size: 12px; color: var(--muted);">
                Gerado em {{ $interpretacao->updated_at?->format('d/m/Y H:i') ?? 'Não informado' }}
                @if ($interpretacao->prompt_version)
                    · {{ $interpretacao->prompt_version }}
                @endif
                @if ($interpretacao->latency_ms)
                    · {{ $interpretacao->latency_ms }} ms
                @endif
                @if ($interpretacao->tokens_in || $interpretacao->tokens_out)
                    · tokens {{ (int) $interpretacao->tokens_in }}/{{ (int) $interpretacao->tokens_out }}
                @endif
            </p>
        @endif
    </div>
@endif
