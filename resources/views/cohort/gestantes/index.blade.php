@extends('layouts.app')

@section('title', 'Análise de risco. Cardioprenatal')

@section('content')
@php
    $queryBase = array_filter([
        'q' => $busca ?: null,
        'per_page' => $perPage !== 25 ? $perPage : null,
    ]);
@endphp
<div class="page-header">
    <h1 class="page-title">Análise de risco</h1>
    <p class="page-subtitle">
        Rastreio de cardiopatias congênitas em gestantes diabéticas.
        As mesmas gestantes do cadastro, com as mais recentes primeiro.
        @if ($dataset) Fonte: {{ $dataset }}. @endif
        A predição usa o modelo a partir da consulta clínica.
    </p>
</div>

@if ($error)
    <div class="main-card" style="margin-bottom: 16px;">
        <p class="dash-empty" style="color: var(--accent);">
            Serviço de análise indisponível no momento. Tente novamente em instantes.
        </p>
    </div>
@endif

<div class="main-card">
    <div class="card-toolbar">
        <h2 class="card-title" style="margin: 0; font-size: 20px;">
            Lista
            <span style="font-size: 14px; color: var(--muted); font-weight: 500;">
                : {{ number_format($total, 0, ',', '.') }} gestantes
            </span>
        </h2>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <form method="GET" action="{{ route('cohort.gestantes.index') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <input class="form-input" type="search" name="q" value="{{ $busca }}"
                       placeholder="Buscar nome, ID ou CPF"
                       style="min-width: 220px; padding: 8px 12px; font-size: 14px;"
                       autocomplete="off">
                @if ($perPage !== 25)
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                @endif
                <button type="submit" class="btn-secondary-outline">Buscar</button>
                @if ($busca !== '')
                    <a href="{{ route('cohort.gestantes.index', array_filter(['per_page' => $perPage !== 25 ? $perPage : null])) }}" class="btn-secondary-outline" style="text-decoration:none;">Limpar</a>
                @endif
            </form>
            <a href="{{ route('gestantes.index') }}" class="btn-secondary-outline" style="text-decoration:none;">
                Ver gestantes
            </a>
        </div>
    </div>

    <div class="table-container table-container--flush">
        <table class="data-table data-table--wide">
            <thead>
                <tr>
                    <th class="td-cell-nome">Nome</th>
                    <th>ID</th>
                    <th class="td-num">Idade</th>
                    <th>Etnia</th>
                    <th class="td-num">IMC</th>
                    <th class="td-num">IG</th>
                    <th class="td-num">Diabetes</th>
                    <th class="td-num">HAS</th>
                    <th class="td-actions">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($gestantes as $g)
                    <tr>
                        <td class="td-cell-nome"><strong>{{ $g['nome'] ?? 'Não informado' }}</strong></td>
                        <td>{{ $g['id_gestante'] ?? 'Não informado' }}</td>
                        <td class="td-num">{{ $g['idade_materna'] ?? 'Não informado' }}</td>
                        <td>{{ $g['etnia'] ?? 'Não informado' }}</td>
                        <td class="td-num">{{ $g['imc_pre_gestacional'] ?? 'Não informado' }}</td>
                        <td class="td-num">{{ $g['idade_gestacional'] ?? 'Não informado' }}</td>
                        <td class="td-num">{{ (int) ($g['diabetes'] ?? 0) }}</td>
                        <td class="td-num">{{ (int) ($g['hipertensao'] ?? 0) }}</td>
                        <td class="td-actions">
                            <a href="{{ route('cohort.gestantes.show', $g['id_gestante']) }}" class="btn-primary-custom" style="padding: 6px 12px; font-size: 13px;">
                                Ver / analisar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="dash-empty">
                            @if ($busca !== '')
                                Nenhuma gestante encontrada para “{{ $busca }}”.
                            @else
                                Nenhuma gestante encontrada.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($total > $perPage)
        <div style="display:flex; gap:10px; justify-content:center; padding: 18px 8px; flex-wrap: wrap;">
            @if ($page > 1)
                <a class="btn-secondary-outline" href="{{ route('cohort.gestantes.index', array_merge($queryBase, ['page' => $page - 1])) }}">← Anterior</a>
            @endif
            <span style="align-self:center; color: var(--muted); font-size: 14px;">
                Página {{ $page }} de {{ $lastPage }}
            </span>
            @if ($page < $lastPage)
                <a class="btn-secondary-outline" href="{{ route('cohort.gestantes.index', array_merge($queryBase, ['page' => $page + 1])) }}">Próxima →</a>
            @endif
        </div>
    @endif
</div>
@endsection
