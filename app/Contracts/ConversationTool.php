<?php

namespace App\Contracts;

use App\Models\Gestante;
use App\Services\Messaging\IncomingMessage;

/**
 * Ferramenta futura do orquestrador (exames, consultas, encaminhamento).
 * Nenhuma implementação fictícia é registrada hoje.
 */
interface ConversationTool
{
    public function name(): string;

    public function supports(Gestante $gestante, IncomingMessage $mensagem): bool;

    public function run(Gestante $gestante, IncomingMessage $mensagem): ?string;
}
