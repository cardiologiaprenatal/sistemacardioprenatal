<?php

namespace App\Support;

/**
 * Estados da conversa do atendimento inteligente (independente do canal).
 */
class AtendimentoFluxoEstado
{
    public const MENU_INICIAL = 'MENU_INICIAL';

    public const ATENDENTE = 'ATENDENTE';

    public const ATENDENTE_CATEGORIA = 'ATENDENTE_CATEGORIA';

    public const EXAMES_CONSULTAS = 'EXAMES_CONSULTAS';

    public const MEUS_EXAMES = 'MEUS_EXAMES';

    public const MINHAS_CONSULTAS = 'MINHAS_CONSULTAS';

    public const PROXIMOS_COMPROMISSOS = 'PROXIMOS_COMPROMISSOS';

    public const FALAR_COM_MEDICO = 'FALAR_COM_MEDICO';

    public const AGUARDANDO_MENSAGEM_MEDICO = 'AGUARDANDO_MENSAGEM_MEDICO';

    public const CONVERSA_SINTOMA = 'CONVERSA_SINTOMA';

    public static function menuOpcao(?string $estado): ?string
    {
        return match ($estado) {
            self::ATENDENTE, self::ATENDENTE_CATEGORIA => 'atendente',
            self::EXAMES_CONSULTAS, self::MEUS_EXAMES, self::MINHAS_CONSULTAS, self::PROXIMOS_COMPROMISSOS => 'exames',
            self::FALAR_COM_MEDICO, self::AGUARDANDO_MENSAGEM_MEDICO => 'medico',
            self::CONVERSA_SINTOMA => 'sintoma',
            default => null,
        };
    }
}
