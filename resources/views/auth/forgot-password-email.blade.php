@extends('layouts.auth')

@section('title', 'Recuperar por e-mail')

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
        box-sizing: border-box;
    }
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
    .login-register { text-align: center; margin-top: 24px; color: var(--muted); font-size: 14px; }
    .login-register a { color: var(--accent); font-weight: 600; text-decoration: none; }
    @media (max-width: 520px) {
        .login-card { padding: 28px 18px; max-width: calc(100vw - 24px); border-radius: 22px; }
    }
</style>

<div class="login-card">
    <div class="login-header">
        @include('partials.brand-logo', ['variant' => 'auth-sm'])
        <h1 class="login-title">Recuperar por e-mail</h1>
        <p class="login-subtitle">
            Informe o e-mail cadastrado. Se ele existir e o envio estiver disponível, mandaremos um link para redefinir a senha.
        </p>
    </div>

    @if (session('status'))
        <p class="field-status" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" id="emailResetForm">
        @csrf
        <div class="field">
            <label class="field-label" for="email">E-mail cadastrado</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                   class="field-input" required autocomplete="email" maxlength="255"
                   placeholder="nome@email.com">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary" id="emailResetBtn">Enviar link de recuperação</button>
        <div class="login-register">
            <a href="{{ route('password.request') }}">Receber código no WhatsApp</a>
            <span> · </span>
            <a href="{{ route('login') }}">Voltar para o login</a>
        </div>
    </form>
</div>
<script>
    document.getElementById('emailResetForm').addEventListener('submit', function () {
        const button = document.getElementById('emailResetBtn');
        button.disabled = true;
    });
</script>
@endsection
