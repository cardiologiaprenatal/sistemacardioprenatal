<?php

namespace App\ApiScripts\Rag;

use App\Contracts\RagContextBuilder;
use App\Models\Gestante;
use Illuminate\Support\Facades\Log;

/**
 * RAG clínico por gestante. O canal só entra como filtro de histórico, não como regra.
 */
class RecuperarContextoGestante
{
    public function __construct(
        private RagContextBuilder $rag,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function recuperar(Gestante $gestante, string $mensagem, string $canal, int $topK = 6): array
    {
        $inicio = hrtime(true);
        $resultado = $this->rag->buildForGestante($gestante, $mensagem, $topK, $canal);

        Log::info('rag.executed', [
            'gestante_id' => $gestante->id,
            'canal' => $canal,
            'fontes' => $resultado['total_fontes'] ?? 0,
            'intencao' => $resultado['intencao'] ?? null,
            'duration_ms' => (int) ((hrtime(true) - $inicio) / 1_000_000),
        ]);

        return $resultado;
    }
}
