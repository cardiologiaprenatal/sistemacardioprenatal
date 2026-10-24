<?php

namespace App\Jobs;

use App\Models\Acompanhamento;
use App\Services\WhatsAppLembreteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarLembreteWhatsappAcompanhamento implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $acompanhamentoId,
    ) {}

    public function uniqueId(): string
    {
        return 'whatsapp-lembrete-acomp-'.$this->acompanhamentoId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(WhatsAppLembreteService $lembreteService): void
    {
        if (! $lembreteService->isEnabled()) {
            Log::debug('WhatsApp lembrete job: auto_lembrete desabilitado — ignorando.', [
                'acompanhamento_id' => $this->acompanhamentoId,
            ]);

            return;
        }

        $acompanhamento = Acompanhamento::query()
            ->with(['gestante.consultas', 'gestante.ultimaAnalise', 'gestante.acompanhamentos'])
            ->find($this->acompanhamentoId);

        if (! $acompanhamento || ! $acompanhamento->estaAberto()) {
            Log::debug('WhatsApp lembrete job: acompanhamento inexistente ou fechado.', [
                'acompanhamento_id' => $this->acompanhamentoId,
            ]);

            return;
        }

        $lembreteService->enviarLembrete($acompanhamento);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('WhatsApp lembrete job: falhou após todas as tentativas.', [
            'acompanhamento_id' => $this->acompanhamentoId,
            'exception' => $e ? $e::class : null,
            'message' => $e?->getMessage(),
        ]);
    }
}
