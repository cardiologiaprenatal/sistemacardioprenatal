@extends('layouts.app')

@section('title', 'Nova consulta. Cardioprenatal')

@section('content')
    <div style="padding-bottom: 32px;">
        <div style="max-width: 920px; margin: 0 auto;">
            <div class="page-header" style="margin-bottom: 20px;">
                <h1 class="page-title">Nova consulta</h1>
                <p class="page-subtitle">
                    <strong>{{ $gestante->nome_exibicao }}</strong>
                    <span style="color: var(--muted); font-weight: 600;">· #{{ $gestante->id }}</span>
                    @if ($gestante->cpf)
                        · CPF {{ $gestante->cpf_formatado }}
                    @endif
                </p>
            </div>

            <div class="main-card" style="margin-bottom: 0;">
                @if ($errors->any())
                    <div class="form-alert-error">
                        <strong>Verifique os campos abaixo:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('consultas.store', ['id' => $gestante->id]) }}" method="POST">
                    @csrf

                    @include('consultas.partials.fields', ['consulta' => null])

                    <div class="form-actions" style="margin-top: 22px;">
                        <a href="{{ route('gestantes.show', $gestante) }}" class="btn-secondary-outline">Cancelar</a>
                        <div class="form-actions-end">
                            <button type="submit" class="btn-primary-custom">Salvar consulta</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
