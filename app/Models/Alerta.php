<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    public const TIPO_RISCO = 'risco';

    public const TIPO_ECO = 'eco';

    public const TIPO_ACOMPANHAMENTO = 'acompanhamento';

    public const TIPO_SISTEMA = 'sistema';

    public const SEVERIDADE_INFO = 'info';

    public const SEVERIDADE_ATENCAO = 'atencao';

    public const SEVERIDADE_URGENTE = 'urgente';

    protected $fillable = [
        'gestante_id',
        'acompanhamento_id',
        'tipo',
        'severidade',
        'titulo',
        'mensagem',
        'chave',
        'lido_em',
        'resolvido_em',
        'metadata',
    ];

    protected $casts = [
        'lido_em' => 'datetime',
        'resolvido_em' => 'datetime',
        'metadata' => 'array',
    ];

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function acompanhamento(): BelongsTo
    {
        return $this->belongsTo(Acompanhamento::class);
    }

    public function estaAberto(): bool
    {
        return $this->resolvido_em === null;
    }

    public function scopeAbertos($query)
    {
        return $query->whereNull('resolvido_em');
    }
}
