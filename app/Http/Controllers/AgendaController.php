<?php

namespace App\Http\Controllers;

use App\Models\Acompanhamento;
use App\Models\AgendamentoSolicitacao;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;
use App\Models\User;
use App\Services\AgendamentoSolicitacaoService;
use App\Services\ConsultaAgendamentoService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AgendaController extends Controller
{
    public function __construct(
        private ConsultaAgendamentoService $agendamentoService,
        private AgendamentoSolicitacaoService $indicacaoService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize(User::ABILITY_CONSULTAS_AGENDA);

        $tipo = (string) $request->input('tipo', '');
        $gestanteFiltro = trim((string) $request->input('gestante_id', ''));

        $pendencias = $this->pendencias($tipo, $gestanteFiltro);
        $agendados = ConsultaAgendamento::query()
            ->with(['gestante', 'solicitacao.solicitante', 'profissional'])
            ->when($tipo !== '' && in_array($tipo, AgendamentoSolicitacao::tipos(), true), fn ($q) => $q->where('tipo', $tipo))
            ->when($gestanteFiltro !== '', function ($q) use ($gestanteFiltro) {
                $q->whereHas('gestante', function ($g) use ($gestanteFiltro) {
                    $g->where('gestantes.id', $gestanteFiltro)
                        ->orWhere('gestantes.gestante_id', $gestanteFiltro)
                        ->orWhere('gestantes.nome', 'like', '%'.addcslashes($gestanteFiltro, '%_\\').'%');
                });
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', [
                ConsultaAgendamento::STATUS_AGENDADA,
                ConsultaAgendamento::STATUS_REALIZADA,
            ])
            ->orderBy('agendado_para')
            ->paginate(15)
            ->withQueryString();

        return view('agenda.index', [
            'pendencias' => $pendencias,
            'agendados' => $agendados,
            'tipo' => $tipo,
            'gestanteFiltro' => $gestanteFiltro,
            'profissionais' => $this->profissionais(),
            'gestantes' => $this->gestantes(),
            'podeAgendar' => $request->user()?->can(User::ABILITY_CONSULTAS_AGENDAR) ?? false,
            'podeIndicar' => $request->user()?->can(User::ABILITY_AGENDAMENTOS_INDICAR) ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize(User::ABILITY_CONSULTAS_AGENDAR);
        $request->merge([
            'horario' => $this->normalizeHorario($request->input('horario')),
        ]);

        $validated = $request->validate([
            'gestante_id' => ['required', 'integer', 'exists:gestantes,id'],
            'data' => ['required', 'date'],
            'horario' => ['required', 'date_format:H:i'],
            'profissional_id' => ['required', 'integer', 'exists:users,id'],
            'tipo' => ['nullable', 'in:consulta,ecocardiograma,retorno'],
            'solicitacao_id' => ['nullable', 'integer', 'exists:agendamento_solicitacoes,id'],
            'acompanhamento_id' => ['nullable', 'integer', 'exists:acompanhamentos,id'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'data.required' => 'Informe a data da consulta.',
            'horario.required' => 'Informe o horário.',
            'horario.date_format' => 'Informe um horário válido.',
            'profissional_id.required' => 'Selecione o profissional.',
            'profissional_id.exists' => 'Profissional inválido.',
        ]);

        $quando = $this->quandoOuErro($validated['data'], $validated['horario']);
        if ($quando instanceof RedirectResponse) {
            return $quando;
        }

        $gestante = Gestante::query()->findOrFail($validated['gestante_id']);
        $profissional = User::query()->findOrFail($validated['profissional_id']);
        $solicitacao = ! empty($validated['solicitacao_id'])
            ? AgendamentoSolicitacao::query()->findOrFail($validated['solicitacao_id'])
            : null;
        $acompanhamento = ! empty($validated['acompanhamento_id'])
            ? Acompanhamento::query()->findOrFail($validated['acompanhamento_id'])
            : null;

        if ($solicitacao && (int) $solicitacao->gestante_id !== (int) $gestante->id) {
            abort(422, 'A indicação não pertence a esta gestante.');
        }
        if ($acompanhamento && (int) $acompanhamento->gestante_id !== (int) $gestante->id) {
            abort(422, 'O acompanhamento não pertence a esta gestante.');
        }

        $agendamento = $this->agendamentoService->agendar(
            $gestante,
            $quando,
            $profissional,
            $validated['observacao'] ?? null,
            $validated['tipo'] ?? ConsultaAgendamento::TIPO_CONSULTA,
            $solicitacao,
            $acompanhamento,
        );

        return redirect()
            ->route('agenda.index')
            ->with(
                'success',
                $agendamento->labelTipo().' agendado(a) para '.$agendamento->dataFormatada().' às '.$agendamento->horarioFormatado().'.'
            );
    }

    public function indicar(Request $request): RedirectResponse
    {
        $this->authorize(User::ABILITY_AGENDAMENTOS_INDICAR);

        $validated = $request->validate([
            'gestante_id' => ['required', 'integer', 'exists:gestantes,id'],
            'tipo' => ['required', 'in:consulta,ecocardiograma,retorno'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'gestante_id.required' => 'Selecione a gestante.',
            'tipo.required' => 'Selecione o tipo de consulta.',
        ]);

        $gestante = Gestante::query()->findOrFail($validated['gestante_id']);
        $solicitacao = $this->indicacaoService->indicar(
            $gestante,
            $request->user(),
            $validated['tipo'],
            $validated['observacao'] ?? null,
        );

        return redirect()
            ->route('agenda.index', ['gestante_id' => $gestante->id])
            ->with('success', 'Indicação de '.$solicitacao->labelTipo().' enviada à secretaria para agendamento.');
    }

    public function update(Request $request, ConsultaAgendamento $agendamento): RedirectResponse
    {
        $this->authorize(User::ABILITY_CONSULTAS_AGENDAR);
        $request->merge([
            'horario' => $this->normalizeHorario($request->input('horario')),
        ]);

        $validated = $request->validate([
            'data' => ['required', 'date'],
            'horario' => ['required', 'date_format:H:i'],
            'profissional_id' => ['required', 'integer', 'exists:users,id'],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'data.required' => 'Informe a data da consulta.',
            'horario.required' => 'Informe o horário.',
            'horario.date_format' => 'Informe um horário válido.',
            'profissional_id.required' => 'Selecione o profissional.',
        ]);

        $quando = $this->quandoOuErro($validated['data'], $validated['horario']);
        if ($quando instanceof RedirectResponse) {
            return $quando;
        }

        $profissional = User::query()->findOrFail($validated['profissional_id']);

        try {
            $agendamento = $this->agendamentoService->reagendar(
                $agendamento->load('acompanhamento'),
                $quando,
                $profissional,
                $validated['observacao'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['data' => $e->getMessage()]);
        }

        return redirect()
            ->route('agenda.index')
            ->with(
                'success',
                $agendamento->labelTipo().' reagendado(a) para '.$agendamento->dataFormatada().' às '.$agendamento->horarioFormatado().'.'
            );
    }

    public function cancel(ConsultaAgendamento $agendamento): RedirectResponse
    {
        $this->authorize(User::ABILITY_CONSULTAS_AGENDAR);

        $agendamento = $this->agendamentoService->cancelar(
            $agendamento->load(['acompanhamento', 'solicitacao'])
        );

        return redirect()
            ->route('agenda.index')
            ->with('success', $agendamento->labelTipo().' cancelado(a).');
    }

    /**
     * @return list<array{origem: string, gestante: Gestante, tipo: string, titulo: string, observacao: ?string, criado_em: ?\Illuminate\Support\Carbon, solicitacao_id: ?int, acompanhamento_id: ?int, solicitante: ?string}>
     */
    private function pendencias(string $tipo, string $gestanteFiltro): array
    {
        $solicitacoes = AgendamentoSolicitacao::query()
            ->with(['gestante', 'solicitante', 'acompanhamento'])
            ->where('status', AgendamentoSolicitacao::STATUS_PENDENTE)
            ->when($tipo !== '' && in_array($tipo, AgendamentoSolicitacao::tipos(), true), fn ($q) => $q->where('tipo', $tipo))
            ->when($gestanteFiltro !== '', function ($q) use ($gestanteFiltro) {
                $q->whereHas('gestante', function ($g) use ($gestanteFiltro) {
                    $g->where('gestantes.id', $gestanteFiltro)
                        ->orWhere('gestantes.gestante_id', $gestanteFiltro)
                        ->orWhere('gestantes.nome', 'like', '%'.addcslashes($gestanteFiltro, '%_\\').'%');
                });
            })
            ->orderBy('created_at')
            ->get();

        $idsAcompanhamento = $solicitacoes->pluck('acompanhamento_id')->filter()->unique()->all();

        $ecos = Acompanhamento::query()
            ->with('gestante')
            ->abertos()
            ->where('motivo', Acompanhamento::MOTIVO_ECO_RECOMENDADA)
            ->whereNull('agendado_para')
            ->when($idsAcompanhamento !== [], fn ($q) => $q->whereNotIn('id', $idsAcompanhamento))
            ->when($tipo !== '' && $tipo !== AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA, fn ($q) => $q->whereRaw('1=0'))
            ->when($gestanteFiltro !== '', function ($q) use ($gestanteFiltro) {
                $q->whereHas('gestante', function ($g) use ($gestanteFiltro) {
                    $g->where('gestantes.id', $gestanteFiltro)
                        ->orWhere('gestantes.gestante_id', $gestanteFiltro)
                        ->orWhere('gestantes.nome', 'like', '%'.addcslashes($gestanteFiltro, '%_\\').'%');
                });
            })
            ->orderBy('created_at')
            ->get();

        $itens = [];
        foreach ($solicitacoes as $solicitacao) {
            $itens[] = [
                'origem' => 'indicacao',
                'gestante' => $solicitacao->gestante,
                'tipo' => $solicitacao->tipo,
                'titulo' => $solicitacao->labelTipo(),
                'observacao' => $solicitacao->observacao,
                'criado_em' => $solicitacao->created_at,
                'solicitacao_id' => $solicitacao->id,
                'acompanhamento_id' => $solicitacao->acompanhamento_id,
                'solicitante' => $solicitacao->solicitante?->name,
            ];
        }
        foreach ($ecos as $eco) {
            $itens[] = [
                'origem' => 'eco_analise',
                'gestante' => $eco->gestante,
                'tipo' => AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA,
                'titulo' => $eco->titulo ?: 'Ecocardiograma recomendado',
                'observacao' => $eco->observacao,
                'criado_em' => $eco->created_at,
                'solicitacao_id' => null,
                'acompanhamento_id' => $eco->id,
                'solicitante' => 'Análise de risco',
            ];
        }

        usort($itens, function (array $a, array $b): int {
            $ta = $a['criado_em']?->getTimestamp() ?? 0;
            $tb = $b['criado_em']?->getTimestamp() ?? 0;

            return $ta <=> $tb;
        });

        return $itens;
    }

    private function profissionais()
    {
        return User::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'crm']);
    }

    private function gestantes()
    {
        return Gestante::query()
            ->orderBy('nome')
            ->orderBy('id')
            ->get(['id', 'nome', 'gestante_id']);
    }

    private function quandoOuErro(string $data, string $horario): Carbon|RedirectResponse
    {
        $quando = Carbon::createFromFormat(
            'Y-m-d H:i',
            $data.' '.$horario,
            config('app.timezone')
        );

        if ($quando->lt(now(config('app.timezone'))->subMinutes(5))) {
            return back()
                ->withInput()
                ->withErrors(['data' => 'A data e o horário devem ser atuais ou futuros.']);
        }

        return $quando;
    }

    private function normalizeHorario(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return trim($value);
    }
}
