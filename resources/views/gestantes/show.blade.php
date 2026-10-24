@extends('layouts.app')

@section('title', $gestante->nome_exibicao.'. Cardioprenatal')

@section('content')
@php
    use App\Models\User;

    $podeAnalises = auth()->user()?->can(User::ABILITY_ANALISES);
    $podeGerenciarGestantes = auth()->user()?->can(User::ABILITY_GESTANTES_MANAGE);
    $podeVerAgenda = auth()->user()?->can(User::ABILITY_CONSULTAS_AGENDA);
    $podeConsultas = auth()->user()?->can(User::ABILITY_CONSULTAS_MANAGE);
    $podeDirecionarAtendimento = $podeDirecionarAtendimento ?? (bool) auth()->user()?->canDirecionarAtendimentoGestante();
    $podeEnviarEmail = (bool) auth()->user()?->canAtenderWhatsappGestante()
        && ($atendimentoPersonalizadoLiberado ?? $gestante->atendimentoPersonalizadoLiberado())
        && ! empty($gestante->email);
    $temGraficosIa = session('resultado_analise_ia') && is_array(session('resultado_analise_ia')) && ! empty(session('resultado_analise_ia')['imagens']);
@endphp
<style>
    .gestante-identidade { padding: 28px 32px 26px; }
    .gestante-identidade-texto {
        min-width: 0;
        flex: 1 1 240px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .gestante-identidade-texto .page-title {
        margin: 0;
        max-width: 100%;
        line-height: 1.25;
    }
    .gestante-identidade-texto p { margin: 0; }
    .gestante-area-nav {
        display: flex; flex-wrap: wrap; gap: 4px; margin: 8px 0 4px;
        padding: 6px; border: 1px solid var(--border); border-radius: 12px;
        background: var(--surface);
    }
    .gestante-area-nav a {
        flex: 1 1 auto; min-width: 7rem; text-align: center;
        font-size: 13px; font-weight: 600; text-decoration: none;
        color: var(--muted); padding: 12px 16px; border-radius: 10px;
    }
    .gestante-area-nav a:hover { color: var(--primary); background: var(--accent-light); }
    .gestante-area {
        scroll-margin-top: 96px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .gestante-area-kicker {
        font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--muted); margin: 0;
    }
    .gestante-area .form-field-grid { gap: 22px 32px; }
    @media (max-width: 640px) {
        .gestante-identidade { padding: 22px 18px 20px; }
    }
</style>
<div class="page-stack">

        @if (session('success'))
            <div class="main-card" role="status" style="margin-bottom: 0; border-left: 4px solid var(--success); background: rgba(39, 174, 96, 0.08);">
                <p style="margin: 0; font-size: 15px; color: var(--text);">{{ session('success') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="main-card" role="alert" style="margin-bottom: 0; border-left: 4px solid #c0392b; background: rgba(192, 57, 43, 0.08);">
                @foreach ($errors->all() as $erro)
                    <p style="margin: 0; font-size: 15px; color: var(--text);">{{ $erro }}</p>
                @endforeach
            </div>
        @endif

        <div class="main-card gestante-identidade" style="margin-bottom: 0;">
            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 24px;">
                <div class="gestante-identidade-texto">
                    <p style="font-size: 12px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted);">Gestante</p>
                    <h2 class="page-title">
                        {{ $gestante->nome_exibicao }}
                    </h2>
                    <p style="font-size: 14px; color: var(--muted);">
                        Cadastro <strong style="color: var(--text);">#{{ $gestante->id }}</strong>
                    </p>
                </div>
                @if ($podeEnviarEmail || $podeGerenciarGestantes)
                <div class="gestante-header-actions">
                    @if ($podeEnviarEmail)
                    <button type="button"
                            class="btn-secondary-outline"
                            id="btnAbrirEmail"
                            @disabled(empty($emailHabilitado))
                            title="{{ ! empty($emailHabilitado) ? 'Enviar mensagem por e-mail' : 'Envio de e-mail em espera no momento' }}">
                        Enviar e-mail
                    </button>
                    @endif
                    @if ($podeGerenciarGestantes)
                    <a href="{{ route('gestantes.edit', $gestante) }}"
                       style="display: inline-flex; align-items: center; padding: 12px 20px; border-radius: 12px; border: 1px solid var(--border); color: var(--primary); text-decoration: none; font-weight: 600;">
                        Editar dados
                    </a>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <div id="painel-consulta">
            @include('partials.gestante-aba-consulta', [
                'gestante' => $gestante,
                'consultaAtual' => $consultaAtual ?? null,
                'historicoConsultas' => $historicoConsultas ?? collect(),
                'podeConsultas' => $podeConsultas,
                'podeAnalises' => $podeAnalises,
                'podeVerAgenda' => $podeVerAgenda,
                'podeGerenciarGestantes' => $podeGerenciarGestantes,
                'podeDirecionarAtendimento' => $podeDirecionarAtendimento ?? false,
                'atendimentoPersonalizadoLiberado' => $atendimentoPersonalizadoLiberado ?? false,
                'encaminhadaParaFilaEco' => $encaminhadaParaFilaEco ?? false,
                'temGraficosIa' => $temGraficosIa,
            ])
        </div>

</div>

@if ($podeEnviarEmail)
    <div id="modalEmail"
         class="app-modal-overlay"
         style="background: rgba(28, 26, 26, 0.45); backdrop-filter: blur(4px);"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modalEmailTitulo"
         aria-hidden="true">
        <div class="main-card app-modal-panel app-modal-panel--lg" style="padding: 28px;" onclick="event.stopPropagation()">
            <h2 id="modalEmailTitulo" style="font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--primary); margin-bottom: 8px;">
                Enviar e-mail
            </h2>
            <p style="color: var(--muted); margin-bottom: 20px; line-height: 1.5; font-size: 14px;">
                Para {{ $gestante->nome_exibicao }} · {{ $gestante->email }}
            </p>
            @if (! empty($emailLogMailer))
                <p style="color: var(--muted); margin-bottom: 16px; line-height: 1.5; font-size: 13px; padding: 10px 12px; border-radius: 10px; background: rgba(230, 126, 34, 0.08);">
                    O mailer atual é <code>log</code>: o envio será gravado em <code>storage/logs</code>, não na caixa de entrada. Configure SMTP no <code>.env</code> para entrega real.
                </p>
            @endif

            <form method="POST" action="{{ route('gestantes.email', $gestante) }}" id="formEmail">
                @csrf
                <div class="form-field" style="margin-bottom: 16px;">
                    <label class="form-label" for="emailTipo">Template (conforme esta gestante)</label>
                    <select name="tipo" id="emailTipo" class="form-input" required>
                        @foreach (($emailTemplates ?? []) as $key => $label)
                            <option value="{{ $key }}" @selected(($emailTemplateSugerido ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                        <option value="personalizada">Personalizada (escrever do zero)</option>
                    </select>
                </div>
                <div class="form-field" style="margin-bottom: 16px;">
                    <label class="form-label" for="emailAssunto">Assunto</label>
                    <input type="text"
                           name="assunto"
                           id="emailAssunto"
                           class="form-input"
                           required
                           maxlength="255">
                </div>
                <div class="form-field" style="margin-bottom: 20px;">
                    <label class="form-label" for="emailMensagem">Mensagem</label>
                    <textarea name="mensagem"
                              id="emailMensagem"
                              class="form-input"
                              rows="10"
                              required
                              maxlength="4000"
                              style="resize: vertical; min-height: 180px; font-family: inherit; line-height: 1.5;"></textarea>
                    <p style="margin: 8px 0 0; font-size: 12px; color: var(--muted);">Você pode editar antes de enviar.</p>
                </div>
                <div class="app-modal-actions">
                    <button type="button" id="btnFecharEmail"
                            style="padding: 10px 18px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface); cursor: pointer; font-weight: 600; color: var(--text);">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary-custom">
                        Enviar agora
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const prompts = @json($emailPrompts ?? []);
            const assuntos = @json($emailAssuntos ?? []);
            const modal = document.getElementById('modalEmail');
            const tipo = document.getElementById('emailTipo');
            const assunto = document.getElementById('emailAssunto');
            const mensagem = document.getElementById('emailMensagem');
            const btnAbrir = document.getElementById('btnAbrirEmail');
            const btnFechar = document.getElementById('btnFecharEmail');

            function aplicarPrompt() {
                const key = tipo.value;
                if (key === 'personalizada') {
                    if (!mensagem.dataset.touched) {
                        mensagem.value = '';
                    }
                    if (!assunto.dataset.touched) {
                        assunto.value = 'Mensagem do Cardioprenatal';
                    }
                    return;
                }
                mensagem.value = prompts[key] || '';
                assunto.value = assuntos[key] || 'Mensagem do Cardioprenatal';
                mensagem.dataset.touched = '';
                assunto.dataset.touched = '';
            }

            function abrir() {
                aplicarPrompt();
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                mensagem.focus();
            }

            function fechar() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            if (btnAbrir) btnAbrir.addEventListener('click', abrir);
            if (btnFechar) btnFechar.addEventListener('click', fechar);
            tipo.addEventListener('change', function () {
                mensagem.dataset.touched = '';
                assunto.dataset.touched = '';
                aplicarPrompt();
            });
            mensagem.addEventListener('input', function () {
                mensagem.dataset.touched = '1';
            });
            assunto.addEventListener('input', function () {
                assunto.dataset.touched = '1';
            });
            modal.addEventListener('click', function (e) {
                if (e.target === modal) fechar();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) fechar();
            });

            aplicarPrompt();
        })();
    </script>
@endif
@endsection
