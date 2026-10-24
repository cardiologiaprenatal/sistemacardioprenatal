<?php

namespace App\Services\Messaging;

use App\ApiScripts\Atendimento\AtendimentoResultado;
use App\ApiScripts\Atendimento\ProcessarMensagem;
use App\Contracts\MessagingChannel;

/**
 * Compatibilidade: o orquestrador vive em App\ApiScripts\Atendimento\ProcessarMensagem.
 */
class AtendimentoInteligenteService
{
    public function __construct(
        private ProcessarMensagem $processar,
    ) {}

    public function handle(IncomingMessage $mensagem, MessagingChannel $canal): AtendimentoResultado
    {
        return $this->processar->handle($mensagem, $canal);
    }
}
