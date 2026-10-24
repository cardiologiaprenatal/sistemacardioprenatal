<?php

namespace App\Http\Controllers;

use App\Models\Acompanhamento;
use App\Models\Alerta;
use App\Models\User;
use App\Services\AcompanhamentoSyncService;
use App\Services\DashboardAnaliseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcompanhamentoController extends Controller
{
    public function __construct(
        private AcompanhamentoSyncService $sync,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize(User::ABILITY_ACOMPANHAMENTOS);
        $syncResult = ['acompanhamentos' => 0, 'alertas' => 0];
        if ($request->boolean('sync')) {
            $syncResult = $this->sync->syncFromUltimasAnalises(100);
        }

        $status = $request->string('status')->toString();
        if ($status === '') {
            $status = 'abertos';
        }

        $motivo = $request->has('motivo')
            ? $request->string('motivo')->toString()
            : Acompanhamento::MOTIVO_ECO_RECOMENDADA;

        $query = Acompanhamento::query()
            ->with(['gestante', 'analiseHistorico', 'encaminhadoPor'])
            ->orderByRaw("CASE prioridade WHEN 'urgente' THEN 1 WHEN 'alta' THEN 2 WHEN 'moderada' THEN 3 WHEN 'baixa' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at');

        if ($status === 'abertos') {
            $query->abertos();
        } elseif (in_array($status, [
            Acompanhamento::STATUS_PENDENTE,
            Acompanhamento::STATUS_EM_ANDAMENTO,
            Acompanhamento::STATUS_CONCLUIDO,
            Acompanhamento::STATUS_CANCELADO,
        ], true)) {
            $query->where('status', $status);
        }

        if ($motivo !== '') {
            $query->where('motivo', $motivo);
        }

        $itens = $query->paginate(20)->withQueryString();

        $alertasQuery = Alerta::query()->abertos();
        if ($motivo === Acompanhamento::MOTIVO_ECO_RECOMENDADA) {
            $alertasQuery->where('tipo', Alerta::TIPO_ECO);
        }
        $alertasCount = (clone $alertasQuery)->count();
        $alertasAbertos = (clone $alertasQuery)
            ->with('gestante')
            ->orderByRaw("CASE severidade WHEN 'urgente' THEN 1 WHEN 'atencao' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $kpiBase = Acompanhamento::query();
        if ($motivo !== '') {
            $kpiBase->where('motivo', $motivo);
        }

        $kpis = [
            'pendentes' => (clone $kpiBase)->where('status', Acompanhamento::STATUS_PENDENTE)->count(),
            'em_andamento' => (clone $kpiBase)->where('status', Acompanhamento::STATUS_EM_ANDAMENTO)->count(),
            'alertas' => $alertasCount,
            'urgentes' => (clone $kpiBase)->abertos()->whereIn('prioridade', ['urgente', 'alta'])->count(),
        ];

        return view('acompanhamentos.index', [
            'itens' => $itens,
            'alertasAbertos' => $alertasAbertos,
            'kpis' => $kpis,
            'status' => $status,
            'motivo' => $motivo,
            'syncResult' => $syncResult,
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorize(User::ABILITY_ACOMPANHAMENTOS);
        $result = $this->sync->syncFromUltimasAnalises(150);

        return redirect()
            ->route('acompanhamentos.index', $request->only(['status', 'motivo']))
            ->with('success', "Fila de ecocardiograma sincronizada: {$result['acompanhamentos']} item(ns) e {$result['alertas']} alerta(s) novos.");
    }

    public function update(Request $request, Acompanhamento $acompanhamento): RedirectResponse
    {
        $this->authorize(User::ABILITY_ACOMPANHAMENTOS);
        if ($request->has('agendado_para') && $request->input('agendado_para') === '') {
            $request->merge(['agendado_para' => null]);
        }

        $validated = $request->validate([
            'status' => 'required|in:pendente,em_andamento,concluido,cancelado',
            'observacao' => 'nullable|string|max:2000',
            'agendado_para' => 'nullable|date',
        ]);

        $this->sync->atualizarStatus(
            $acompanhamento,
            $validated['status'],
            $validated['observacao'] ?? null,
            $validated['agendado_para'] ?? null,
            $request->has('agendado_para'),
        );

        return back()->with('success', 'Item da fila atualizado.');
    }

    public function resolverAlerta(Alerta $alerta): RedirectResponse
    {
        $this->authorize(User::ABILITY_ACOMPANHAMENTOS);
        $alerta->resolvido_em = now();
        if ($alerta->chave) {
            $alerta->chave = $alerta->chave.':closed:'.$alerta->id;
        }
        $alerta->save();

        return back()->with('success', 'Alerta resolvido.');
    }

    public static function labelMotivo(?string $motivo): string
    {
        return match ($motivo) {
            Acompanhamento::MOTIVO_RISCO_ALTO => 'Risco alto',
            Acompanhamento::MOTIVO_ECO_RECOMENDADA => 'Ecocardiograma recomendado',
            Acompanhamento::MOTIVO_MANUAL => 'Manual',
            Acompanhamento::MOTIVO_RETORNO => 'Retorno',
            Acompanhamento::MOTIVO_CONSULTA => 'Consulta',
            default => $motivo ?: 'Não informado',
        };
    }

    public static function labelPrioridade(?string $prioridade): string
    {
        return DashboardAnaliseService::labelPrioridadeEco($prioridade);
    }
}
