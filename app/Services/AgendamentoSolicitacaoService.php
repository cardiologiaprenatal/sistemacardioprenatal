<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\AgendamentoSolicitacao;
use App\Models\Gestante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AgendamentoSolicitacaoService
{
    public function indicar(
        Gestante $gestante,
        User $medico,
        string $tipo,
        ?string $observacao = null,
        ?Acompanhamento $acompanhamento = null,
    ): AgendamentoSolicitacao {
        if (! in_array($tipo, AgendamentoSolicitacao::tipos(), true)) {
            $tipo = AgendamentoSolicitacao::TIPO_CONSULTA;
        }

        $nota = $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null;

        return DB::transaction(function () use ($gestante, $medico, $tipo, $nota, $acompanhamento) {
            if ($acompanhamento === null && $tipo === AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA) {
                $acompanhamento = Acompanhamento::query()
                    ->where('gestante_id', $gestante->id)
                    ->where('motivo', Acompanhamento::MOTIVO_ECO_RECOMENDADA)
                    ->abertos()
                    ->whereNull('agendado_para')
                    ->orderByDesc('id')
                    ->first();
            }

            if ($acompanhamento === null) {
                $acompanhamento = Acompanhamento::query()->create([
                    'gestante_id' => $gestante->id,
                    'status' => Acompanhamento::STATUS_PENDENTE,
                    'motivo' => (new AgendamentoSolicitacao(['tipo' => $tipo]))->motivoAcompanhamento(),
                    'titulo' => 'Indicação: '.AgendamentoSolicitacao::labelParaTipo($tipo),
                    'observacao' => $this->observacaoIndicacao($medico, $nota),
                ]);
            } elseif ($nota) {
                $prev = trim((string) $acompanhamento->observacao);
                $acrescimo = $this->observacaoIndicacao($medico, $nota);
                $acompanhamento->forceFill([
                    'observacao' => $prev !== '' ? $prev."\n\n".$acrescimo : $acrescimo,
                ])->save();
            }

            $pendente = AgendamentoSolicitacao::query()
                ->where('gestante_id', $gestante->id)
                ->where('tipo', $tipo)
                ->where('status', AgendamentoSolicitacao::STATUS_PENDENTE)
                ->when($acompanhamento->id, fn ($q) => $q->where('acompanhamento_id', $acompanhamento->id))
                ->orderByDesc('id')
                ->first();

            if ($pendente !== null) {
                if ($nota) {
                    $prev = trim((string) $pendente->observacao);
                    $pendente->forceFill([
                        'observacao' => $prev !== '' ? $prev."\n".$nota : $nota,
                    ])->save();
                }

                return $pendente->refresh();
            }

            return AgendamentoSolicitacao::query()->create([
                'gestante_id' => $gestante->id,
                'solicitado_por' => $medico->id,
                'tipo' => $tipo,
                'status' => AgendamentoSolicitacao::STATUS_PENDENTE,
                'observacao' => $nota,
                'acompanhamento_id' => $acompanhamento->id,
            ]);
        });
    }

    private function observacaoIndicacao(User $medico, ?string $nota): string
    {
        $quando = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $linhas = ['['.$quando.'] Indicação do médico '.$this->nomeMedico($medico)];
        if ($nota) {
            $linhas[] = $nota;
        }

        return implode("\n", $linhas);
    }

    private function nomeMedico(User $medico): string
    {
        $nome = trim((string) $medico->name);

        return $nome !== '' ? $nome : 'Médico';
    }
}
