@extends('layouts.auth')

@section('title', 'Redefinir senha')

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
    .field-input {
        width: 100%; padding: 12px 14px; border: 1px solid var(--border);
        border-radius: 12px; font-size: 15px; color: var(--text); background: #fff;
    }
    .field-input:focus { outline: 2px solid var(--primary); outline-offset: 1px; }
    .field-error { color: var(--danger); font-size: 12px; margin-top: 5px; }
    .field-wrap { position: relative; }
    .field-input.with-eye { padding-right: 44px; }
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
    .login-register { text-align: center; margin-top: 24px; color: var(--muted); font-size: 14px; }
    @media (max-width: 520px) {
        .login-card { padding: 28px 18px; max-width: calc(100vw - 24px); border-radius: 22px; }
    }
    .login-register a { color: var(--accent); font-weight: 600; text-decoration: none; }
</style>

<div class="login-card">
    <div class="login-header">
        @include('partials.brand-logo', ['variant' => 'auth-sm'])
        <h1 class="login-title">Nova senha</h1>
        <p class="login-subtitle">Digite a nova senha e confirme. Ela deve ter no mínimo 8 caracteres.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" id="resetForm">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label class="field-label" for="email">E-mail</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}"
                   class="field-input" required autocomplete="email">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field-label" for="password">Nova senha</label>
            <div class="field-wrap">
                <input type="password" name="password" id="password"
                       class="field-input with-eye" required minlength="8" autocomplete="new-password">
                <button type="button" class="btn-eye" id="toggleResetPassword" aria-label="Mostrar senha">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field-label" for="password_confirmation">Confirmar senha</label>
            <div class="field-wrap">
                <input type="password" name="password_confirmation" id="password_confirmation"
                       class="field-input with-eye" required minlength="8" autocomplete="new-password">
                <button type="button" class="btn-eye" id="toggleResetPasswordConfirm" aria-label="Mostrar confirmação da senha">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-primary" id="resetSubmitBtn">Salvar nova senha</button>
        <div class="login-register">
            <a href="{{ route('login') }}">Voltar para o login</a>
        </div>
    </form>
</div>
<script>
    function bindEye(buttonId, inputId) {
        const button = document.getElementById(buttonId);
        const input = document.getElementById(inputId);
        if (!button || !input) return;
        button.addEventListener('click', function () {
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    }
    bindEye('toggleResetPassword', 'password');
    bindEye('toggleResetPasswordConfirm', 'password_confirmation');
    document.getElementById('resetForm').addEventListener('submit', function () {
        document.getElementById('resetSubmitBtn').disabled = true;
    });
</script>
@endsection
