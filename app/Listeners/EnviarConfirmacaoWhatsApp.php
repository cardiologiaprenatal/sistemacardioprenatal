<?php

namespace App\Listeners;

use App\Events\AgendamentoConfirmado;
use App\Models\GestanteWhatsapp;
use App\Services\WhatsAppMessageTemplates;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarConfirmacaoWhatsApp
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private WhatsAppMessageTemplates $templates,
    ) {}

    public function handle(AgendamentoConfirmado $event): void
    {
        $agendamento = $event->agendamento;
        $gestante = $agendamento->gestante;
        if (! $gestante) {
            return;
        }

        try {
            $texto = $this->templates->textoConfirmacaoConsulta($gestante, $agendamento);
            $enviado = $this->whatsApp->sendGestanteTextMessage(
                $gestante,
                $texto,
                GestanteWhatsapp::CATEGORIA_CONFIRMACAO_CONSULTA,
                $agendamento->acompanhamento_id,
            );
        } catch (Throwable $e) {
            Log::error('Falha ao enviar WhatsApp de confirmação do agendamento.', [
                'gestante_id' => $gestante->id,
                'agendamento_id' => $agendamento->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($enviado) {
            $agendamento->forceFill(['whatsapp_enviado_em' => now()])->save();
        }
    }
}
