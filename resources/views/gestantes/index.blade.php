@extends('layouts.app')

@section('title', 'Gestantes. Cardioprenatal')

@section('content')
@php
    use App\Models\User;
    use App\Services\DashboardAnaliseService;

    $podeAnalises = auth()->user()?->can(User::ABILITY_ANALISES);
    $podeGerenciarGestantes = auth()->user()?->can(User::ABILITY_GESTANTES_MANAGE);
    $colspan = 6 + ($podeAnalises ? 1 : 0) + 1;
@endphp
    <div class="page-header">
        <h1 class="page-title">Gestantes</h1>
        <p class="page-subtitle">Cadastro e acompanhamento no rastreio de cardiopatias congênitas em gestantes diabéticas.</p>
    </div>

    @if (session('success'))
        <div class="main-card" role="status" style="margin-bottom: 16px; border-left: 4px solid var(--success); background: rgba(39, 174, 96, 0.08);">
            <p style="margin: 0; font-size: 15px; color: var(--text);">{{ session('success') }}</p>
        </div>
    @endif
    @if (session('warning'))
        <div class="main-card" role="alert" style="margin-bottom: 16px; border-left: 4px solid #e67e22; background: rgba(230, 126, 34, 0.08);">
            <p style="margin: 0; font-size: 15px; color: var(--text);">{{ session('warning') }}</p>
        </div>
    @endif

    <div class="main-card">
        <div class="card-toolbar">
            <h2 class="card-title" style="margin: 0; font-size: 20px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                Lista
            </h2>
            @can(\App\Models\User::ABILITY_GESTANTES_MANAGE)
            <a href="{{ route('gestantes.create') }}" class="btn-primary-custom">
                Cadastrar gestante
            </a>
            @endcan
        </div>

        <p class="table-scroll-hint">↔ Em telas menores, deslize para ver as colunas; no desktop aparecem todas.</p>
        <div class="table-container table-container--flush">
            <table class="data-table data-table--wide">
                <thead>
                    <tr>
                        <th class="td-cell-nome">Nome</th>
                        <th class="td-num td-num--narrow">Nº cadastro</th>
                        @if ($podeAnalises)
                            <th class="td-risco">Risco</th>
                        @endif
                        <th class="td-cpf">CPF</th>
                        <th class="td-tel">Telefone</th>
                        <th class="td-date">Nascimento</th>
                        <th class="td-num td-num--narrow">Consultas</th>
                        <th class="td-actions">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gestantes as $gestante)
                        @php
                            $risco = null;
                            if ($podeAnalises) {
                                $ua = $gestante->ultimaAnalise;
                                $risco = ($ua && $ua->status === 'concluida') ? $ua->classificacao_risco : null;
                            }
                        @endphp
                        <tr>
                            <td class="td-cell-nome"><strong>{{ $gestante->nome_exibicao }}</strong></td>
                            <td class="td-num td-num--narrow">#{{ $gestante->id }}</td>
                            @if ($podeAnalises)
                            <td class="td-risco">
                                @if ($risco)
                                    <span class="consulta-pill {{ DashboardAnaliseService::pillClassRisco($risco) }}" style="margin:0;">
                                        {{ DashboardAnaliseService::labelRisco($risco) }}
                                    </span>
                                @else
                                    <span style="color: var(--muted); font-size: 13px;">Não informado</span>
                                @endif
                            </td>
                            @endif
                            <td class="td-cpf">{{ $gestante->cpf ? $gestante->cpf_formatado : 'Não informado' }}</td>
                            <td class="td-tel">{{ $gestante->telefone ? $gestante->telefone_formatado : 'Não informado' }}</td>
                            <td class="td-date">{{ $gestante->data_nascimento ? \Carbon\Carbon::parse($gestante->data_nascimento)->format('d/m/Y') : 'Não informado' }}</td>
                            <td class="td-num td-num--narrow">{{ $gestante->consultas_count }}</td>
                            <td class="td-actions">
                                <div class="td-actions-inner td-actions-inner--icons">
                                    <div class="td-actions-group" role="group" aria-label="Cadastro">
                                        <a href="{{ route('gestantes.show', $gestante) }}"
                                           class="btn-table btn-table--primary btn-table--icon"
                                           title="Ver"
                                           aria-label="Ver {{ $gestante->nome_exibicao }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </a>
                                        @if ($podeGerenciarGestantes)
                                        <a href="{{ route('gestantes.edit', $gestante) }}"
                                           class="btn-table btn-table--secondary btn-table--icon"
                                           title="Editar"
                                           aria-label="Editar {{ $gestante->nome_exibicao }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M12 20h9"/>
                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                                            </svg>
                                        </a>
                                        <button type="button"
                                                onclick="abrirModal({{ $gestante->id }})"
                                                class="btn-table btn-table--danger btn-table--icon"
                                                title="Excluir"
                                                aria-label="Excluir {{ $gestante->nome_exibicao }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                                <path d="M10 11v6"/><path d="M14 11v6"/>
                                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                            </svg>
                                        </button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="data-table-empty">
                            <td colspan="{{ $colspan }}">Nenhuma gestante cadastrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($gestantes->hasPages())
            <div class="table-pagination">
                {{ $gestantes->links() }}
            </div>
        @endif
    </div>

    @if ($podeGerenciarGestantes)
    <div id="modalExcluir"
         class="app-modal-overlay"
         style="background: rgba(28, 26, 26, 0.45); backdrop-filter: blur(4px);"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modalExcluirTitulo"
         aria-hidden="true">
        <div class="main-card app-modal-panel" style="padding: 28px;" onclick="event.stopPropagation()">
            <h2 id="modalExcluirTitulo" style="font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--primary); margin-bottom: 12px;">
                Confirmar exclusão
            </h2>
            <p style="color: var(--muted); margin-bottom: 24px; line-height: 1.5;">
                Tem certeza que deseja excluir esta gestante? Esta ação não pode ser desfeita.
            </p>
            <div class="app-modal-actions">
                <button type="button" onclick="fecharModal()"
                        style="padding: 10px 18px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface); cursor: pointer; font-weight: 600; color: var(--text);">
                    Cancelar
                </button>
                <form id="formExcluir" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-primary-custom" style="background: linear-gradient(135deg, #8b1530, var(--accent-mid));">
                        Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('modalExcluir');
            const form = document.getElementById('formExcluir');

            window.abrirModal = function (id) {
                form.action = `/gestantes/${id}`;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            };

            window.fecharModal = function () {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            };

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    fecharModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    fecharModal();
                }
            });
        })();
    </script>
    @endif
@endsection
