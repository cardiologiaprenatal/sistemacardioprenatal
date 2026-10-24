<?php

namespace App\Listeners;

use App\Events\AgendamentoConfirmado;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarConfirmacaoEmail
{
    public function __construct(private EmailService $email) {}

    public function handle(AgendamentoConfirmado $event): void
    {
        $agendamento = $event->agendamento;
        $gestante = $agendamento->gestante;
        if (! $gestante) {
            return;
        }

        try {
            $enviado = $this->email->sendConfirmacaoConsulta($gestante, $agendamento);
        } catch (Throwable $e) {
            Log::error('Falha ao enviar e-mail de confirmação do agendamento.', [
                'gestante_id' => $gestante->id,
                'agendamento_id' => $agendamento->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($enviado) {
            $agendamento->forceFill(['email_enviado_em' => now()])->save();
        }
    }
}
