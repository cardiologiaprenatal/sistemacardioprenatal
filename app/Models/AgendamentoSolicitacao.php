<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgendamentoSolicitacao extends Model
{
    public const TIPO_CONSULTA = 'consulta';

    public const TIPO_ECOCARDIOGRAMA = 'ecocardiograma';

    public const TIPO_RETORNO = 'retorno';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_AGENDADA = 'agendada';

    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'agendamento_solicitacoes';

    protected $fillable = [
        'gestante_id',
        'solicitado_por',
        'tipo',
        'status',
        'observacao',
        'acompanhamento_id',
    ];

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function acompanhamento(): BelongsTo
    {
        return $this->belongsTo(Acompanhamento::class);
    }

    public function agendamento(): HasOne
    {
        return $this->hasOne(ConsultaAgendamento::class, 'solicitacao_id');
    }

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function labelTipo(): string
    {
        return self::labelParaTipo($this->tipo);
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'Aguardando agendamento',
            self::STATUS_AGENDADA => 'Agendada',
            self::STATUS_CANCELADA => 'Cancelada',
            default => $this->status ?: 'Não informado',
        };
    }

    public static function tipos(): array
    {
        return [
            self::TIPO_CONSULTA,
            self::TIPO_ECOCARDIOGRAMA,
            self::TIPO_RETORNO,
        ];
    }

    public static function labelParaTipo(?string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_CONSULTA => 'Consulta',
            self::TIPO_ECOCARDIOGRAMA => 'Ecocardiograma',
            self::TIPO_RETORNO => 'Retorno',
            default => $tipo ?: 'Não informado',
        };
    }

    public function motivoAcompanhamento(): string
    {
        return match ($this->tipo) {
            self::TIPO_ECOCARDIOGRAMA => Acompanhamento::MOTIVO_ECO_RECOMENDADA,
            self::TIPO_RETORNO => Acompanhamento::MOTIVO_RETORNO,
            default => Acompanhamento::MOTIVO_CONSULTA,
        };
    }
}
