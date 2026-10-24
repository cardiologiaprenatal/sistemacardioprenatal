@extends('layouts.app')

@section('title', 'Editar gestante. Cardioprenatal')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Editar gestante</h1>
        <p class="page-subtitle">{{ $gestante->nome_exibicao }}</p>
    </div>

    <div class="main-card" style="max-width: 720px;">
        <form action="{{ route('gestantes.update', $gestante->id) }}" method="POST">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div style="background: var(--accent-light); border: 1px solid var(--border); color: var(--primary); padding: 16px 18px; border-radius: 16px; margin-bottom: 24px;">
                    <ul style="margin: 0; padding-left: 1.1rem; line-height: 1.6;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display: grid; gap: 20px;">
                <div>
                    <label for="nome" style="display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 8px;">Nome completo</label>
                    <input type="text" name="nome" id="nome" required value="{{ old('nome', $gestante->nome) }}" maxlength="255"
                           autocomplete="name"
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); font-family: inherit; font-size: 15px;">
                </div>

                <div>
                    <span style="display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 8px;">Número do cadastro</span>
                    <p style="margin: 0; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); background: rgba(253, 240, 240, 0.35); font-size: 15px; font-weight: 600; color: var(--text);">
                        #{{ $gestante->id }}
                    </p>
                    <p style="font-size: 12px; color: var(--muted); margin-top: 8px; line-height: 1.4;">Gerado automaticamente e sequencial; não pode ser alterado.</p>
                </div>

                <div>
                    <label for="cpf" style="display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 8px;">CPF</label>
                    <input type="text" name="cpf" id="cpf"
                           value="{{ old('cpf', $gestante->cpf ? $gestante->cpf_formatado : '') }}"
                           placeholder="000.000.000-00"
                           inputmode="numeric" maxlength="14" required
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); font-family: inherit; font-size: 15px;">
                </div>

                <div>
                    <label for="telefone" style="display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 8px;">Telefone / WhatsApp</label>
                    <input type="text" name="telefone" id="telefone"
                           value="{{ old('telefone', $gestante->telefone_formatado ?? $gestante->telefone) }}"
                           placeholder="(00) 00000-0000"
                           inputmode="tel" maxlength="16" required
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); font-family: inherit; font-size: 15px;">
                </div>

                <div>
                    <label for="email" style="display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 8px;">E-mail (opcional)</label>
                    <input type="email" name="email" id="email"
                           value="{{ old('email', $gestante->email) }}"
                           placeholder="nome@email.com"
                           autocomplete="email" maxlength="255"
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); font-family: inherit; font-size: 15px;">
                </div>

                <div>
                    <label for="data_nascimento" style="display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: 8px;">Data de nascimento</label>
                    <input type="date" name="data_nascimento" id="data_nascimento" required value="{{ old('data_nascimento', $gestante->data_nascimento) }}"
                           style="width: 100%; max-width: min(280px, 100%); padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); font-family: inherit; font-size: 15px;">
                </div>
            </div>

            <div class="gestante-form-actions" style="margin-top: 28px; display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="submit" class="btn-primary-custom">Atualizar</button>
                <a href="{{ route('gestantes.show', $gestante) }}" style="display: inline-flex; align-items: center; padding: 10px 20px; border-radius: 12px; border: 1px solid var(--border); color: var(--primary); text-decoration: none; font-weight: 600;">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

    @include('partials.form-masks')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cpf = document.getElementById('cpf');
            const tel = document.getElementById('telefone');
            if (cpf) {
                maskCPFInput(cpf);
                cpf.addEventListener('input', function () { maskCPFInput(this); });
            }
            if (tel) {
                maskTelefoneBRInput(tel);
                tel.addEventListener('input', function () { maskTelefoneBRInput(this); });
            }
        });
    </script>
@endsection
