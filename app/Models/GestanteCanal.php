<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GestanteCanal extends Model
{
    public const CANAL_WHATSAPP = 'whatsapp';

    protected $table = 'gestante_canais';

    protected $fillable = [
        'gestante_id',
        'canal',
        'external_user_id',
        'external_chat_id',
        'username',
        'display_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(GestanteWhatsapp::class, 'canal_identidade_id');
    }
}
