<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GestanteDocumento extends Model
{
    protected $table = 'gestante_documentos';

    protected $fillable = [
        'gestante_id',
        'titulo',
        'tipo',
        'fonte',
        'data_documento',
        'conteudo_texto',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'data_documento' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(GestanteDocumentoChunk::class);
    }
}
