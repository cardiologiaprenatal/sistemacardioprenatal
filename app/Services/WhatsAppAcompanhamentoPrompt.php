<?php

namespace App\Services;

/**
 * @deprecated Use {@see WhatsAppMessageTemplates}. Mantido para compatibilidade.
 */
class WhatsAppAcompanhamentoPrompt
{
    public function __construct(
        private WhatsAppMessageTemplates $templates,
    ) {}

    public function build(string $motivo, string $nome, ?string $prioridade = null, ?string $dataAgendada = null): string
    {
        return $this->templates->build($motivo, $nome, $prioridade, $dataAgendada);
    }

    public function riscoAlto(string $nome): string
    {
        return $this->templates->riscoAlto($nome);
    }

    public function ecoRecomendada(string $nome, string $prioridadeLabel): string
    {
        return $this->templates->ecoRecomendada($nome, [
            'prioridade_label' => $prioridadeLabel,
        ]);
    }

    public function retorno(string $nome, ?string $dataAgendada): string
    {
        return $this->templates->retorno($nome, [
            'data_agendada' => $dataAgendada,
        ]);
    }

    public function manual(string $nome): string
    {
        return $this->templates->manual($nome);
    }

    public function generico(string $nome): string
    {
        return $this->templates->orientacaoGeral($nome);
    }
}
