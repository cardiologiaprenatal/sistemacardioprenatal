<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gestante extends Model
{
    protected $fillable = [
        'nome',
        'data_nascimento',
        'cpf',
        'telefone',
        'email',
        'whatsapp_ia_pausada_ate',
        'whatsapp_menu_opcao',
        'whatsapp_fluxo_estado',
        'fluxo_atendimento',
        'atendimento_personalizado_aceito_em',
        'atendimento_personalizado_aceito_por',
    ];

    public const FLUXO_AGUARDANDO_CONSULTA = 'aguardando_consulta';

    public const FLUXO_CONSULTA_REALIZADA = 'consulta_realizada';

    public const FLUXO_ATENDIMENTO_PERSONALIZADO = 'atendimento_personalizado';

    public const FLUXO_AGUARDANDO_ECOCARDIOGRAMA = 'aguardando_ecocardiograma';

    public const FLUXO_ECOCARDIOGRAMA_AGENDADO = 'ecocardiograma_agendado';

    public const FLUXO_ECOCARDIOGRAMA_REALIZADO = 'ecocardiograma_realizado';

    protected function casts(): array
    {
        return [
            'whatsapp_ia_pausada_ate' => 'datetime',
            'atendimento_personalizado_aceito_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Gestante $gestante) {
            $gestante->forceFill(['gestante_id' => (string) $gestante->id])->saveQuietly();
        });
    }

    /**
     * Nome para exibição (cadastro antigo pode não ter nome preenchido).
     */
    public function getNomeExibicaoAttribute(): string
    {
        $n = $this->nome;

        return ($n !== null && trim($n) !== '') ? trim($n) : (string) ($this->gestante_id ?? '');
    }

    /**
     * CPF armazenado somente com dígitos (11) ou null.
     */
    public function getCpfFormatadoAttribute(): ?string
    {
        if (! $this->cpf || strlen($this->cpf) !== 11) {
            return $this->cpf;
        }

        return substr($this->cpf, 0, 3).'.'.substr($this->cpf, 3, 3).'.'.substr($this->cpf, 6, 3).'-'.substr($this->cpf, 9, 2);
    }

    /**
     * Telefone em dígitos (DDD + número ou 55…); exibição amigável.
     */
    public function getTelefoneFormatadoAttribute(): ?string
    {
        if (! $this->telefone) {
            return null;
        }

        $d = preg_replace('/\D/', '', $this->telefone);
        if (strlen($d) === 11) {
            return '('.substr($d, 0, 2).') '.substr($d, 2, 5).'-'.substr($d, 7, 4);
        }
        if (strlen($d) === 13 && str_starts_with($d, '55')) {
            return '+55 ('.substr($d, 2, 2).') '.substr($d, 4, 5).'-'.substr($d, 9, 4);
        }
        if (strlen($d) === 10) {
            return '('.substr($d, 0, 2).') '.substr($d, 2, 4).'-'.substr($d, 6, 4);
        }

        return $this->telefone;
    }

    public function whatsappIaPausada(): bool
    {
        return $this->whatsapp_ia_pausada_ate !== null
            && $this->whatsapp_ia_pausada_ate->isFuture();
    }

    public function consultas()
    {
        return $this->hasMany(Consulta::class, 'gestante_id');
    }

    public function documentos()
    {
        return $this->hasMany(GestanteDocumento::class);
    }

    public function consultaAgendamentos()
    {
        return $this->hasMany(ConsultaAgendamento::class, 'gestante_id')->orderByDesc('agendado_para');
    }

    public function agendamentoSolicitacoes()
    {
        return $this->hasMany(AgendamentoSolicitacao::class, 'gestante_id')->orderByDesc('created_at');
    }

    public function analisesHistorico()
    {
        return $this->hasMany(AnaliseHistorico::class, 'gestante_id')->orderByDesc('created_at');
    }

    public function ultimaAnalise()
    {
        return $this->hasOne(AnaliseHistorico::class, 'gestante_id')->latestOfMany();
    }

    public function gestanteWhatsapps()
    {
        return $this->hasMany(GestanteWhatsapp::class, 'gestante_id');
    }

    public function canais()
    {
        return $this->hasMany(GestanteCanal::class, 'gestante_id');
    }

    public function gestanteEmails()
    {
        return $this->hasMany(GestanteEmail::class, 'gestante_id');
    }

    public function interpretacoesClinicas()
    {
        return $this->hasMany(InterpretacaoClinica::class, 'gestante_id')->orderByDesc('created_at');
    }

    public function ultimaInterpretacao()
    {
        return $this->hasOne(InterpretacaoClinica::class, 'gestante_id')->latestOfMany();
    }

    public function acompanhamentos()
    {
        return $this->hasMany(Acompanhamento::class, 'gestante_id')->orderByDesc('created_at');
    }

    public function alertas()
    {
        return $this->hasMany(Alerta::class, 'gestante_id')->orderByDesc('created_at');
    }

    public function aceiteAtendimentoPor()
    {
        return $this->belongsTo(User::class, 'atendimento_personalizado_aceito_por');
    }

    public function atendimentoPersonalizadoLiberado(): bool
    {
        return $this->atendimento_personalizado_aceito_em !== null;
    }

    public function acompanhamentoEcoAberto(): ?Acompanhamento
    {
        return $this->acompanhamentos
            ->first(fn (Acompanhamento $item) => $item->motivo === Acompanhamento::MOTIVO_ECO_RECOMENDADA && $item->estaAberto());
    }

    public function encaminhadaParaFilaEco(): bool
    {
        $itens = $this->relationLoaded('acompanhamentos')
            ? $this->acompanhamentos
            : $this->acompanhamentos()->get();

        return $itens->contains(
            fn (Acompanhamento $item) => $item->motivo === Acompanhamento::MOTIVO_ECO_RECOMENDADA
                && $item->encaminhado_por_user_id
        );
    }

    public function labelFluxoAtendimento(): string
    {
        return match ($this->fluxo_atendimento) {
            self::FLUXO_CONSULTA_REALIZADA => 'Consulta realizada',
            self::FLUXO_ATENDIMENTO_PERSONALIZADO => 'Atendimento personalizado autorizado',
            self::FLUXO_AGUARDANDO_ECOCARDIOGRAMA => 'Aguardando ecocardiograma',
            self::FLUXO_ECOCARDIOGRAMA_AGENDADO => 'Ecocardiograma agendado',
            self::FLUXO_ECOCARDIOGRAMA_REALIZADO => 'Ecocardiograma realizado',
            default => 'Aguardando consulta',
        };
    }
}
