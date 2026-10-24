@extends('layouts.app')

@section('title', 'WhatsApp. Cardioprenatal')

@section('content')
    <style>
        .wa-inbox {
            display: grid;
            grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
            gap: 16px;
            align-items: stretch;
        }
        .wa-people-card {
            display: flex;
            flex-direction: column;
            height: min(72vh, 640px);
            min-height: 460px;
            padding: 0;
            overflow: hidden;
            margin-bottom: 0;
        }
        .wa-people-card .card-toolbar {
            padding: 14px 14px 10px;
            margin: 0;
        }
        .wa-people {
            overflow-y: auto;
            flex: 1;
        }
        .wa-person {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: #fff;
            padding: 12px 14px;
            cursor: pointer;
            font-family: inherit;
            color: var(--text);
        }
        .wa-person.is-active {
            background: var(--accent-light);
            box-shadow: inset 3px 0 0 var(--primary);
        }
        .wa-person strong {
            display: block;
            font-size: 14px;
            margin-bottom: 2px;
        }
        .wa-person small,
        .wa-person-preview {
            display: block;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.35;
        }
        .wa-person-preview {
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .wa-inbox .wa-window { margin-bottom: 0; }
        @media (max-width: 800px) {
            .wa-inbox { grid-template-columns: 1fr; }
            .wa-people-card {
                height: auto;
                max-height: 280px;
                min-height: 0;
            }
        }
    </style>

    <div class="page-header">
        <h1 class="page-title">WhatsApp</h1>
        <p class="page-subtitle">Conversas da equipe com as gestantes. O histórico completo continua na consulta de atendimento.</p>
    </div>

    <div class="main-card" style="margin-bottom: 16px;">
        <div class="card-toolbar">
            <h2 class="card-title" style="margin: 0; font-size: 20px;">Sessão do WhatsApp</h2>
            @if ($pronto)
                <span class="consulta-pill consulta-pill--ok" style="margin: 0;">{{ $status }}</span>
            @elseif ($status)
                <span class="consulta-pill consulta-pill--warn" style="margin: 0;">{{ $status }}</span>
            @else
                <span class="consulta-pill consulta-pill--alert" style="margin: 0;">indisponível</span>
            @endif
        </div>
        <p style="margin: 0; line-height: 1.5;">
            Sessão <strong>{{ $sessao }}</strong>.
            @if ($pronto)
                O canal está conectado e o envio está disponível.
            @elseif ($status)
                O estado atual é <strong>{{ $status }}</strong>. O envio não está pronto enquanto a sessão não estiver conectada.
            @elseif ($configurado)
                Não foi possível consultar o estado da sessão. O envio não está pronto.
            @else
                A integração do WhatsApp está desabilitada ou sem endereço. O envio não está pronto.
            @endif
        </p>
    </div>

    @if ($gestantes->isEmpty())
        <div class="main-card">
            <p class="dash-empty">Nenhuma gestante cadastrada.</p>
        </div>
    @else
        <div class="wa-inbox">
            <div class="main-card wa-people-card">
                <div class="card-toolbar">
                    <h2 class="card-title" style="margin: 0; font-size: 18px;">Gestantes</h2>
                    <a href="{{ route('historico-whatsapp.index') }}" class="btn-secondary-outline">Histórico completo</a>
                </div>
                <div class="wa-people" id="waPeople">
                    @foreach ($gestantes as $gestante)
                        @php
                            $preview = trim((string) ($gestante->ultima_mensagem ?? ''));
                        @endphp
                        <button type="button"
                                class="wa-person{{ $selecionada && $selecionada->id === $gestante->id ? ' is-active' : '' }}"
                                data-id="{{ $gestante->id }}"
                                data-com-mensagem="{{ $preview !== '' ? '1' : '0' }}">
                            <strong>{{ $gestante->nome_exibicao }}</strong>
                            <small>{{ $gestante->telefone_formatado ?: 'Sem telefone' }}</small>
                            <span class="wa-person-preview">{{ $preview !== '' ? \Illuminate\Support\Str::limit($preview, 80) : 'Ainda não há conversa.' }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @include('partials.gestante-conversa', [
                'gestante' => $selecionada,
                'whatsappTemplates' => $whatsappTemplates,
                'whatsappPrompts' => $whatsappPrompts,
                'whatsappHabilitado' => $whatsappHabilitado,
                'whatsappEnvioPronto' => $whatsappEnvioPronto,
                'whatsappIaPausada' => $whatsappIaPausada,
                'whatsappIaPausadaAte' => $whatsappIaPausadaAte,
                'whatsappMenuOpcao' => $whatsappMenuOpcao,
            ])
        </div>

        <script type="application/json" id="waInboxDados">@json($lista)</script>
        <script>
            (function () {
                const listaEl = document.getElementById('waPeople');
                const dados = JSON.parse(document.getElementById('waInboxDados').textContent || '[]');
                const porId = {};
                const resumoUrl = @json(url('/api/gestante-whatsapp'));
                dados.forEach(function (item) { porId[String(item.id)] = item; });

                function limitar(texto) {
                    const s = String(texto || '').trim();
                    if (!s) return 'Ainda não há conversa.';
                    return s.length > 80 ? s.slice(0, 77) + '...' : s;
                }

                function botao(id) {
                    return listaEl.querySelector('.wa-person[data-id="' + id + '"]');
                }

                function selecionar(id) {
                    const item = porId[String(id)];
                    if (!item || !window.GestanteChat) return;
                    listaEl.querySelectorAll('.wa-person').forEach(function (el) {
                        el.classList.toggle('is-active', el.getAttribute('data-id') === String(id));
                    });
                    window.GestanteChat.abrir(item);
                }

                listaEl.addEventListener('click', function (e) {
                    const alvo = e.target.closest('.wa-person');
                    if (!alvo) return;
                    selecionar(alvo.getAttribute('data-id'));
                });

                function atualizarPreview(id, texto) {
                    const el = botao(id);
                    if (!el) return;
                    const preview = el.querySelector('.wa-person-preview');
                    const tem = String(texto || '').trim() !== '';
                    el.setAttribute('data-com-mensagem', tem ? '1' : '0');
                    if (preview) preview.textContent = limitar(texto);
                    if (porId[String(id)]) porId[String(id)].ultima_mensagem = texto || '';
                }

                async function atualizarLista() {
                    try {
                        const res = await fetch(resumoUrl + '?per_page=100&sort=ultima_data&direction=desc', {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) return;
                        const corpo = await res.json();
                        const linhas = corpo.data || [];
                        const idsApi = [];
                        linhas.forEach(function (linha) {
                            const id = linha.gestante && linha.gestante.id;
                            if (!id || !porId[String(id)]) return;
                            idsApi.push(String(id));
                            atualizarPreview(id, linha.ultima_mensagem || '');
                        });
                        const botoes = Array.from(listaEl.querySelectorAll('.wa-person'));
                        const comApi = idsApi.map(botao).filter(Boolean);
                        const comFora = botoes.filter(function (el) {
                            return el.getAttribute('data-com-mensagem') === '1' && idsApi.indexOf(el.getAttribute('data-id')) === -1;
                        });
                        const sem = botoes.filter(function (el) {
                            return el.getAttribute('data-com-mensagem') !== '1';
                        });
                        comApi.concat(comFora, sem).forEach(function (el) { listaEl.appendChild(el); });
                    } catch (e) {
                        /* silencioso */
                    }
                }

                setInterval(function () {
                    if (!document.hidden) atualizarLista();
                }, 8000);
            })();
        </script>
    @endif
@endsection
