<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestanteDocumentoChunk extends Model
{
    protected $table = 'gestante_documento_chunks';

    protected $fillable = [
        'gestante_documento_id',
        'gestante_id',
        'chunk_index',
        'conteudo',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(GestanteDocumento::class, 'gestante_documento_id');
    }

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }
}
