@extends('layouts.auth')

@section('title', 'Esqueci minha senha')

@section('content')
<style>
    :root {
        --primary: #7f0c1a;
        --accent: #c0392b;
        --danger: #c0392b;
        --text: #1c1a1a;
        --muted: #8a6f6f;
        --border: #f0d5d5;
        --surface: #ffffff;
    }
    .login-card {
        position: relative; z-index: 1;
        background: var(--surface);
        border-radius: 28px;
        box-shadow: 0 16px 48px rgba(127,12,26,.12), 0 0 0 1px rgba(240,213,213,.8);
        padding: 44px 48px 38px;
        width: 100%; max-width: 440px;
    }
    .login-header { text-align: center; margin-bottom: 28px; }
    .login-title {
        font-family: 'DM Serif Display', serif;
        font-size: 26px; color: var(--primary); margin: 0 0 8px;
    }
    .login-subtitle { font-size: 14px; color: var(--muted); line-height: 1.5; }
    .field { margin-bottom: 16px; }
    .field-label { display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 8px; }
    .field-wrap { position: relative; }
    .field-input {
        width: 100%; padding: 12px 14px; border: 1px solid var(--border);
        border-radius: 12px; font-size: 15px; color: var(--text); background: #fff;
        box-sizing: border-box;
    }
    .field-input.with-eye { padding-right: 44px; }
    .field-input:focus { outline: 2px solid var(--primary); outline-offset: 1px; }
    .field-error { color: var(--danger); font-size: 12px; margin-top: 5px; }
    .field-status {
        background: rgba(39, 174, 96, 0.1); color: #1e7a45;
        border-radius: 12px; padding: 12px 14px; font-size: 14px; margin-bottom: 16px; line-height: 1.45;
    }
    .btn-primary {
        width: 100%; margin-top: 8px; padding: 13px 16px; border: 0; border-radius: 12px;
        background: linear-gradient(135deg, var(--primary), var(--accent));
        color: #fff; font-weight: 600; font-size: 15px; cursor: pointer;
    }
    .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
    .btn-eye {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        background: none; border: 0; color: var(--muted); cursor: pointer; padding: 4px;
    }
    .login-register { text-align: center; margin-top: 18px; color: var(--muted); font-size: 14px; }
    .login-register a { color: var(--accent); font-weight: 600; text-decoration: none; }
    .step { display: none; }
    .step.is-active { display: block; }
    @media (max-width: 520px) {
        .login-card { padding: 28px 18px; max-width: calc(100vw - 24px); border-radius: 22px; }
    }
</style>

<div class="login-card">
    <div class="login-header">
        @include('partials.brand-logo', ['variant' => 'auth-sm'])
        <h1 class="login-title">Esqueci minha senha</h1>
        <p class="login-subtitle" id="stepHint">Informe o telefone cadastrado na sua conta. Se ele estiver no cadastro, enviaremos um código pelo WhatsApp.</p>
    </div>

    <p class="field-status" id="flowStatus" role="status" hidden></p>
    <p class="field-error" id="flowError" hidden></p>

    <form class="step is-active" id="stepPhone">
        <div class="field">
            <label class="field-label" for="telefone">Telefone com DDD</label>
            <input type="tel" name="telefone" id="telefone" class="field-input" required maxlength="20"
                   inputmode="tel" autocomplete="tel" placeholder="(11) 99999-9999">
        </div>
        <button type="submit" class="btn-primary" id="sendCodeBtn">Enviar código</button>
    </form>

    <form class="step" id="stepCode">
        <div class="field">
            <label class="field-label" for="code">Código recebido</label>
            <input type="text" name="code" id="code" class="field-input" required maxlength="6"
                   inputmode="numeric" autocomplete="one-time-code" placeholder="6 dígitos">
        </div>
        <button type="submit" class="btn-primary" id="verifyCodeBtn">Validar código</button>
        <button type="button" class="btn-primary" id="resendBtn" style="margin-top: 10px; background: transparent; color: var(--primary); border: 1px solid var(--border);" disabled>
            Reenviar código (<span id="resendCount">{{ $resendSeconds }}</span>s)
        </button>
    </form>

    <form class="step" id="stepPassword">
        <div class="field">
            <label class="field-label" for="password">Nova senha</label>
            <div class="field-wrap">
                <input type="password" name="password" id="password" class="field-input with-eye" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                <button type="button" class="btn-eye" id="togglePassword" aria-label="Mostrar senha">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>
        <div class="field">
            <label class="field-label" for="password_confirmation">Confirmar senha</label>
            <div class="field-wrap">
                <input type="password" name="password_confirmation" id="password_confirmation" class="field-input with-eye" required minlength="8" autocomplete="new-password">
                <button type="button" class="btn-eye" id="togglePasswordConfirm" aria-label="Mostrar confirmação da senha">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-primary" id="savePasswordBtn">Salvar nova senha</button>
    </form>

    <div class="login-register">
        <a href="{{ route('login') }}">Voltar para o login</a>
    </div>
