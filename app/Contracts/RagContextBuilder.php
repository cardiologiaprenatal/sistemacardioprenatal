<?php

namespace App\Contracts;

use App\Models\Gestante;

/**
 * Recupera contexto clínico por gestante. Não conhece o canal de mensagem.
 */
interface RagContextBuilder
{
    /**
     * @return array{
     *   found: bool,
     *   gestante_id: int|null,
     *   gestante_id_publico: string|null,
     *   chunks: list<array{source: string, title: string, text: string, score: float}>,
     *   contexto_texto: string,
     *   total_fontes: int,
     *   pular_ia: bool,
     *   atendimento_humano: bool,
     *   ia_pausada_ate: string|null,
     *   menu_opcao: string|null,
     *   fluxo_estado: string|null,
     *   intencao: string,
     *   resumo_prenatal: string,
     *   nome: string|null
     * }
     */
    public function buildForGestante(
        Gestante $gestante,
        ?string $mensagem = null,
        int $topK = 6,
        string $canal = 'whatsapp',
    ): array;
}
