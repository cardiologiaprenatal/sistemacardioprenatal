<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestanteWhatsapp extends Model
{
    public const CATEGORIA_CONVERSA = 'conversa';

    public const CATEGORIA_LEMBRETE_CONSULTA = 'lembrete_consulta';

    public const CATEGORIA_LEMBRETE_RETORNO = 'lembrete_retorno';

    public const CATEGORIA_CONFIRMACAO_CONSULTA = 'confirmacao_consulta';

    public const REMETENTE_PACIENTE = 'paciente';

    public const REMETENTE_EQUIPE = 'equipe';

    public const REMETENTE_SISTEMA = 'sistema';

    public const CANAL_WHATSAPP = 'whatsapp';

    protected $table = 'gestante_whatsapp';

    protected $fillable = [
        'gestante_id',
        'acompanhamento_id',
        'mensagem',
        'tipo',
        'categoria',
        'tempo_atendimento',
        'canal',
        'canal_identidade_id',
        'external_user_id',
        'external_chat_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tempo_atendimento' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function gestante(): BelongsTo
    {
        return $this->belongsTo(Gestante::class);
    }

    public function acompanhamento(): BelongsTo
    {
        return $this->belongsTo(Acompanhamento::class);
    }

    public function canalIdentidade(): BelongsTo
    {
        return $this->belongsTo(GestanteCanal::class, 'canal_identidade_id');
    }

    public function isLembrete(): bool
    {
        return in_array($this->categoria, [
            self::CATEGORIA_LEMBRETE_CONSULTA,
            self::CATEGORIA_LEMBRETE_RETORNO,
            self::CATEGORIA_CONFIRMACAO_CONSULTA,
        ], true);
    }

    public function remetente(): string
    {
        if ($this->tipo === 'entrada') {
            return self::REMETENTE_PACIENTE;
        }

        return $this->isLembrete() ? self::REMETENTE_SISTEMA : self::REMETENTE_EQUIPE;
    }

    public function remetenteLabel(): string
    {
        return match ($this->remetente()) {
            self::REMETENTE_PACIENTE => 'Paciente',
            self::REMETENTE_SISTEMA => 'Sistema',
            default => 'Equipe',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toConversaPayload(): array
    {
        return [
            'id' => $this->id,
            'mensagem' => $this->mensagem,
            'tipo' => $this->tipo,
            'remetente' => $this->remetente(),
            'remetente_label' => $this->remetenteLabel(),
            'categoria' => $this->categoria,
            'categoria_label' => self::labelCategoria($this->categoria),
            'acompanhamento_id' => $this->acompanhamento_id,
            'tempo_atendimento' => $this->tempo_atendimento,
            'tempo_atendimento_formatado' => $this->tempo_atendimento_formatado,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public static function labelCategoria(?string $categoria): string
    {
        return match ($categoria) {
            self::CATEGORIA_LEMBRETE_CONSULTA => 'Lembrete de consulta',
            self::CATEGORIA_LEMBRETE_RETORNO => 'Lembrete de retorno',
            self::CATEGORIA_CONFIRMACAO_CONSULTA => 'Confirmação de consulta',
            self::CATEGORIA_CONVERSA => 'Conversa',
            default => 'Conversa',
        };
    }

    /**
     * Vincula mensagens de conversa ao último lembrete enviado recentemente.
     */
    public static function acompanhamentoIdRecenteParaConversa(int $gestanteId): ?int
    {
        $id = static::query()
            ->where('gestante_id', $gestanteId)
            ->whereNotNull('acompanhamento_id')
            ->whereIn('categoria', [
                self::CATEGORIA_LEMBRETE_CONSULTA,
                self::CATEGORIA_LEMBRETE_RETORNO,
                self::CATEGORIA_CONFIRMACAO_CONSULTA,
            ])
            ->where('created_at', '>=', now()->subDays(2))
            ->orderByDesc('id')
            ->value('acompanhamento_id');

        return $id !== null ? (int) $id : null;
    }

    public function getTempoAtendimentoFormatadoAttribute(): ?string
    {
        if ($this->tempo_atendimento === null) {
            return null;
        }

        $s = (int) $this->tempo_atendimento;
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;

        if ($h > 0) {
            return sprintf('%dh %dm %ds', $h, $m, $sec);
        }
        if ($m > 0) {
            return sprintf('%dm %ds', $m, $sec);
        }

        return sprintf('%ds', $sec);
    }
}
