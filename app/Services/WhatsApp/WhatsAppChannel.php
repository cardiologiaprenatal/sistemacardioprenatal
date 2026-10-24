<?php

namespace App\Services\WhatsApp;

use App\Contracts\MessagingChannel;
use App\Models\GestanteCanal;
use App\Services\WhatsAppService;

/**
 * Adaptador de canal: só envia via WAHA. Sem menu, RAG ou Gemini.
 */
class WhatsAppChannel implements MessagingChannel
{
    public function __construct(
        private WhatsAppService $whatsApp,
    ) {}

    public function name(): string
    {
        return GestanteCanal::CANAL_WHATSAPP;
    }

    public function send(string $externalChatId, string $text): bool
    {
        return $this->whatsApp->sendMessage($externalChatId, $text);
    }
}
