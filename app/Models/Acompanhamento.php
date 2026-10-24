<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Acompanhamento extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    public const STATUS_CONCLUIDO = 'concluido';

    public const STATUS_CANCELADO = 'cancelado';

    public const MOTIVO_RISCO_ALTO = 'risco_alto';

    public const MOTIVO_ECO_RECOMENDADA = 'eco_recomendada';

    public const MOTIVO_MANUAL = 'manual';

    public const MOTIVO_RETORNO = 'retorno';

    public const MOTIVO_CONSULTA = 'consulta';

    protected $fillable = [
        'gestante_id',
        'consulta_id',
        'analise_historico_id',
        'encaminhado_por_user_id',
        'encaminhado_em',
        'status',
        'prioridade',
        'motivo',
        'titulo',
        'observacao',
        'agendado_para',
        'whatsapp_lembrete_enviado_em',
        'whatsapp_lembrete_para',
        'concluido_em',
    ];

    protected $casts = [
        'agendado_para' => 'datetime',
        'whatsapp_lembrete_enviado_em' => 'datetime',
        'whatsapp_lembrete_para' => 'datetime',
        'concluido_em' => 'datetime',
        'encaminhado_em' => 'datetime',
    ];

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    public function encaminhadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaminhado_por_user_id');
    }

    public function analiseHistorico(): BelongsTo
    {
        return $this->belongsTo(AnaliseHistorico::class, 'analise_historico_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    public function mensagensWhatsapp(): HasMany
    {
        return $this->hasMany(GestanteWhatsapp::class);
    }

    public function isRetorno(): bool
    {
        return $this->motivo === self::MOTIVO_RETORNO;
    }

    public function tipoAgenda(): string
    {
        return $this->isRetorno() ? 'retorno' : 'consulta';
    }

    public function labelTipoAgenda(): string
    {
        return $this->isRetorno() ? 'Retorno' : 'Consulta';
    }

    public function dataAgenda(): ?\Illuminate\Support\Carbon
    {
        if ($this->agendado_para) {
            return $this->agendado_para;
        }

        $dataConsulta = $this->consulta?->data_consulta;
        if ($dataConsulta === null) {
            return $this->whatsapp_lembrete_enviado_em;
        }

        return $dataConsulta instanceof \Illuminate\Support\Carbon
            ? $dataConsulta
            : \Illuminate\Support\Carbon::parse($dataConsulta);
    }

    public function estaAberto(): bool
    {
        return in_array($this->status, [self::STATUS_PENDENTE, self::STATUS_EM_ANDAMENTO], true);
    }

    public function scopeAbertos($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDENTE, self::STATUS_EM_ANDAMENTO]);
    }
}