</div>

<script>
(function () {
    const resendSeconds = {{ (int) $resendSeconds }};
    const phoneForm = document.getElementById('stepPhone');
    const codeForm = document.getElementById('stepCode');
    const passwordForm = document.getElementById('stepPassword');
    const statusEl = document.getElementById('flowStatus');
    const errorEl = document.getElementById('flowError');
    const hint = document.getElementById('stepHint');
    let resetToken = '';
    let timer = null;

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function showMessage(text, ok) {
        statusEl.hidden = !ok;
        errorEl.hidden = ok;
        (ok ? statusEl : errorEl).textContent = text || '';
    }

    function setBusy(button, busy) {
        button.disabled = busy;
    }

    function showStep(step) {
        [phoneForm, codeForm, passwordForm].forEach(function (form) {
            form.classList.toggle('is-active', form === step);
        });
    }

    function startCountdown(seconds) {
        const button = document.getElementById('resendBtn');
        const label = document.getElementById('resendCount');
        let left = Math.max(0, seconds);
        button.disabled = left > 0;
        label.textContent = String(left);
        if (timer) clearInterval(timer);
        if (left === 0) return;
        timer = setInterval(function () {
            left -= 1;
            label.textContent = String(Math.max(left, 0));
            if (left <= 0) {
                clearInterval(timer);
                button.disabled = false;
                button.textContent = 'Reenviar código';
            }
        }, 1000);
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const raw = await response.text();
        let data = {};
        try { data = raw ? JSON.parse(raw) : {}; } catch (_) { data = { message: 'Resposta inválida do servidor.' }; }
        if (!data.message && data.errors) {
            data.message = Object.values(data.errors).flat()[0];
        }
        return { ok: response.ok, status: response.status, data: data };
    }

    function phoneValue() {
        return document.getElementById('telefone').value.trim();
    }

    phoneForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = document.getElementById('sendCodeBtn');
        setBusy(button, true);
        const result = await postJson(@json(route('password.whatsapp.send')), { telefone: phoneValue() });
        setBusy(button, false);
        showMessage(result.data.message || 'Não foi possível enviar o código.', result.ok);
        if (!result.ok) return;
        hint.textContent = 'Digite o código de 6 dígitos enviado pelo WhatsApp.';
        showStep(codeForm);
        startCountdown(Number(result.data.retry_after || resendSeconds));
    });

    document.getElementById('resendBtn').addEventListener('click', async function () {
        const button = this;
        setBusy(button, true);
        const result = await postJson(@json(route('password.whatsapp.send')), { telefone: phoneValue() });
        showMessage(result.data.message || 'Não foi possível reenviar o código.', result.ok);
        if (result.ok) {
            startCountdown(Number(result.data.retry_after || resendSeconds));
        } else {
            button.disabled = false;
        }
    });

    codeForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = document.getElementById('verifyCodeBtn');
        setBusy(button, true);
        const result = await postJson(@json(route('password.whatsapp.verify')), {
            telefone: phoneValue(),
            code: document.getElementById('code').value.trim(),
        });
        setBusy(button, false);
        showMessage(result.data.message || 'Código inválido ou expirado.', result.ok);
        if (!result.ok) return;
        resetToken = result.data.reset_token || '';
        hint.textContent = 'Escolha uma nova senha com no mínimo 8 caracteres.';
        showStep(passwordForm);
    });

    function bindEye(buttonId, inputId) {
        document.getElementById(buttonId).addEventListener('click', function () {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    }
    bindEye('togglePassword', 'password');
    bindEye('togglePasswordConfirm', 'password_confirmation');

    passwordForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = document.getElementById('savePasswordBtn');
        const password = document.getElementById('password').value;
        const confirmation = document.getElementById('password_confirmation').value;
        if (password !== confirmation) {
            showMessage('A confirmação da senha não confere.', false);
            return;
        }
        setBusy(button, true);
        const result = await postJson(@json(route('password.whatsapp.update')), {
            telefone: phoneValue(),
            reset_token: resetToken,
            password: password,
            password_confirmation: confirmation,
        });
        showMessage(result.data.message || 'Não foi possível redefinir a senha.', result.ok);
        if (!result.ok) {
            setBusy(button, false);
            return;
        }
        window.location.href = result.data.redirect || @json(route('login'));
    });
})();
</script>
@endsection
