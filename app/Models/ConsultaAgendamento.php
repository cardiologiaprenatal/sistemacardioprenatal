<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultaAgendamento extends Model
{
    public const STATUS_AGENDADA = 'agendada';

    public const STATUS_REALIZADA = 'realizada';

    public const STATUS_CANCELADA = 'cancelada';

    public const TIPO_CONSULTA = AgendamentoSolicitacao::TIPO_CONSULTA;

    public const TIPO_ECOCARDIOGRAMA = AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA;

    public const TIPO_RETORNO = AgendamentoSolicitacao::TIPO_RETORNO;

    protected $table = 'consulta_agendamentos';

    protected $fillable = [
        'gestante_id',
        'acompanhamento_id',
        'solicitacao_id',
        'profissional_id',
        'profissional_nome',
        'agendado_para',
        'status',
        'tipo',
        'email_enviado_em',
        'whatsapp_enviado_em',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'agendado_para' => 'datetime',
            'email_enviado_em' => 'datetime',
            'whatsapp_enviado_em' => 'datetime',
        ];
    }

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profissional_id');
    }

    public function acompanhamento(): BelongsTo
    {
        return $this->belongsTo(Acompanhamento::class);
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(AgendamentoSolicitacao::class, 'solicitacao_id');
    }

    public function labelTipo(): string
    {
        return AgendamentoSolicitacao::labelParaTipo($this->tipo ?: self::TIPO_CONSULTA);
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::STATUS_AGENDADA => 'Agendada',
            self::STATUS_REALIZADA => 'Realizada',
            self::STATUS_CANCELADA => 'Cancelada',
            default => $this->status ?: 'Não informado',
        };
    }

    public function estaAgendada(): bool
    {
        return $this->status === self::STATUS_AGENDADA;
    }

    public function dataFormatada(): string
    {
        if (! $this->agendado_para) {
            return 'Não informado';
        }

        return $this->agendado_para->timezone(config('app.timezone'))->format('d/m/Y');
    }

    public function horarioFormatado(): string
    {
        if (! $this->agendado_para) {
            return 'Não informado';
        }

        return $this->agendado_para->timezone(config('app.timezone'))->format('H:i');
    }
}
