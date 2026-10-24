<?php

namespace App\Console\Commands;

use App\Jobs\EnviarLembreteWhatsappAcompanhamento;
use App\Services\WhatsAppLembreteService;
use Illuminate\Console\Command;

class EnviarLembretesWhatsappAgendadosCommand extends Command
{
    protected $signature = 'whatsapp:lembretes-agendados
                            {--sync : Envia no processo atual em vez de enfileirar jobs}
                            {--dry-run : Lista elegíveis sem enviar}';

    protected $description = 'Enfileira lembretes WhatsApp para acompanhamentos com agendado_para = hoje.';

    public function handle(WhatsAppLembreteService $lembreteService): int
    {
        if (! $lembreteService->isEnabled()) {
            $this->warn('WHATSAPP_AUTO_LEMBRETE desligado ou WAHA desabilitado. Nada a fazer.');

            return self::SUCCESS;
        }

        $elegiveis = $lembreteService->elegiveisParaHoje();
        $this->info('Elegíveis hoje: '.$elegiveis->count());

        if ($elegiveis->isEmpty()) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($elegiveis as $item) {
                $this->line(sprintf(
                    '  #%d gestante=%d agendado=%s motivo=%s tel=%s',
                    $item->id,
                    $item->gestante_id,
                    $item->agendado_para?->format('Y-m-d H:i'),
                    $item->motivo,
                    $item->gestante?->telefone ? 'sim' : 'não',
                ));
            }

            return self::SUCCESS;
        }

        $enfileirados = 0;
        $enviados = 0;
        $pulados = 0;
        $erros = 0;

        foreach ($elegiveis as $item) {
            if ($this->option('sync')) {
                try {
                    if ($lembreteService->enviarLembrete($item)) {
                        $enviados++;
                        $this->line("Enviado acompanhamento #{$item->id}");
                    } else {
                        $pulados++;
                        $this->line("Pulado acompanhamento #{$item->id}");
                    }
                } catch (\Throwable $e) {
                    $erros++;
                    $this->error("Erro #{$item->id}: ".$e->getMessage());
                }

                continue;
            }

            EnviarLembreteWhatsappAcompanhamento::dispatch($item->id);
            $enfileirados++;
        }

        if ($this->option('sync')) {
            $this->info("Sync: enviados={$enviados} pulados={$pulados} erros={$erros}");
        } else {
            $this->info("Jobs enfileirados: {$enfileirados}");
        }

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }
}
