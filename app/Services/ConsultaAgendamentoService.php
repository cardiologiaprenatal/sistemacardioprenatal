<?php

namespace App\Services;

use App\Events\AgendamentoConfirmado;
use App\Models\Acompanhamento;
use App\Models\AgendamentoSolicitacao;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsultaAgendamentoService
{
    public function agendar(
        Gestante $gestante,
        Carbon $quando,
        User $profissional,
        ?string $observacao = null,
        string $tipo = ConsultaAgendamento::TIPO_CONSULTA,
        ?AgendamentoSolicitacao $solicitacao = null,
        ?Acompanhamento $acompanhamento = null,
    ): ConsultaAgendamento {
        $tipo = $this->normalizarTipo($tipo, $solicitacao);
        $nomeProfissional = $this->nomeProfissional($profissional);
        $nota = $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null;
        if ($nota === null && $solicitacao?->observacao) {
            $nota = trim((string) $solicitacao->observacao);
        }

        $agendamento = DB::transaction(function () use (
            $gestante,
            $quando,
            $profissional,
            $nomeProfissional,
            $nota,
            $tipo,
            $solicitacao,
            $acompanhamento,
        ) {
            $acompanhamento = $this->resolverAcompanhamento(
                $gestante,
                $quando,
                $nomeProfissional,
                $nota,
                $tipo,
                $solicitacao,
                $acompanhamento,
            );

            $agendamento = ConsultaAgendamento::query()->create([
                'gestante_id' => $gestante->id,
                'acompanhamento_id' => $acompanhamento->id,
                'solicitacao_id' => $solicitacao?->id,
                'profissional_id' => $profissional->id,
                'profissional_nome' => $nomeProfissional,
                'agendado_para' => $quando,
                'status' => ConsultaAgendamento::STATUS_AGENDADA,
                'tipo' => $tipo,
                'observacao' => $nota,
            ]);

            if ($solicitacao !== null) {
                $solicitacao->forceFill([
                    'status' => AgendamentoSolicitacao::STATUS_AGENDADA,
                    'acompanhamento_id' => $acompanhamento->id,
                ])->save();
            }

            return $agendamento;
        });

        AgendamentoConfirmado::dispatch($agendamento->load(['gestante', 'acompanhamento']));

        return $agendamento->refresh();
    }

    public function reagendar(
        ConsultaAgendamento $agendamento,
        Carbon $quando,
        User $profissional,
        ?string $observacao = null,
    ): ConsultaAgendamento {
        if (! $agendamento->estaAgendada()) {
            throw new \InvalidArgumentException('Só é possível reagendar consultas com status agendada.');
        }

        $nomeProfissional = $this->nomeProfissional($profissional);
        $nota = $observacao !== null && trim($observacao) !== ''
            ? trim($observacao)
            : $agendamento->observacao;

        $mudouHorario = $agendamento->agendado_para === null
            || ! $agendamento->agendado_para->equalTo($quando);

        $agendamento = DB::transaction(function () use ($agendamento, $quando, $profissional, $nomeProfissional, $nota) {
            $agendamento->forceFill([
                'profissional_id' => $profissional->id,
                'profissional_nome' => $nomeProfissional,
                'agendado_para' => $quando,
                'observacao' => $nota,
                'email_enviado_em' => null,
                'whatsapp_enviado_em' => null,
            ])->save();

            if ($agendamento->acompanhamento) {
                $agendamento->acompanhamento->forceFill([
                    'agendado_para' => $quando,
                    'observacao' => trim('Profissional: '.$nomeProfissional.($nota ? '. '.$nota : '')),
                    'whatsapp_lembrete_enviado_em' => null,
                    'whatsapp_lembrete_para' => null,
                ])->save();
            }

            return $agendamento;
        });

        if ($mudouHorario) {
            AgendamentoConfirmado::dispatch($agendamento->load(['gestante', 'acompanhamento']));
        }

        return $agendamento->refresh();
    }

    public function cancelar(ConsultaAgendamento $agendamento): ConsultaAgendamento
    {
        if ($agendamento->status === ConsultaAgendamento::STATUS_CANCELADA) {
            return $agendamento;
        }

        return DB::transaction(function () use ($agendamento) {
            $agendamento->forceFill([
                'status' => ConsultaAgendamento::STATUS_CANCELADA,
            ])->save();

            if ($agendamento->acompanhamento && $agendamento->acompanhamento->estaAberto()) {
                $agendamento->acompanhamento->forceFill([
                    'status' => Acompanhamento::STATUS_CANCELADO,
                    'concluido_em' => now(),
                ])->save();
            }

            if ($agendamento->solicitacao && $agendamento->solicitacao->status === AgendamentoSolicitacao::STATUS_AGENDADA) {
                $agendamento->solicitacao->forceFill([
                    'status' => AgendamentoSolicitacao::STATUS_CANCELADA,
                ])->save();
            }

            return $agendamento;
        });
    }

    public function nomeProfissional(User $profissional): string
    {
        $nome = trim((string) $profissional->name);
        $crm = trim((string) ($profissional->crm ?? ''));

        if ($nome !== '' && $crm !== '') {
            return $nome.' · '.$crm;
        }

        return $nome !== '' ? $nome : ($crm !== '' ? $crm : 'Profissional');
    }

    private function normalizarTipo(string $tipo, ?AgendamentoSolicitacao $solicitacao): string
    {
        if ($solicitacao !== null) {
            return $solicitacao->tipo;
        }

        if (in_array($tipo, AgendamentoSolicitacao::tipos(), true)) {
            return $tipo;
        }

        return ConsultaAgendamento::TIPO_CONSULTA;
    }

    private function resolverAcompanhamento(
        Gestante $gestante,
        Carbon $quando,
        string $nomeProfissional,
        ?string $nota,
        string $tipo,
        ?AgendamentoSolicitacao $solicitacao,
        ?Acompanhamento $acompanhamento,
    ): Acompanhamento {
        $existente = $acompanhamento
            ?? $solicitacao?->acompanhamento
            ?? ($solicitacao?->acompanhamento_id
                ? Acompanhamento::query()->find($solicitacao->acompanhamento_id)
                : null);

        $motivo = match ($tipo) {
            AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA => Acompanhamento::MOTIVO_ECO_RECOMENDADA,
            AgendamentoSolicitacao::TIPO_RETORNO => Acompanhamento::MOTIVO_RETORNO,
            default => Acompanhamento::MOTIVO_CONSULTA,
        };
        $titulo = match ($tipo) {
            AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA => 'Ecocardiograma agendado',
            AgendamentoSolicitacao::TIPO_RETORNO => 'Retorno agendado',
            default => 'Consulta agendada',
        };
        $observacao = trim('Profissional: '.$nomeProfissional.($nota ? '. '.$nota : ''));

        if ($existente !== null) {
            $existente->forceFill([
                'agendado_para' => $quando,
                'titulo' => $existente->titulo ?: $titulo,
                'observacao' => $observacao,
            ])->save();

            return $existente;
        }

        return Acompanhamento::query()->create([
            'gestante_id' => $gestante->id,
            'status' => Acompanhamento::STATUS_PENDENTE,
            'motivo' => $motivo,
            'titulo' => $titulo,
            'observacao' => $observacao,
            'agendado_para' => $quando,
        ]);
    }
}
