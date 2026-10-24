<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterpretacaoClinica extends Model
{
    protected $table = 'interpretacoes_clinicas';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_CONCLUIDA = 'concluida';

    public const STATUS_ERRO = 'erro';

    public const STATUS_CACHE_HIT = 'cache_hit';

    public const PROMPT_VERSION = 'system_v2';

    public const SCHEMA_VERSION = '1.1';

    protected $fillable = [
        'gestante_id',
        'consulta_id',
        'analise_historico_id',
        'status',
        'prompt_version',
        'model_id',
        'contexto_hash',
        'schema_version',
        'resumo_medico',
        'resumo_gestante',
        'fatores_explicados',
        'recomendacoes',
        'deltas',
        'contexto_enviado',
        'resposta_bruta',
        'tokens_in',
        'tokens_out',
        'latency_ms',
        'mock',
        'erro_mensagem',
    ];

    protected $casts = [
        'fatores_explicados' => 'array',
        'recomendacoes' => 'array',
        'deltas' => 'array',
        'contexto_enviado' => 'array',
        'resposta_bruta' => 'array',
        'mock' => 'boolean',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    public function analiseHistorico(): BelongsTo
    {
        return $this->belongsTo(AnaliseHistorico::class, 'analise_historico_id');
    }
}
