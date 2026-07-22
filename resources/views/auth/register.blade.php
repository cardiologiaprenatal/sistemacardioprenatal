@extends('layouts.auth')

@section('title', 'Cadastro')

@section('subtitle', 'Cadastro do médico')

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap');

    :root {
        --primary:      #7f0c1a;
        --accent:       #c0392b;
        --accent-mid:   #e74c3c;
        --accent-light: #fdf0f0;
        --success:      #27ae60;
        --danger:       #c0392b;
        --text:         #1c1a1a;
        --muted:        #8a6f6f;
        --border:       #f0d5d5;
        --surface:      #ffffff;
        --bg:           #fdf5f5;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DM Sans', sans-serif;
        background: var(--bg);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow-y: auto;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background:
            radial-gradient(ellipse 80% 60% at 75% -10%, rgba(192,57,43,.15) 0%, transparent 70%),
            radial-gradient(ellipse 60% 50% at -10% 85%, rgba(127,12,26,.12) 0%, transparent 65%);
        pointer-events: none;
        z-index: 0;
    }

    .deco-circle {
        position: fixed;
        border-radius: 50%;
        pointer-events: none;
        z-index: 0;
    }
    .deco-circle-1 { width: 380px; height: 380px; border: 1.5px solid rgba(192,57,43,.13); top: -120px; right: -90px; }
    .deco-circle-2 { width: 220px; height: 220px; border: 1.5px solid rgba(127,12,26,.09); bottom: 50px; left: -70px; }
    .deco-circle-3 { width: 100px; height: 100px; background: rgba(231,76,60,.06); top: 35%; left: 4%; border-radius: 50%; }

    /* Card */
    .register-card {
        position: relative;
        z-index: 1;
        background: var(--surface);
        border-radius: 28px;
        box-shadow:
            0 2px 4px rgba(127,12,26,.04),
            0 16px 48px rgba(127,12,26,.12),
            0 0 0 1px rgba(240,213,213,.8);
        padding: 48px 52px 42px;
        width: 100%;
        max-width: 480px;
        animation: cardIn .55s cubic-bezier(.22,.97,.58,1) both;
        margin: 32px 0;
    }

    @keyframes cardIn {
        from { opacity: 0; transform: translateY(28px) scale(.97); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Header */
    .register-header { text-align: center; margin-bottom: 32px; }

    .register-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 68px; height: 68px;
        background: linear-gradient(135deg, var(--primary), var(--accent-mid));
        border-radius: 20px;
        margin-bottom: 18px;
        box-shadow: 0 6px 20px rgba(192,57,43,.35);
        position: relative;
    }

    .register-logo::after {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 24px;
        border: 2px solid rgba(192,57,43,.3);
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: .6; }
        50%       { transform: scale(1.08); opacity: .15; }
    }

    .register-logo svg { width: 34px; height: 34px; color: #fff; filter: drop-shadow(0 2px 4px rgba(0,0,0,.2)); }

    .register-title {
        font-family: 'DM Serif Display', serif;
        font-size: 26px;
        color: var(--primary);
        letter-spacing: -.4px;
        line-height: 1.2;
    }

    .register-subtitle { font-size: 14px; color: var(--muted); margin-top: 6px; }

    .register-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--accent-light);
        border: 1px solid rgba(192,57,43,.18);
        color: var(--accent);
        font-size: 12px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 99px;
        margin-top: 10px;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .register-badge svg { width: 11px; height: 11px; }

    /* Grid 2 colunas */
    .fields-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0 18px;
    }
    .field-full { grid-column: 1 / -1; }

    /* Campo */
    .field { margin-bottom: 18px; animation: fieldIn .5s cubic-bezier(.22,.97,.58,1) both; }
    .field:nth-child(1) { animation-delay: .08s; }
    .field:nth-child(2) { animation-delay: .13s; }
    .field:nth-child(3) { animation-delay: .18s; }
    .field:nth-child(4) { animation-delay: .23s; }
    .field:nth-child(5) { animation-delay: .28s; }

    @keyframes fieldIn {
        from { opacity: 0; transform: translateX(-10px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .field-label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 7px; letter-spacing: .01em; }

    .field-wrap { position: relative; }

    .field-icon {
        position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
        color: var(--muted); width: 17px; height: 17px; pointer-events: none; transition: color .2s;
    }
    .field-wrap:focus-within .field-icon { color: var(--accent); }

    .field-input {
        width: 100%;
        padding: 12px 13px 12px 38px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        font-size: 14.5px;
        font-family: 'DM Sans', sans-serif;
        color: var(--text);
        background: #fffafa;
        outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .field-input:focus {
        border-color: var(--accent);
        background: var(--surface);
        box-shadow: 0 0 0 3.5px rgba(192,57,43,.12);
    }

    .field-input.is-error { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(192,57,43,.10); }

    select.field-input { appearance: none; cursor: pointer; }

    .select-arrow {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        pointer-events: none; color: var(--muted);
    }

    .field-error { font-size: 12px; color: var(--danger); margin-top: 5px; display: flex; align-items: center; gap: 4px; }

    /* Força da senha */
    .password-strength { margin-top: 7px; display: none; }
    .strength-bar { height: 4px; border-radius: 99px; background: var(--border); overflow: hidden; margin-bottom: 4px; }
    .strength-fill { height: 100%; border-radius: 99px; width: 0%; transition: width .3s, background .3s; }
    .strength-label { font-size: 11.5px; color: var(--muted); }

    /* Toggle senha */
    .btn-eye {
        position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
        background: none; border: none; cursor: pointer; color: var(--muted); padding: 2px; transition: color .2s; display: flex;
    }
    .btn-eye:hover { color: var(--accent); }

    /* Botão */
    .btn-primary {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; padding: 14px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--accent-mid) 100%);
        color: #fff; border: none; border-radius: 12px;
        font-size: 15.5px; font-weight: 600; font-family: 'DM Sans', sans-serif;
        cursor: pointer; box-shadow: 0 4px 18px rgba(192,57,43,.32);
        transition: opacity .2s, transform .15s, box-shadow .2s;
        margin-top: 6px;
        animation: fieldIn .5s .34s cubic-bezier(.22,.97,.58,1) both;
        letter-spacing: .01em;
    }

    .btn-primary:hover { opacity: .92; transform: translateY(-1px); box-shadow: 0 7px 24px rgba(192,57,43,.38); }
    .btn-primary:active { transform: translateY(0); }
    .btn-primary:disabled { opacity: .65; cursor: not-allowed; transform: none; }

    .btn-spinner {
        display: none; width: 17px; height: 17px;
        border: 2.5px solid rgba(255,255,255,.4); border-top-color: #fff;
        border-radius: 50%; animation: spin .7s linear infinite;
    }
    .btn-primary.loading .btn-spinner { display: block; }
    .btn-primary.loading .btn-label   { opacity: .7; }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* Divisor */
    .divider {
        display: flex; align-items: center; gap: 12px; margin: 22px 0;
        animation: fieldIn .5s .40s cubic-bezier(.22,.97,.58,1) both;
    }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .divider span { font-size: 12px; color: var(--muted); white-space: nowrap; font-weight: 500; }

    .login-row { text-align: center; animation: fieldIn .5s .46s cubic-bezier(.22,.97,.58,1) both; }
    .login-row p { font-size: 14px; color: var(--muted); }
    .link-login { color: var(--accent); font-weight: 600; text-decoration: none; transition: color .2s; }
    .link-login:hover { color: var(--primary); text-decoration: underline; }

    @media (max-width: 520px) {
        .register-card { padding: 36px 20px 30px; border-radius: 20px; }
        .fields-grid { grid-template-columns: 1fr; }
        .field-full { grid-column: 1; }
        .register-title { font-size: 21px; }
    }
</style>

<div class="deco-circle deco-circle-1"></div>
<div class="deco-circle deco-circle-2"></div>
<div class="deco-circle deco-circle-3"></div>

<div class="register-card">

    <div class="register-header">
        <div class="register-logo">
            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
        </div>
        <h1 class="register-title">Criar conta de médico</h1>
        <p class="register-subtitle">Preencha seus dados para acessar o sistema (CRM, nome e contato).</p>
        <span class="register-badge">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            Cardiologia
        </span>
    </div>

    <form method="POST" id="registerForm" novalidate>
        @csrf

        <div class="fields-grid">

            {{-- Nome completo --}}
            <div class="field field-full">
                <label class="field-label" for="nome">Nome completo</label>
                <div class="field-wrap">
                    <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    <input type="text" id="nome" name="nome" value="{{ old('nome') }}"
                        placeholder="Ex: Dr. João Silva" autocomplete="name"
                        class="field-input {{ $errors->has('nome') ? 'is-error' : '' }}">
                </div>
                @error('nome')
                    <p class="field-error">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- CRM --}}
            <div class="field">
                <label class="field-label" for="crm">CRM</label>
                <div class="field-wrap">
                    <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    <input type="text" id="crm" name="crm" value="{{ old('crm') }}"
                        placeholder="Digite seu CRM, com tracinho (ex.: SP-123456)" autocomplete="off" maxlength="23"
                        class="field-input {{ $errors->has('crm') ? 'is-error' : '' }}">
                </div>
                @error('crm')
                    <p class="field-error">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Telefone --}}
            <div class="field">
                <label class="field-label" for="telefone">Telefone</label>
                <div class="field-wrap">
                    <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.39 19a19.45 19.45 0 0 1-6-6A19.79 19.79 0 0 1 3.09 4.18 2 2 0 0 1 5.07 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L9.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <input type="tel" id="telefone" name="telefone" value="{{ old('telefone') }}"
                        placeholder="(11) 99999-9999" autocomplete="tel"
                        class="field-input {{ $errors->has('telefone') ? 'is-error' : '' }}">
                </div>
                @error('telefone')
                    <p class="field-error">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Senha --}}
            <div class="field field-full">
                <label class="field-label" for="password">Senha</label>
                <div class="field-wrap">
                    <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="password" name="password"
                        placeholder="Mínimo 8 caracteres" autocomplete="new-password"
                        class="field-input {{ $errors->has('password') ? 'is-error' : '' }}">
                    <button type="button" class="btn-eye" id="togglePassword" aria-label="Mostrar/ocultar senha">
                        <svg id="eyeIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <span class="strength-label" id="strengthLabel"></span>
                </div>
                @error('password')
                    <p class="field-error">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        {{ $message }}
                    </p>
                @enderror
            </div>

        </div>

        <button type="submit" class="btn-primary" id="submitBtn">
            <span class="btn-spinner"></span>
            <span class="btn-label">Criar conta</span>
        </button>
    </form>

    <div class="divider"><span>ou</span></div>

    <div class="login-row">
        <p>Já tem uma conta? <a href="{{ route('login') }}" class="link-login">Entrar</a></p>
    </div>

