<?php

namespace App\ApiScripts\Atendimento;

final class AtendimentoResultado
{
    public function __construct(
        public readonly string $fluxo,
        public readonly string $canal,
        public readonly ?int $gestanteId,
        public readonly ?string $intencao,
        public readonly ?string $modelo,
        public readonly int $fontesRag,
        public readonly ?string $ferramenta,
        public readonly bool $enviado,
        public readonly int $durationMs,
        public readonly ?string $erro = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'fluxo' => $this->fluxo,
            'canal' => $this->canal,
            'gestante_id' => $this->gestanteId,
            'intencao' => $this->intencao,
            'modelo' => $this->modelo,
            'fontes_rag' => $this->fontesRag,
            'ferramenta' => $this->ferramenta,
            'enviado' => $this->enviado,
            'duration_ms' => $this->durationMs,
            'erro' => $this->erro,
        ];
    }
}
