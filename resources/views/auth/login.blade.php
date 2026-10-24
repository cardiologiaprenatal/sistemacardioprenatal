@extends('layouts.auth')

@section('title', 'Login')

@section('content')

<style>
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
    }

    .login-card {
        background: var(--surface);
        border-radius: 28px;
        box-shadow: 0 16px 48px rgba(127, 12, 26, 0.12);
        padding: 54px 52px 46px;
        width: 100%;
        max-width: 480px;
        animation: cardIn 0.55s cubic-bezier(0.22, 0.97, 0.58, 1) both;
    }

    @keyframes cardIn {
        from { opacity: 0; transform: translateY(28px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .login-header {
        text-align: center;
        margin-bottom: 38px;
    }

    .login-logo {
        display: flex;
        justify-content: center;
        margin-bottom: 12px;
    }

    .login-sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .login-subtitle {
        color: var(--muted);
        font-size: 14px;
        line-height: 1.45;
        max-width: 22rem;
        margin: 0 auto;
    }

    .field { margin-bottom: 22px; }

    .field-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text);
    }

    .field-wrap { position: relative; }

    .field-input {
        width: 100%;
        padding: 13px 14px 13px 42px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        transition: all 0.2s;
        box-sizing: border-box;
        background: var(--surface);
        color: var(--text);
    }

    .field-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3.5px rgba(192, 57, 43, 0.12);
    }

    .field-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        width: 18px;
    }

    .btn-primary {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, var(--primary), var(--accent-mid));
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s;
    }

    .btn-primary:active { transform: scale(0.98); }
    .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

    .btn-spinner {
        display: none;
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    .loading .btn-spinner { display: block; }

    @keyframes spin { to { transform: rotate(360deg); } }

    .field-error {
        color: var(--danger);
        font-size: 12px;
        margin-top: 5px;
    }

    .btn-eye {
        position: absolute;
        right: 13px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--muted);
    }

    .login-register {
        text-align: center;
        margin-top: 24px;
        color: var(--muted);
        font-size: 14px;
    }

    .login-register a {
        color: var(--accent);
        font-weight: 600;
        text-decoration: none;
    }

    .login-forgot {
        text-align: right;
        margin: -6px 0 18px;
    }
    .login-forgot a {
        font-size: 13px;
        font-weight: 600;
        color: var(--accent);
        text-decoration: none;
    }

    @media (max-width: 520px) {
        .login-card {
            padding: 28px 18px;
            max-width: calc(100vw - 24px);
            border-radius: 22px;
        }
    }
</style>

<div class="login-card">
    <div class="login-header">
        <div class="login-logo">
            @include('partials.brand-logo', ['variant' => 'auth'])
        </div>
        <h1 class="login-sr-only">Cardioprenatal</h1>
        <p class="login-subtitle">
            <strong style="color: var(--primary); font-weight: 600;">Acesso da equipe</strong>.
            Rastreio de cardiopatias congênitas em gestantes diabéticas. Entre com seu
            <strong style="color: var(--text); font-weight: 600;">CRM</strong> e senha.
        </p>
    </div>

    <form method="POST" id="loginForm">
        @csrf

        <div class="field">
            <label class="field-label" for="crm">CRM</label>
            <div class="field-wrap">
                <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path d="M16 2v4M8 2v4M3 10h18M5 20h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z"/>
                </svg>
                <input type="text" name="crm" id="crm" value="{{ old('crm') }}" class="field-input" placeholder="Digite seu CRM (ex.: SP-123456)" maxlength="23" autocomplete="username">
            </div>
            @error('crm') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field-label" for="password">Senha</label>
            <div class="field-wrap">
                <svg class="field-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <input type="password" name="password" id="password" class="field-input" placeholder="Sua senha" autocomplete="current-password">
                <button type="button" class="btn-eye" id="togglePassword" aria-label="Mostrar senha">
                    <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="login-forgot">
            <a href="{{ route('password.request') }}">Esqueci minha senha</a>
        </div>

        <button type="submit" class="btn-primary" id="submitBtn">
            <span class="btn-spinner"></span>
            <span class="btn-label">Entrar</span>
        </button>

        <div class="login-register">
            Não tem conta?
            <a href="{{ route('users.create') }}">Cadastre-se</a>
        </div>
    </form>
</div>

@include('partials.form-masks')

@if (session('success'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Noty === 'undefined') return;
        new Noty({
            type: 'success',
            layout: 'topRight',
            text: @json(session('success')),
            timeout: 4500,
        }).show();
    });
</script>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const crm = document.getElementById('crm');
        if (crm) {
            maskCRMInput(crm);
            crm.addEventListener('input', function () { maskCRMInput(this); });
        }
    });

    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    toggleBtn.addEventListener('click', () => {
        const isPass = passwordInput.type === 'password';
        passwordInput.type = isPass ? 'text' : 'password';
        eyeIcon.style.opacity = isPass ? '0.5' : '1';
    });

    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');

    const attemptError = 'Não foi possível concluir a tentativa. Tente novamente.';

    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function applyCsrf(token) {
        if (!token) return;
        const input = loginForm.querySelector('input[name="_token"]');
        if (input) input.value = token;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
    }

    async function refreshCsrf() {
        const response = await fetch('{{ url('/login') }}', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html' },
        });
        const html = await response.text();
        const match = html.match(/name="_token" value="([^"]+)"/)
            || html.match(/name="csrf-token" content="([^"]+)"/);
        applyCsrf(match ? match[1] : '');
    }

    function postLogin() {
        const formToken = loginForm.querySelector('input[name="_token"]')?.value
            || document.querySelector('meta[name="csrf-token"]')?.content
            || '';
        const xsrfCookie = readCookie('XSRF-TOKEN');
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (formToken) {
            headers['X-CSRF-TOKEN'] = formToken;
        }
        if (xsrfCookie) {
            headers['X-XSRF-TOKEN'] = xsrfCookie;
        }

        return fetch('{{ url('/login') }}', {
            method: 'POST',
            headers,
            body: new FormData(loginForm),
            credentials: 'same-origin',
        });
    }

    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        const crmEl = document.getElementById('crm');
        if (crmEl) {
            crmEl.value = normalizeCRMValue(crmEl.value);
        }

        try {
            let response = await postLogin();
            if (response.status === 419) {
                await refreshCsrf();
                response = await postLogin();
            }

            const raw = await response.text();
            if (response.status === 419) {
                throw { message: attemptError };
            }

            let data = null;
            try {
                data = raw ? JSON.parse(raw) : null;
            } catch (_) {
                throw { message: attemptError };
            }

            if (!response.ok) {
                throw data || { message: attemptError };
            }

            new Noty({
                type: 'success',
                layout: 'topRight',
                text: 'Acesso autorizado! Redirecionando...',
                timeout: 2000,
            }).show();

            setTimeout(() => { window.location.href = (data.redirect || '/dashboard'); }, 2000);
        } catch (error) {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;

            let msg = error.error || error.message || 'Erro inesperado ao tentar login';
            if (error.errors) {
                msg = Object.values(error.errors).flat()[0];
            }

            new Noty({
                type: 'error',
                layout: 'topRight',
                text: msg,
                timeout: 4000,
            }).show();
        }
    });
</script>

@endsection
