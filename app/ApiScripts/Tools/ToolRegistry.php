<?php

namespace App\ApiScripts\Tools;

use App\Contracts\ConversationTool;
use App\Models\Gestante;
use App\Services\Messaging\IncomingMessage;

/**
 * Registro de ferramentas do atendimento. Vazio de propósito: o menu determinístico
 * já cobre exames/consultas; novas tools entram aqui sem acoplar ao canal.
 */
class ToolRegistry
{
    /**
     * @param  list<ConversationTool>  $tools
     */
    public function __construct(
        private array $tools = [],
    ) {}

    public function tryHandle(Gestante $gestante, IncomingMessage $mensagem): ?string
    {
        foreach ($this->tools as $tool) {
            if (! $tool->supports($gestante, $mensagem)) {
                continue;
            }

            return $tool->run($gestante, $mensagem);
        }

        return null;
    }
}
