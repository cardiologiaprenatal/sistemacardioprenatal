<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\Gestante;

/**
 * Registra solicitações do menu WhatsApp na fila de acompanhamento já existente.
 * Não dispara notificação em tempo real a um atendente ou médico.
 */
class WhatsAppSolicitacaoAtendimentoService
{
    public const ORIGEM_ATENDENTE = 'atendente';

    public const ORIGEM_MEDICO = 'medico';

    public function registrar(
        Gestante $gestante,
        string $origem,
        string $mensagem,
        ?string $categoria = null,
    ): Acompanhamento {
        $titulo = $origem === self::ORIGEM_MEDICO
            ? 'WhatsApp — Falar com o médico'
            : 'WhatsApp — Falar com atendente';

        $observacao = $this->montarObservacao($mensagem, $categoria);

        $aberto = Acompanhamento::query()
            ->where('gestante_id', $gestante->id)
            ->where('motivo', Acompanhamento::MOTIVO_MANUAL)
            ->where('titulo', $titulo)
            ->abertos()
            ->orderByDesc('id')
            ->first();

        if ($aberto !== null) {
            $prev = trim((string) $aberto->observacao);
            $aberto->forceFill([
                'observacao' => $prev !== '' ? $prev."\n\n".$observacao : $observacao,
            ])->save();

            return $aberto->refresh();
        }

        return Acompanhamento::query()->create([
            'gestante_id' => $gestante->id,
            'status' => Acompanhamento::STATUS_PENDENTE,
            'prioridade' => 'moderada',
            'motivo' => Acompanhamento::MOTIVO_MANUAL,
            'titulo' => $titulo,
            'observacao' => $observacao,
        ]);
    }

    private function montarObservacao(string $mensagem, ?string $categoria): string
    {
        $quando = now()->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $linhas = ['['.$quando.'] Solicitação via menu WhatsApp'];

        if ($categoria !== null && trim($categoria) !== '') {
            $linhas[] = 'Categoria: '.trim($categoria);
        }

        $texto = trim($mensagem);
        if ($texto !== '') {
            $linhas[] = 'Mensagem: '.$texto;
        }

        return implode("\n", $linhas);
    }
}
