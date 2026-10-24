@extends('layouts.app')

@section('title', 'Histórico de atendimento. Cardioprenatal')

@section('content')
    @php
        use App\Models\GestanteWhatsapp;
        $sort = $sort ?? 'ultima_data';
        $direction = $direction ?? 'desc';
        $nextDir = ($sort === 'ultima_data' && $direction === 'desc') ? 'asc' : 'desc';
        $sortQuery = array_merge(request()->except(['page', 'sort', 'direction']), [
            'sort' => 'ultima_data',
            'direction' => $nextDir,
        ]);
        $pillCategoria = function (?string $categoria): string {
            return match ($categoria) {
                GestanteWhatsapp::CATEGORIA_LEMBRETE_CONSULTA => 'consulta-pill--warn',
                GestanteWhatsapp::CATEGORIA_CONFIRMACAO_CONSULTA => 'consulta-pill--ok',
                GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO => 'consulta-pill--info',
                default => '',
            };
        };
    @endphp

    <div class="page-header">
        <h1 class="page-title">Histórico de atendimento</h1>
        <p class="page-subtitle">Conversas do WhatsApp com as gestantes.</p>
        @if (request()->filled('gestante_id'))
            <p class="page-subtitle" style="margin-top: 4px;">
                Filtrado pela gestante #{{ request('gestante_id') }}.
                <a href="{{ route('historico-whatsapp.index') }}" style="color: var(--accent); font-weight: 600;">Ver todas</a>
            </p>
        @endif
    </div>

    <div class="main-card" style="margin-bottom: 22px;">
        <form method="GET" action="{{ route('historico-whatsapp.index') }}" class="form-section" style="margin-bottom: 0; padding: 20px 22px;">
            <h3 class="form-section-title" style="font-size: 17px; margin-bottom: 16px;">Filtros</h3>
            <div class="form-field-grid form-field-grid--2">
                <div class="form-field">
                    <label class="form-label" for="filtro_nome">Nome da gestante</label>
                    <input class="form-input" type="text" name="nome" id="filtro_nome" value="{{ request('nome') }}"
                           placeholder="Identificação cadastrada" autocomplete="off">
                </div>
                <div class="form-field">
                    <label class="form-label" for="filtro_cpf">CPF</label>
                    <input class="form-input" type="text" name="cpf" id="filtro_cpf" value="{{ request('cpf') }}" placeholder="Somente números ou formatado" autocomplete="off">
                </div>
                <div class="form-field">
                    <label class="form-label" for="filtro_telefone">Telefone</label>
                    <input class="form-input" type="text" name="telefone" id="filtro_telefone" value="{{ request('telefone') }}" placeholder="DDD + número" autocomplete="off">
                </div>
                <div class="form-field">
                    <label class="form-label" for="filtro_categoria">Tipo de mensagem</label>
                    <select class="form-input" name="categoria" id="filtro_categoria">
                        <option value="">Todas</option>
                        <option value="{{ GestanteWhatsapp::CATEGORIA_CONVERSA }}" @selected(request('categoria') === GestanteWhatsapp::CATEGORIA_CONVERSA)>Conversa</option>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label" for="filtro_data_inicio">Data início</label>
                    <input class="form-input" type="date" name="data_inicio" id="filtro_data_inicio" value="{{ request('data_inicio') }}">
                </div>
                <div class="form-field">
                    <label class="form-label" for="filtro_data_fim">Data fim</label>
                    <input class="form-input" type="date" name="data_fim" id="filtro_data_fim" value="{{ request('data_fim') }}">
                </div>
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            @if (request()->filled('gestante_id'))
                <input type="hidden" name="gestante_id" value="{{ request('gestante_id') }}">
            @endif
            <div class="form-actions" style="border-top: none; padding-top: 12px; margin-top: 8px;">
                <a href="{{ route('historico-whatsapp.index') }}" class="btn-secondary-outline">Limpar</a>
                <button type="submit" class="btn-primary-custom">Filtrar</button>
            </div>
        </form>
    </div>

    <div class="main-card">
        <div class="card-toolbar">
            <h2 class="card-title" style="margin: 0; font-size: 20px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Conversas
            </h2>
        </div>

        <p class="table-scroll-hint">↔ Deslize horizontalmente para ver todas as colunas.</p>
        <div class="table-container table-container--flush">
            <table class="data-table data-table--wide">
                <thead>
                    <tr>
                        <th class="td-cell-nome">Nome da gestante</th>
                        <th class="td-num td-num--narrow">Identificador</th>
                        <th class="td-cpf">CPF</th>
                        <th class="td-tel">Telefone</th>
                        <th class="td-msg">Última mensagem</th>
                        <th class="td-nowrap">Direção</th>
                        <th class="td-nowrap">Categoria</th>
                        <th class="td-num">Tempo de atendimento</th>
                        <th class="td-date">
                            <a href="{{ route('historico-whatsapp.index', $sortQuery) }}"
                               style="color: inherit; text-decoration: none; border-bottom: 1px dashed rgba(127,12,26,0.35);">
                                Data
                                @if ($sort === 'ultima_data')
                                    <span style="font-weight: 800;">{{ $direction === 'desc' ? '↓' : '↑' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="td-actions">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $tipoUltimo = $row->ultimo_tipo ?? '';
                            $tipoLabel = $tipoUltimo === 'saida' ? 'Saída' : ($tipoUltimo === 'entrada' ? 'Entrada' : 'Não informado');
                            $categoriaUltima = $row->ultima_categoria ?? null;
                            $tempo = $row->ultimo_tempo_atendimento;
                            $tempoFmt = $tempo !== null
                                ? (function () use ($tempo) {
                                    $s = (int) $tempo;
                                    $h = intdiv($s, 3600);
                                    $m = intdiv($s % 3600, 60);
                                    $sec = $s % 60;
                                    if ($h > 0) {
                                        return sprintf('%dh %dm %ds', $h, $m, $sec);
                                    }
                                    if ($m > 0) {
                                        return sprintf('%dm %ds', $m, $sec);
                                    }
                                    return sprintf('%ds', $sec);
                                })()
                                : 'Não informado';
                            $ultimaData = $row->ultima_data ? \Carbon\Carbon::parse($row->ultima_data)->timezone(config('app.timezone'))->format('d/m/Y H:i') : 'Não informado';
                        @endphp
                        <tr>
                            <td class="td-cell-nome"><strong>{{ $row->nome_exibicao }}</strong></td>
                            <td class="td-num td-num--narrow"><strong>#{{ $row->id }}</strong></td>
                            <td class="td-cpf">{{ $row->cpf ? $row->cpf_formatado : 'Não informado' }}</td>
                            <td class="td-tel">{{ $row->telefone ? $row->telefone_formatado : 'Não informado' }}</td>
                            <td class="td-msg">{{ \Illuminate\Support\Str::limit($row->ultima_mensagem ?? '', 90) }}</td>
                            <td class="td-nowrap">{{ $tipoLabel }}</td>
                            <td class="td-nowrap">
                                @if (in_array($categoriaUltima, [
                                    GestanteWhatsapp::CATEGORIA_LEMBRETE_CONSULTA,
                                    GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO,
                                    GestanteWhatsapp::CATEGORIA_CONFIRMACAO_CONSULTA,
                                ], true))
                                    <span class="consulta-pill {{ $pillCategoria($categoriaUltima) }}" style="margin: 0;">
                                        {{ GestanteWhatsapp::labelCategoria($categoriaUltima) }}
                                    </span>
                                @else
                                    {{ GestanteWhatsapp::labelCategoria($categoriaUltima) }}
                                @endif
                            </td>
                            <td class="td-num">{{ $tempoFmt }}</td>
                            <td class="td-date">{{ $ultimaData }}</td>
                            <td class="td-actions">
                                <div class="td-actions-inner">
                                    <button type="button"
                                            class="btn-table btn-table--primary"
                                            data-gestante-id="{{ $row->id }}"
                                            data-gestante-label="{{ e($row->nome_exibicao) }}"
                                            onclick="abrirConversa(this)">
                                        Ver conversa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="data-table-empty">
                            <td colspan="10">
                                <p style="margin: 0 0 8px;">Nenhum registro de atendimento por WhatsApp encontrado.</p>
                                <p style="margin: 0; font-size: 13px; color: var(--muted); line-height: 1.5;">
                                    Só entram aqui conversas com mensagens vinculadas a uma gestante já cadastrada. Se você acabou de gerar dados de teste no terminal e a tela continua vazia,
                                    o servidor web pode estar lendo outra base ou configuração antiga em cache: rode
                                    <code style="font-size: 12px;">php artisan config:clear</code>
                                    e atualize a página. Para ver contagens no mesmo ambiente do Artisan:
                                    <code style="font-size: 12px;">php artisan whatsapp:diagnostico</code>.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="table-pagination">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

    <div id="modalConversa"
         class="app-modal-overlay"
         style="background: rgba(28, 26, 26, 0.45); backdrop-filter: blur(4px);"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modalConversaTitulo"
         aria-hidden="true">
        <div class="main-card app-modal-panel app-modal-panel--lg" style="max-height: min(90vh, 100dvh); display: flex; flex-direction: column; padding: 22px; margin: 0;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 16px;">
                <div>
                    <h2 id="modalConversaTitulo" style="font-family: 'DM Serif Display', serif; font-size: clamp(1.1rem, 3vw, 1.35rem); color: var(--primary); margin-bottom: 4px;">
                        Conversa
                    </h2>
                    <p id="modalConversaSub" style="color: var(--muted); font-size: 14px; line-height: 1.45;"></p>
                </div>
                <button type="button" onclick="fecharConversa()"
                        style="padding: 8px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface); cursor: pointer; font-weight: 600; color: var(--text);">
                    Fechar
                </button>
            </div>
            <div id="modalConversaBody"
                 style="flex: 1; min-height: 200px; max-height: 60vh; overflow-y: auto; border: 1px solid var(--border); border-radius: 16px; padding: 12px; background: rgba(253, 240, 240, 0.35);">
                <p style="color: var(--muted); text-align: center; padding: 24px;">Carregando conversa…</p>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function fecharConversa() {
                const modal = document.getElementById('modalConversa');
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function escapeHtml(s) {
                const d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            function formatarData(iso) {
                if (!iso) return 'Não informado';
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return iso;
                return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
            }

            function pillCategoria(categoria, label) {
                if (!categoria || categoria === 'conversa') {
                    return escapeHtml(label || 'Conversa');
                }
                const cls = categoria === 'lembrete_retorno'
                    ? 'consulta-pill--info'
                    : (categoria === 'confirmacao_consulta' ? 'consulta-pill--ok' : 'consulta-pill--warn');
                return '<span class="consulta-pill ' + cls + '" style="margin:0 0 0 6px; text-transform:none; letter-spacing:0;">'
                    + escapeHtml(label || categoria) + '</span>';
            }

            function renderMensagens(mensagens) {
                const body = document.getElementById('modalConversaBody');
                if (!mensagens || !mensagens.length) {
                    body.innerHTML = '<p style="color: var(--muted); text-align: center; padding: 24px;">Nenhuma mensagem nesta conversa.</p>';
                    return;
                }

                let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
                mensagens.forEach(function (m) {
                    const entrada = m.tipo === 'entrada';
                    const align = entrada ? 'flex-start' : 'flex-end';
                    const bg = entrada
                        ? 'rgba(255,255,255,0.95)'
                        : 'linear-gradient(135deg, rgba(127,12,26,0.12), rgba(231,76,60,0.18))';
                    const border = entrada ? '1px solid rgba(240, 213, 213, 0.9)' : '1px solid rgba(127,12,26,0.2)';
                    const label = m.remetente_label
                        || (entrada ? 'Paciente' : ((m.categoria === 'lembrete_consulta' || m.categoria === 'lembrete_retorno' || m.categoria === 'confirmacao_consulta') ? 'Sistema' : 'Equipe'));
                    const tempo = m.tempo_atendimento_formatado
                        ? (' · ' + escapeHtml(m.tempo_atendimento_formatado))
                        : '';
                    const categoriaHtml = pillCategoria(m.categoria, m.categoria_label);

                    html += '<div style="display: flex; justify-content: ' + align + ';">';
                    html += '<div style="max-width: 92%; border-radius: 14px; padding: 10px 14px; background: ' + bg + '; border: ' + border + '; box-shadow: 0 2px 8px rgba(127,12,26,0.06);">';
                    html += '<div style="font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; display:flex; align-items:center; flex-wrap:wrap; gap:4px;">'
                        + label + ' · ' + formatarData(m.created_at) + tempo + categoriaHtml + '</div>';
                    html += '<div style="font-size: 14px; color: var(--text); line-height: 1.5; white-space: pre-wrap;">' + escapeHtml(m.mensagem || '') + '</div>';
                    html += '</div></div>';
                });
                html += '</div>';
                body.innerHTML = html;
            }

            async function abrirConversa(btn) {
                const id = btn.getAttribute('data-gestante-id');
                const label = btn.getAttribute('data-gestante-label') || '';
                const acompanhamentoId = btn.getAttribute('data-acompanhamento-id') || '';
                const tituloExtra = btn.getAttribute('data-conversa-titulo') || '';
                const modal = document.getElementById('modalConversa');
                const body = document.getElementById('modalConversaBody');
                const sub = document.getElementById('modalConversaSub');
                const titulo = document.getElementById('modalConversaTitulo');

                titulo.textContent = acompanhamentoId ? 'Mensagens do agendamento' : 'Conversa';
                sub.textContent = [
                    label ? ('Gestante: ' + label) : ('Identificador #' + id),
                    tituloExtra,
                ].filter(Boolean).join(' · ');
                body.innerHTML = '<p style="color: var(--muted); text-align: center; padding: 24px;">Carregando conversa…</p>';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';

                let url = '{{ url('/api/gestante-whatsapp') }}/' + encodeURIComponent(id);
                if (acompanhamentoId) {
                    url += '?acompanhamento_id=' + encodeURIComponent(acompanhamentoId);
                }

                try {
                    const res = await fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!res.ok) {
                        body.innerHTML = '<p style="color: var(--primary); text-align: center; padding: 24px;">Não foi possível carregar a conversa.</p>';
                        return;
                    }

                    const data = await res.json();
                    renderMensagens(data.mensagens || []);
                } catch (e) {
                    body.innerHTML = '<p style="color: var(--primary); text-align: center; padding: 24px;">Erro de conexão ao carregar a conversa.</p>';
                }
            }

            document.getElementById('modalConversa').addEventListener('click', function (e) {
                if (e.target === this) {
                    fecharConversa();
                }
            });

            document.addEventListener('keydown', function (e) {
                const modal = document.getElementById('modalConversa');
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    fecharConversa();
                }
            });
        </script>
    @endpush
@endsection
