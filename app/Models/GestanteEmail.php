<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestanteEmail extends Model
{
    protected $table = 'gestante_emails';

    protected $fillable = [
        'gestante_id',
        'destinatario',
        'assunto',
        'mensagem',
        'tipo',
    ];

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }
}
