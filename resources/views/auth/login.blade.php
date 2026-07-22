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

    @media (prefers-color-scheme: dark) {
        :root {
            --surface:      #1e1e1e;
            --text:         #e0e0e0;
            --muted:        #a0a0a0;
            --border:       #333333;
            --accent-light: #2c0b0e;
        }

        .field-input {
            background-color: #262626;
            color: #fff;
        }

        .login-card {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.5);
        }
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
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 76px;
        height: 68px;
        padding: 0 18px;
        background: linear-gradient(135deg, var(--primary), var(--accent-mid));
        border-radius: 20px;
        margin-bottom: 20px;
        color: #fff;
    }

    .login-logo svg {
        flex-shrink: 0;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.12));
    }

    .login-logo .heart-brand {
        width: 32px;
        height: 32px;
        transform-origin: center;
        animation: loginHeartPulse 2.4s ease-in-out infinite;
    }

    .login-logo .icon-baby-brand {
        width: 30px;
        height: 30px;
        opacity: 0.98;
    }

    @keyframes loginHeartPulse {
        0%, 100% { transform: scale(1); }
        12% { transform: scale(1.1); }
        24% { transform: scale(1); }
        36% { transform: scale(1.06); }
        48% { transform: scale(1); }
    }

    @media (prefers-reduced-motion: reduce) {
        .login-logo .heart-brand { animation: none; }
    }

    .login-title {
        font-family: 'DM Serif Display', serif;
        font-size: 28px;
        color: var(--primary);
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

    @media (max-width: 520px) {
        .login-card {
            padding: 28px 18px;
            max-width: calc(100vw - 24px);
            border-radius: 22px;
        }

        .login-title {
            font-size: clamp(1.35rem, 5vw, 1.75rem);
        }
    }
</style>

<div class="login-card">
    <div class="login-header">
        <div class="login-logo" aria-hidden="true" title="Coração e bebê">
            <svg class="heart-brand" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </svg>
            <svg class="icon-baby-brand" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 4.75a3.15 3.15 0 1 0 0 6.3 3.15 3.15 0 0 0 0-6.3z"/>
                <path d="M12 12.2c-3.35 0-6.1 2.15-6.1 4.8V21h12.2v-4c0-2.65-2.75-4.8-6.1-4.8z"/>
            </svg>
        </div>
        <h1 class="login-title">Cardioprenatal</h1>
        <p class="login-subtitle">
            <strong style="color: var(--primary); font-weight: 600;">Acesso do médico</strong>
            — rastreio de cardiopatias congênitas no pré-natal. Entre com seu
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

    loginForm.addEventListener('submit', function (e) {
        e.preventDefault();

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        const crmEl = document.getElementById('crm');
        if (crmEl) {
            crmEl.value = normalizeCRMValue(crmEl.value);
        }

        fetch('{{ route('login') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(this),
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

            if (!response.ok) {
                throw data || { message: `Erro no servidor (${response.status})` };
            }

            new Noty({
                type: 'success',
                layout: 'topRight',
                text: 'Acesso autorizado! Redirecionando...',
                timeout: 2000,
            }).show();

            setTimeout(() => { window.location.href = '/dashboard'; }, 2000);
        })
        .catch(error => {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;

            let msg = error.error || error.message || 'Erro inesperado ao tentar login';
            if (error.errors) {
                msg = Object.values(error.errors).flat()[0];
            }
            if (typeof msg === 'string' && msg.includes('Unexpected token')) {
                msg = 'Sessão expirada. Recarregue a página (F5) e tente novamente.';
            }

            new Noty({
                type: 'error',
                layout: 'topRight',
                text: msg,
                timeout: 4000,
            }).show();
        });
    });
</script>

@endsection
