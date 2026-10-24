<?php

namespace App\Contracts;

use App\Models\Gestante;

interface AtendimentoMenuFlow
{
    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    public function handle(Gestante $gestante, string $mensagem): array;

    /**
     * @return array{estado: string, resposta: string, respondido: bool, pular_ia: bool, intencao: string}
     */
    public function irMenuInicial(Gestante $gestante): array;
}
