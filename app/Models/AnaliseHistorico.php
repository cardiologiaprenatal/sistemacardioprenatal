<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnaliseHistorico extends Model
{
    protected $table = 'analises_historico';

    public const EVENTO_CADASTRO = 'cadastro';

    public const EVENTO_CONSULTA = 'consulta';

    public const EVENTO_EXAME = 'exame';

    public const EVENTO_INTERCORRENCIA = 'intercorrencia';

    public const EVENTO_ALTERACAO = 'alteracao';

    public const EVENTO_REPROCESSAMENTO = 'reprocessamento';

    protected $fillable = [
        'gestante_id',
        'consulta_id',
        'evento_tipo',
        'analise_uuid',
        'probabilidade_ccf',
        'classificacao_risco',
        'score_prioridade',
        'prioridade_ecocardiograma',
        'recomenda_ecocardiograma',
        'intercorrencias',
        'shap',
        'recomendacoes',
        'predicao',
        'analise',
        'qualidade_dados',
        'full_response',
        'status',
        'erro_mensagem',
    ];

    protected $casts = [
        'probabilidade_ccf' => 'float',
        'score_prioridade' => 'float',
        'recomenda_ecocardiograma' => 'boolean',
        'intercorrencias' => 'array',
        'shap' => 'array',
        'recomendacoes' => 'array',
        'predicao' => 'array',
        'analise' => 'array',
        'qualidade_dados' => 'array',
        'full_response' => 'array',
    ];

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }
}
