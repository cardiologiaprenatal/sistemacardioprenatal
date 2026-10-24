@php
    $chatMensagens = $gestante->gestanteWhatsapps
        ->sortBy('id')
        ->values()
        ->map(fn ($m) => [
            'id' => $m->id,
            'mensagem' => $m->mensagem,
            'tipo' => $m->tipo,
            'created_at' => $m->created_at?->toIso8601String(),
        ]);
    $envioLiberado = isset($whatsappEnvioPronto)
        ? ! empty($whatsappEnvioPronto)
        : ! empty($whatsappHabilitado);
    $podeEnviar = ! empty($gestante->telefone) && $envioLiberado;
    $placeholderEnvio = ! $envioLiberado
        ? 'O canal não está pronto'
        : ($podeEnviar ? 'Mensagem' : 'Sem telefone cadastrado');
    $iniciais = collect(preg_split('/\s+/', trim((string) $gestante->nome_exibicao) ?: 'G'))
        ->filter()
        ->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('');
@endphp

<style>
    .wa-window {
        display: flex;
        flex-direction: column;
        height: min(72vh, 640px);
        min-height: 460px;
        padding: 0 !important;
        overflow: hidden;
        scroll-margin-top: 96px;
    }
    .wa-topbar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: linear-gradient(135deg, var(--primary) 0%, #a01020 100%);
        color: #fff;
        flex-shrink: 0;
    }
    .wa-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: rgba(255,255,255,0.18);
        display: grid;
        place-items: center;
        font-weight: 700;
        font-size: 14px;
        letter-spacing: 0.04em;
        flex-shrink: 0;
    }
    .wa-topbar-meta { min-width: 0; flex: 1; }
    .wa-topbar-meta strong {
        display: block;
        font-size: 16px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wa-topbar-meta span {
        display: block;
        font-size: 12px;
        opacity: 0.85;
        margin-top: 2px;
    }
    .wa-badge {
        flex-shrink: 0;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255,255,255,0.16);
    }
    .wa-badge.is-humano { background: #fff; color: var(--primary); }
    .wa-thread {
        flex: 1;
        overflow-y: auto;
        padding: 16px 14px 20px;
        background-color: #f3e6e4;
        background-image:
            radial-gradient(rgba(127,12,26,0.05) 1px, transparent 1px),
            linear-gradient(180deg, #f7ecea 0%, #efe0dd 100%);
        background-size: 18px 18px, 100% 100%;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .wa-day {
        align-self: center;
        margin: 10px 0 8px;
        padding: 4px 12px;
        border-radius: 999px;
        background: rgba(255,255,255,0.82);
        color: #6b4a4a;
        font-size: 11px;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(127,12,26,0.08);
    }
    .wa-row { display: flex; margin: 2px 0; }
    .wa-row--in { justify-content: flex-start; }
    .wa-row--out { justify-content: flex-end; }
    .wa-legend {
        display: flex;
        gap: 14px;
        padding: 8px 16px;
        background: #f6edec;
        color: #6b4a4a;
        font-size: 11px;
        font-weight: 600;
        flex-shrink: 0;
    }
    .wa-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .wa-legend i {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .wa-legend i.in { background: #fff; border: 1px solid rgba(127,12,26,0.25); }
    .wa-legend i.out { background: #7f0c1a; }
    .wa-col {
        max-width: min(78%, 440px);
        display: flex;
        flex-direction: column;
    }
    .wa-row--out .wa-col { align-items: flex-end; }
    .wa-sender {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b4a4a;
        margin: 6px 8px 2px;
    }
    .wa-row--out .wa-sender { color: #7f0c1a; }
    .wa-meta {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 6px;
        margin-top: 4px;
        font-size: 11px;
        opacity: 0.8;
        font-variant-numeric: tabular-nums;
    }
    .wa-pill {
        padding: 1px 6px;
        border-radius: 999px;
        background: rgba(255,255,255,0.18);
        font-size: 10px;
        letter-spacing: 0.02em;
    }
    .wa-row--in .wa-pill { background: rgba(127,12,26,0.08); }
    .wa-bubble {
        max-width: 100%;
        padding: 8px 10px 6px 12px;
        line-height: 1.45;
        font-size: 15px;
        color: #1c1414;
        white-space: pre-wrap;
        word-break: break-word;
        box-shadow: 0 1px 1px rgba(0,0,0,0.08);
        position: relative;
    }
    .wa-row--in .wa-bubble {
        background: #fff;
        border-radius: 4px 16px 16px 16px;
    }
    .wa-row--out .wa-bubble {
        background: #7f0c1a;
        color: #fff;
        border-radius: 16px 4px 16px 16px;
    }
    .wa-time {
        display: block;
        text-align: right;
        font-size: 11px;
        margin-top: 4px;
        opacity: 0.72;
        font-variant-numeric: tabular-nums;
    }
    .wa-empty {
        margin: auto;
        text-align: center;
        max-width: 260px;
        padding: 18px 16px;
        border-radius: 16px;
        background: rgba(255,255,255,0.78);
        color: #6b4a4a;
        font-size: 14px;
        line-height: 1.5;
    }
    .wa-composer {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 10px 12px 12px;
        background: #f6edec;
        border-top: 1px solid rgba(127,12,26,0.08);
        flex-shrink: 0;
    }
    .wa-composer select,
    .wa-composer textarea {
        border: 0;
        outline: none;
        font-family: inherit;
    }
    .wa-composer-select {
        max-width: 132px;
        height: 42px;
        padding: 0 10px;
        border-radius: 21px;
        background: #fff;
        color: var(--text);
        font-size: 12px;
        flex-shrink: 0;
    }
    .wa-composer-input {
        flex: 1;
        min-height: 42px;
        max-height: 120px;
        padding: 11px 16px;
        border-radius: 22px;
        background: #fff;
        font-size: 15px;
        line-height: 1.35;
        resize: none;
        color: var(--text);
    }
    .wa-send {
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 50%;
        background: var(--primary);
        color: #fff;
        cursor: pointer;
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }
    .wa-send:disabled { opacity: 0.45; cursor: not-allowed; }
    .wa-send svg { display: block; }
    .wa-status {
        padding: 0 16px 10px;
        margin: 0;
        font-size: 12px;
        color: var(--muted);
        background: #f6edec;
        min-height: 0;
    }
    .wa-status.is-error { color: var(--primary); }
    .wa-status.is-ok { color: #1e8449; }
    @media (max-width: 640px) {
        .wa-window { height: min(78vh, 580px); }
        .wa-composer-select { display: none; }
        .wa-col { max-width: 88%; }
    }
</style>

<div class="main-card wa-window" id="gestante-conversa"
     data-send-url="{{ route('gestantes.whatsapp', $gestante) }}"
     data-poll-url="{{ url('/api/gestante-whatsapp/'.$gestante->id) }}">
    <div class="wa-topbar">
        <div class="wa-avatar" aria-hidden="true">{{ $iniciais ?: 'G' }}</div>
        <div class="wa-topbar-meta">
            <strong>{{ $gestante->nome_exibicao }}</strong>
            <span>
                @if ($gestante->telefone)
                    {{ $gestante->telefone_formatado }} · WhatsApp
                @else
                    Sem telefone cadastrado
                @endif
            </span>
        </div>
        <div class="wa-badge {{ (! empty($whatsappIaPausada) || in_array($whatsappMenuOpcao ?? null, ['atendente', 'medico'], true)) ? 'is-humano' : '' }}" id="waIaBadge">
            @if (($whatsappMenuOpcao ?? null) === 'medico' || (! empty($whatsappIaPausada) && ($whatsappMenuOpcao ?? null) === 'medico'))
                Médico · IA pausada{{ $whatsappIaPausadaAte ? ' até '.$whatsappIaPausadaAte : '' }}
            @elseif (! empty($whatsappIaPausada) || ($whatsappMenuOpcao ?? null) === 'atendente')
                Atendente · IA pausada{{ $whatsappIaPausadaAte ? ' até '.$whatsappIaPausadaAte : '' }}
            @elseif (($whatsappMenuOpcao ?? null) === 'exames')
                Exames e consultas · IA
            @else
                Assistente automático
            @endif
        </div>
    </div>
    <div class="wa-legend" aria-hidden="true">
        <span><i class="in"></i> Paciente</span>
        <span><i class="out"></i> Equipe ou sistema</span>
    </div>

    <div class="wa-thread" id="gestanteChatThread" aria-live="polite"></div>

    <form class="wa-composer" id="formGestanteChat">
        @csrf
        <select id="chatTipo" class="wa-composer-select" title="Inserir modelo" @disabled(! $podeEnviar)>
            <option value="personalizada">Livre</option>
            @foreach (($whatsappTemplates ?? []) as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <textarea id="chatMensagem"
                  class="wa-composer-input"
                  rows="1"
                  maxlength="4000"
                  required
                  @disabled(! $podeEnviar)
                  placeholder="{{ $placeholderEnvio }}"></textarea>
        <button type="submit" class="wa-send" @disabled(! $podeEnviar) title="Enviar" aria-label="Enviar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
        </button>
    </form>
    <p class="wa-status" id="chatStatus"></p>
</div>

<script>
    (function () {
        const thread = document.getElementById('gestanteChatThread');
        const form = document.getElementById('formGestanteChat');
        const tipo = document.getElementById('chatTipo');
        const campo = document.getElementById('chatMensagem');
        const status = document.getElementById('chatStatus');
        let prompts = @json($whatsappPrompts ?? []);
        let sendUrl = @json(route('gestantes.whatsapp', $gestante));
        let pollUrl = @json(url('/api/gestante-whatsapp/'.$gestante->id));
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        let canalPronto = @json($envioLiberado);
        let podeEnviar = @json($podeEnviar);
        let mensagens = @json($chatMensagens);
        let gera = 0;
        let lastId = mensagens.length ? mensagens[mensagens.length - 1].id : 0;
        let sending = false;

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function formatarHora(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        function formatarDataHora(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '';
            const dia = rotuloDia(iso);
            const hora = formatarHora(iso);
            if (dia === 'Hoje' || dia === 'Ontem') {
                return dia + ' · ' + hora;
            }
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' · ' + hora;
        }

        function labelRemetente(m) {
            if (m.remetente_label) return m.remetente_label;
            if (m.tipo === 'entrada') return 'Paciente';
            const cat = m.categoria || '';
            if (cat === 'lembrete_consulta' || cat === 'lembrete_retorno' || cat === 'confirmacao_consulta') {
                return 'Sistema';
            }
            return 'Equipe';
        }

        function chaveDia(valor) {
            const d = valor instanceof Date ? valor : new Date(valor);
            if (Number.isNaN(d.getTime())) return '';
            return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
        }

        function rotuloDia(iso) {
            const d = new Date(iso);
            const hoje = new Date();
            const ontem = new Date();
            ontem.setDate(hoje.getDate() - 1);
            if (chaveDia(d) === chaveDia(hoje)) return 'Hoje';
            if (chaveDia(d) === chaveDia(ontem)) return 'Ontem';
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function setStatus(texto, kind) {
            status.textContent = texto || '';
            status.classList.remove('is-error', 'is-ok');
            if (kind) status.classList.add(kind);
        }

        function atualizarBadgeIa(pausada, ate, menuOpcao) {
            const badge = document.getElementById('waIaBadge');
            if (!badge) return;
            const humano = !!pausada || menuOpcao === 'atendente' || menuOpcao === 'medico';
            if (menuOpcao === 'medico' || (pausada && menuOpcao === 'medico')) {
                badge.classList.add('is-humano');
                badge.textContent = 'Médico · IA pausada' + (ate ? ' até ' + ate : '');
            } else if (humano) {
                badge.classList.add('is-humano');
                badge.textContent = 'Atendente · IA pausada' + (ate ? ' até ' + ate : '');
            } else if (menuOpcao === 'exames') {
                badge.classList.remove('is-humano');
                badge.textContent = 'Exames e consultas · IA';
            } else {
                badge.classList.remove('is-humano');
                badge.textContent = 'Assistente automático';
            }
        }

        function ajustarAltura() {
            campo.style.height = '42px';
            campo.style.height = Math.min(campo.scrollHeight, 120) + 'px';
        }

        function render(lista, forcarFim) {
            const distancia = thread.scrollHeight - thread.scrollTop - thread.clientHeight;
            const noFim = !!forcarFim || distancia < 90;
            const scrollAnterior = thread.scrollTop;

            if (!lista || !lista.length) {
                thread.innerHTML = '<p class="wa-empty">Nenhuma mensagem ainda. Escreva abaixo para começar a conversa.</p>';
                return;
            }

            let html = '';
            let diaAtual = '';
            lista.forEach(function (m) {
                const dia = chaveDia(m.created_at);
                if (dia && dia !== diaAtual) {
                    diaAtual = dia;
                    html += '<div class="wa-day">' + escapeHtml(rotuloDia(m.created_at)) + '</div>';
                }
                const entrada = m.tipo === 'entrada';
                const remetente = labelRemetente(m);
                html += '<div class="wa-row ' + (entrada ? 'wa-row--in' : 'wa-row--out') + '">';
                html += '<div class="wa-col">';
                html += '<span class="wa-sender">' + escapeHtml(remetente) + '</span>';
                html += '<div class="wa-bubble">';
                html += escapeHtml(m.mensagem || '');
                html += '<span class="wa-meta">';
                if (m.categoria && m.categoria !== 'conversa' && m.categoria_label) {
                    html += '<span class="wa-pill">' + escapeHtml(m.categoria_label) + '</span>';
                }
                html += '<span>' + escapeHtml(formatarDataHora(m.created_at)) + '</span>';
                html += '</span>';
                html += '</div></div></div>';
            });
            thread.innerHTML = html;
            thread.scrollTop = noFim ? thread.scrollHeight : scrollAnterior;
        }

        function aplicarComposer() {
            const bloquear = !podeEnviar;
            tipo.disabled = bloquear;
            campo.disabled = bloquear;
            campo.required = podeEnviar;
            form.querySelector('.wa-send').disabled = bloquear;
            campo.placeholder = !canalPronto
                ? 'O canal não está pronto'
                : (podeEnviar ? 'Mensagem' : 'Sem telefone cadastrado');
        }

        function iniciaisDe(nome) {
            return String(nome || 'G').trim().split(/\s+/).filter(Boolean).slice(0, 2)
                .map(function (p) { return p.charAt(0).toUpperCase(); }).join('') || 'G';
        }

        async function carregar(forcarFim) {
            const atual = gera;
            const url = pollUrl;
            try {
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok || atual !== gera) return;
                const data = await res.json();
                if (atual !== gera) return;
                const lista = data.mensagens || [];
                const novoId = lista.length ? lista[lista.length - 1].id : 0;
                if (forcarFim || novoId !== lastId || lista.length !== mensagens.length) {
                    mensagens = lista;
                    lastId = novoId;
                    render(mensagens, !!forcarFim);
                }
            } catch (e) {
                /* silencioso */
            }
        }

        function abrir(item) {
            if (!item || !item.poll_url) return;
            gera += 1;
            pollUrl = item.poll_url;
            sendUrl = item.send_url;
            prompts = item.prompts || {};
            const temTelefone = !!(item.telefone && String(item.telefone).trim() !== '');
            podeEnviar = !!canalPronto && temTelefone;
            const nome = item.nome || 'Gestante';
            const avatar = document.querySelector('#gestante-conversa .wa-avatar');
            const titulo = document.querySelector('#gestante-conversa .wa-topbar-meta strong');
            const sub = document.querySelector('#gestante-conversa .wa-topbar-meta span');
            if (avatar) avatar.textContent = iniciaisDe(nome);
            if (titulo) titulo.textContent = nome;
            if (sub) {
                sub.textContent = temTelefone
                    ? ((item.telefone_formatado || item.telefone) + ' · WhatsApp')
                    : 'Sem telefone cadastrado';
            }
            atualizarBadgeIa(!!item.ia_pausada, item.ia_ate || null, item.menu_opcao || null);
            campo.value = '';
            tipo.value = 'personalizada';
            ajustarAltura();
            setStatus('');
            aplicarComposer();
            mensagens = [];
            lastId = 0;
            render([], true);
            carregar(true);
        }

        window.GestanteChat = { abrir: abrir };

        tipo.addEventListener('change', function () {
            if (tipo.value === 'personalizada') return;
            campo.value = prompts[tipo.value] || '';
            ajustarAltura();
            campo.focus();
        });

        campo.addEventListener('input', ajustarAltura);
        campo.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!podeEnviar || sending) return;

            const texto = (campo.value || '').trim();
            if (texto.length < 3) {
                setStatus('A mensagem deve ter ao menos 3 caracteres.', 'is-error');
                return;
            }

            sending = true;
            setStatus('Enviando…');
            form.querySelector('.wa-send').disabled = true;

            try {
                const res = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        tipo: tipo.value || 'personalizada',
                        mensagem: texto,
                    }),
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || data.ok === false) {
                    setStatus(data.message || 'Não foi possível enviar a mensagem.', 'is-error');
                    return;
                }
                campo.value = '';
                ajustarAltura();
                tipo.value = 'personalizada';
                setStatus(data.message || '', data.ia_pausada ? 'is-ok' : '');
                atualizarBadgeIa(data.ia_pausada, data.ia_pausada_ate, data.menu_opcao);
                thread.scrollTop = thread.scrollHeight;
                await carregar(true);
            } catch (err) {
                setStatus('Erro de conexão ao enviar.', 'is-error');
            } finally {
                sending = false;
                if (podeEnviar) {
                    form.querySelector('.wa-send').disabled = false;
                }
            }
        });

        render(mensagens, true);
        setInterval(function () {
            if (!document.hidden) carregar();
        }, 8000);
    })();
</script>
