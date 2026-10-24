@extends('layouts.app')

@section('title', 'Importar consultas. Cardioprenatal')

@section('content')
    <div class="import-stage">
        <div class="main-card import-modal" role="dialog" aria-labelledby="importModalTitulo">
            <h1 class="page-title" id="importModalTitulo" style="margin-bottom: 8px;">Importar consultas</h1>
            <p class="page-subtitle" style="margin-bottom: 24px;">
                CSV ou Excel (.xlsx). Gestantes já cadastradas são atualizadas, não duplicadas.
            </p>

            <h2 class="card-title" style="font-size: 20px; margin-bottom: 8px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Arquivo CSV ou Excel
            </h2>
            <p style="color: var(--muted); font-size: 14px; line-height: 1.55; margin-bottom: 24px;">
                O arquivo precisa da coluna <code>id_gestante</code> (além de
                <code>idade_materna</code>, <code>imc_pre_gestacional</code>,
                <code>paridade</code> e <code>idade_gestacional</code>).
                Reimportar o mesmo arquivo só atualiza quem já existe e inclui as linhas novas.
            </p>

            <form id="importForm">
                @csrf

                <div class="form-field">
                    <label class="form-label" for="csv">Selecionar arquivo</label>
                    <input class="form-input" type="file" name="csv" id="csv"
                           accept=".csv,.txt,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                           required
                           style="padding: 10px; cursor: pointer;">
                </div>

                <div id="feedback" class="hidden" style="margin-top: 20px;" role="alert"></div>

                <div class="form-actions import-modal-actions">
                    <a href="{{ route('dashboard') }}" class="btn-secondary-outline">Voltar</a>
                    <button type="submit" class="btn-primary-custom" id="importSubmit">Importar</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        main:has(.import-stage) {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .import-stage {
            flex: 1;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 0;
        }
        .import-modal {
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            text-align: left;
        }
        .import-modal .page-title,
        .import-modal .page-subtitle {
            text-align: center;
        }
        .import-modal .page-subtitle {
            margin-left: auto;
            margin-right: auto;
        }
        .import-modal-actions {
            border-top: none;
            padding-top: 8px;
            margin-top: 8px;
            justify-content: center;
        }
        .import-alert {
            padding: 16px 18px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.55;
            white-space: pre-wrap;
        }
        .import-alert-ok {
            background: #eef8f1;
            border: 1px solid #c5e3cf;
            color: #1e7a45;
        }
        .import-alert-error {
            background: var(--accent-light);
            border: 1px solid var(--border);
            color: var(--primary);
        }
        @media (max-width: 640px) {
            .import-stage { padding: 16px 0; }
        }
    </style>

    <script>
        document.getElementById('importForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const feedbackDiv = document.getElementById('feedback');
            const submitButton = document.getElementById('importSubmit');

            feedbackDiv.className = 'hidden';
            feedbackDiv.textContent = '';
            submitButton.disabled = true;
            submitButton.textContent = 'Importando…';

            const mostrar = function (texto, ok) {
                feedbackDiv.textContent = texto;
                feedbackDiv.className = 'import-alert ' + (ok ? 'import-alert-ok' : 'import-alert-error');
            };

            try {
                const response = await fetch("{{ route('consultas.import.store') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                let data = {};
                try {
                    data = await response.json();
                } catch (_) {}

                if (response.ok) {
                    const partes = [data.message || 'Importação concluída.'];
                    partes.push(
                        (data.criadas ?? 0) + ' criadas, ' +
                        (data.atualizadas ?? 0) + ' atualizadas, ' +
                        (data.ignoradas ?? 0) + ' ignoradas.'
                    );
                    if ((data.analises_enfileiradas ?? 0) > 0) {
                        partes.push(data.analises_enfileiradas + ' análises foram para a fila do dashboard.');
                    }
                    if ((data.erros ?? 0) > 0) {
                        partes.push(data.erros + ' linha(s) com erro (as demais foram gravadas).');
                    }
                    mostrar(partes.join('\n'), true);
                    submitButton.disabled = false;
                    submitButton.textContent = 'Importar';
                    return;
                }

                let message = data.message || 'Não foi possível importar.';
                if (data.error && data.error.faltando) {
                    message += ' Faltando: ' + data.error.faltando.join(', ') + '.';
                } else if (data.error && data.error.dica) {
                    message += ' ' + data.error.dica;
                } else if (Array.isArray(data.error) && data.error.length) {
                    const primeiro = data.error[0];
                    message += ' Linha ' + (primeiro.linha || primeiro.linha_csv || '?') + ': ' + (primeiro.erro || '');
                } else if (data.errors && data.errors.csv) {
                    message = [].concat(data.errors.csv).join(' ');
                }

                mostrar(message, false);
            } catch (error) {
                mostrar('Erro de conexão ou ao processar o arquivo.', false);
            }

            submitButton.disabled = false;
            submitButton.textContent = 'Importar';
        });
    </script>
@endsection
