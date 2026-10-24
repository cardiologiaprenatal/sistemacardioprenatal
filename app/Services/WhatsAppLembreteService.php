<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\GestanteWhatsapp;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orquestra lembretes WhatsApp para acompanhamentos com agendado_para no dia.
 */
class WhatsAppLembreteService
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private WhatsAppMessageTemplates $templates,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.waha.auto_lembrete')
            && (bool) config('services.waha.enabled')
            && filled(config('services.waha.base_url'));
    }

    /**
     * Acompanhamentos abertos com agendado_para na data de hoje e lembrete ainda não enviado para essa data.
     *
     * @return Collection<int, Acompanhamento>
     */
    public function elegiveisParaHoje(?Carbon $hoje = null): Collection
    {
        $hoje = ($hoje ?? now(config('app.timezone')))->startOfDay();
        $amanha = $hoje->copy()->addDay();

        return Acompanhamento::query()
            ->with(['gestante.consultas', 'gestante.ultimaAnalise', 'gestante.acompanhamentos'])
            ->abertos()
            ->whereNotNull('agendado_para')
            ->where('agendado_para', '>=', $hoje)
            ->where('agendado_para', '<', $amanha)
            ->where(function ($q) {
                $q->whereNull('whatsapp_lembrete_para')
                    ->orWhereColumn('whatsapp_lembrete_para', '!=', 'agendado_para');
            })
            ->orderBy('agendado_para')
            ->get();
    }

    /**
     * Envia o lembrete e marca anti-duplicata. Lança RuntimeException se WAHA falhar (para retry do job).
     *
     * @return bool true se enviou; false se pulou (sem telefone etc.)
     */
    public function enviarLembrete(Acompanhamento $acompanhamento): bool
    {
        $gestante = $acompanhamento->gestante;
        if (! $gestante) {
            Log::warning('WhatsApp lembrete: acompanhamento sem gestante.', [
                'acompanhamento_id' => $acompanhamento->id,
            ]);

            return false;
        }

        if (empty($gestante->telefone)) {
            Log::warning('WhatsApp lembrete: gestante sem telefone — pulando.', [
                'acompanhamento_id' => $acompanhamento->id,
                'gestante_id' => $gestante->id,
            ]);

            return false;
        }

        if (! $acompanhamento->agendado_para) {
            Log::warning('WhatsApp lembrete: agendado_para vazio — pulando.', [
                'acompanhamento_id' => $acompanhamento->id,
            ]);

            return false;
        }

        if (! $this->aindaPrecisaLembrete($acompanhamento)) {
            Log::debug('WhatsApp lembrete: já enviado para esta data — pulando.', [
                'acompanhamento_id' => $acompanhamento->id,
            ]);

            return false;
        }

        $templateKey = $acompanhamento->motivo === Acompanhamento::MOTIVO_RETORNO
            ? WhatsAppMessageTemplates::RETORNO
            : WhatsAppMessageTemplates::LEMBRETE_CONSULTA;

        $dataAgendada = $acompanhamento->agendado_para
            ->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');

        $texto = $this->templates->buildForGestante(
            $templateKey,
            $gestante,
            $acompanhamento->prioridade,
            $dataAgendada,
        );

        $enviado = $this->whatsApp->sendGestanteTextMessage(
            $gestante,
            $texto,
            $templateKey === WhatsAppMessageTemplates::RETORNO
                ? GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO
                : GestanteWhatsapp::CATEGORIA_LEMBRETE_CONSULTA,
            $acompanhamento->id,
        );
        if (! $enviado) {
            $detalhe = $this->whatsApp->getLastError() ?: 'Falha desconhecida no WAHA';

            throw new RuntimeException(
                "Falha ao enviar lembrete WhatsApp (acompanhamento {$acompanhamento->id}): {$detalhe}"
            );
        }

        $acompanhamento->forceFill([
            'whatsapp_lembrete_enviado_em' => now(),
            'whatsapp_lembrete_para' => $acompanhamento->agendado_para,
        ])->save();

        Log::info('WhatsApp lembrete: enviado.', [
            'acompanhamento_id' => $acompanhamento->id,
            'gestante_id' => $gestante->id,
            'template' => $templateKey,
        ]);

        return true;
    }

    public function aindaPrecisaLembrete(Acompanhamento $acompanhamento): bool
    {
        if (! $acompanhamento->agendado_para) {
            return false;
        }

        if ($acompanhamento->whatsapp_lembrete_para === null) {
            return true;
        }

        return ! $acompanhamento->whatsapp_lembrete_para->equalTo($acompanhamento->agendado_para);
    }
}
