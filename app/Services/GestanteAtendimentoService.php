<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\Alerta;
use App\Models\Gestante;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestanteAtendimentoService
{
    public function aceitarAtendimentoPersonalizado(Gestante $gestante, User $user): Gestante
    {
        $this->assertPodeDirecionar($user);
        $this->assertTemConsulta($gestante, 'Registre a consulta antes de aceitar o atendimento personalizado.');

        return DB::transaction(function () use ($gestante, $user) {
            $atual = Gestante::query()->lockForUpdate()->findOrFail($gestante->id);

            if ($atual->atendimentoPersonalizadoLiberado()) {
                return $this->sincronizarFluxo($atual);
            }

            $atual->forceFill([
                'atendimento_personalizado_aceito_em' => now(),
                'atendimento_personalizado_aceito_por' => $user->id,
            ])->save();

            return $this->sincronizarFluxo($atual);
        });
    }

    public function encaminharParaFilaEcocardiograma(Gestante $gestante, User $user): Acompanhamento
    {
        $this->assertPodeDirecionar($user);
        $this->assertTemConsulta($gestante, 'Registre a consulta antes de encaminhar a gestante para a fila do ecocardiograma.');

        return DB::transaction(function () use ($gestante, $user) {
            $atual = Gestante::query()->lockForUpdate()->findOrFail($gestante->id);
            $consultaId = $atual->consultas()->orderByDesc('id')->value('id');

            $aberto = Acompanhamento::query()
                ->where('gestante_id', $atual->id)
                ->where('motivo', Acompanhamento::MOTIVO_ECO_RECOMENDADA)
                ->abertos()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($aberto !== null) {
                if ($aberto->encaminhado_por_user_id === null) {
                    $aberto->forceFill([
                        'encaminhado_por_user_id' => $user->id,
                        'encaminhado_em' => $aberto->encaminhado_em ?? now(),
                        'consulta_id' => $aberto->consulta_id ?: $consultaId,
                    ])->save();
                }

                $this->sincronizarFluxo($atual);

                return $aberto->refresh();
            }

            $quando = now();
            $observacao = 'Encaminhada pelo médico '.$user->name.' em '.$quando->timezone(config('app.timezone'))->format('d/m/Y H:i').'.';

            $acompanhamento = Acompanhamento::query()->create([
                'gestante_id' => $atual->id,
                'consulta_id' => $consultaId,
                'encaminhado_por_user_id' => $user->id,
                'encaminhado_em' => $quando,
                'status' => Acompanhamento::STATUS_PENDENTE,
                'prioridade' => 'moderada',
                'motivo' => Acompanhamento::MOTIVO_ECO_RECOMENDADA,
                'titulo' => 'Encaminhamento médico para ecocardiograma',
                'observacao' => $observacao,
            ]);

            Alerta::query()->create([
                'gestante_id' => $atual->id,
                'acompanhamento_id' => $acompanhamento->id,
                'tipo' => Alerta::TIPO_ECO,
                'severidade' => Alerta::SEVERIDADE_ATENCAO,
                'titulo' => 'Encaminhamento para fila de ecocardiograma',
                'mensagem' => $observacao,
                'chave' => 'eco_medico:'.$atual->id.':'.$acompanhamento->id,
                'metadata' => [
                    'origem' => 'medico',
                    'encaminhado_por_user_id' => $user->id,
                ],
            ]);

            $this->sincronizarFluxo($atual);

            return $acompanhamento;
        });
    }

    public function sincronizarFluxo(Gestante $gestante): Gestante
    {
        $eco = Acompanhamento::query()
            ->where('gestante_id', $gestante->id)
            ->where('motivo', Acompanhamento::MOTIVO_ECO_RECOMENDADA)
            ->orderByDesc('id')
            ->get();

        $aberto = $eco->first(fn (Acompanhamento $item) => $item->estaAberto());
        $ultimo = $eco->first();

        if ($aberto !== null && $aberto->agendado_para) {
            $fluxo = Gestante::FLUXO_ECOCARDIOGRAMA_AGENDADO;
        } elseif ($aberto !== null) {
            $fluxo = Gestante::FLUXO_AGUARDANDO_ECOCARDIOGRAMA;
        } elseif ($ultimo && $ultimo->status === Acompanhamento::STATUS_CONCLUIDO) {
            $fluxo = Gestante::FLUXO_ECOCARDIOGRAMA_REALIZADO;
        } elseif ($gestante->atendimentoPersonalizadoLiberado()) {
            $fluxo = Gestante::FLUXO_ATENDIMENTO_PERSONALIZADO;
        } elseif ($gestante->consultas()->exists()) {
            $fluxo = Gestante::FLUXO_CONSULTA_REALIZADA;
        } else {
            $fluxo = Gestante::FLUXO_AGUARDANDO_CONSULTA;
        }

        if ($gestante->fluxo_atendimento !== $fluxo) {
            $gestante->forceFill(['fluxo_atendimento' => $fluxo])->save();
        }

        return $gestante->refresh();
    }

    private function assertPodeDirecionar(User $user): void
    {
        if (! $user->canDirecionarAtendimentoGestante()) {
            abort(403, 'Apenas o médico pode autorizar este encaminhamento.');
        }
    }

    private function assertTemConsulta(Gestante $gestante, string $mensagem): void
    {
        if (! $gestante->consultas()->exists()) {
            throw ValidationException::withMessages([
                'consulta' => $mensagem,
            ]);
        }
    }
}
