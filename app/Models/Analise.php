<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analise extends Model
{
    protected $fillable = [
        'estatistica_geral',
        'analise_risco',
        'comorbidades',
        'odds_ratio_data',
        'fetal_summary',
        'full_response',
        'graficos',
        'ultima_atualizacao',
    ];

    protected $casts = [
        'estatistica_geral' => 'array',
        'analise_risco' => 'array',
        'comorbidades' => 'array',
        'odds_ratio_data' => 'array',
        'fetal_summary' => 'array',
        'full_response' => 'array',
        'graficos' => 'array',
        'ultima_atualizacao' => 'datetime',
    ];
}