</div>

@include('partials.form-masks')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const crm = document.getElementById('crm');
        if (crm) {
            maskCRMInput(crm);
            crm.addEventListener('input', function () { maskCRMInput(this); });
        }
    });

    document.getElementById('telefone').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length >= 7) {
            v = v.length === 11
                ? v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')
                : v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        } else if (v.length >= 3) {
            v = v.replace(/(\d{2})(\d+)/, '($1) $2');
        }
        this.value = v;
    });

    const togglePassword = document.getElementById('togglePassword');
    const passwordInput  = document.getElementById('password');
    const eyeIcon        = document.getElementById('eyeIcon');
    const eyeOpen   = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    const eyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;

    togglePassword.addEventListener('click', () => {
        const show = passwordInput.type === 'password';
        passwordInput.type = show ? 'text' : 'password';
        eyeIcon.innerHTML  = show ? eyeClosed : eyeOpen;
    });

    passwordInput.addEventListener('input', function () {
        const val      = this.value;
        const strength = document.getElementById('passwordStrength');
        const fill     = document.getElementById('strengthFill');
        const label    = document.getElementById('strengthLabel');

        if (!val) { strength.style.display = 'none'; return; }
        strength.style.display = 'block';

        let score = 0;
        if (val.length >= 8)          score++;
        if (/[A-Z]/.test(val))        score++;
        if (/[0-9]/.test(val))        score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: '25%', color: '#e74c3c', text: 'Fraca' },
            { pct: '50%', color: '#e67e22', text: 'Razoável' },
            { pct: '75%', color: '#f1c40f', text: 'Boa' },
            { pct: '100%',color: '#27ae60', text: 'Forte' },
        ];
        const lvl = levels[score - 1] || levels[0];
        fill.style.width      = lvl.pct;
        fill.style.background = lvl.color;
        label.textContent     = `Senha ${lvl.text}`;
        label.style.color     = lvl.color;
    });

    const registerForm = document.getElementById('registerForm');
    const submitBtn    = document.getElementById('submitBtn');

    registerForm.addEventListener('submit', function (e) {
        e.preventDefault();

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        const crmEl = document.getElementById('crm');
        if (crmEl) {
            crmEl.value = normalizeCRMValue(crmEl.value);
        }

        const formData = new FormData(this);

        fetch(@json(route('users.store')), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
            credentials: 'same-origin',
        })
        .then(async response => {
            const raw = await response.text();
            let data = null;
            try {
                data = raw ? JSON.parse(raw) : null;
            } catch (_) {
                if (response.status === 419 || /Page Expired|CSRF/i.test(raw)) {
                    throw { message: 'Sessão expirada. Recarregue a página (F5) e tente novamente.' };
                }
                throw { message: `Resposta inválida do servidor (${response.status}). Recarregue a página.` };
            }

            if (!response.ok) throw data || { message: `Erro no servidor (${response.status})` };

            const msg = data.message || 'Conta criada com sucesso! Redirecionando para o login…';
            const next = (data.redirect || '/login');

            new Noty({
                type: 'success',
                layout: 'topRight',
                text: msg,
                timeout: 2200
            }).show();

            setTimeout(() => {
                window.location.href = next;
            }, 1800);
        })
        .catch(error => {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;

            if (error.errors) {
                Object.values(error.errors).forEach(messages => {
                    messages.forEach(msg => {
                        new Noty({ type: 'error', layout: 'topRight', text: msg, timeout: 3000 }).show();
                    });
                });
                return;
            }

            let msg = error.message || 'Erro ao criar conta';
            if (typeof msg === 'string' && msg.includes('Unexpected token')) {
                msg = 'Sessão expirada. Recarregue a página (F5) e tente novamente.';
            }

            new Noty({
                type: 'error',
                layout: 'topRight',
                text: msg,
                timeout: 4000
            }).show();
        });
    });
</script>

@endsection
