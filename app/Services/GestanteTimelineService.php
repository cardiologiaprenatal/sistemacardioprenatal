<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\Alerta;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GestanteTimelineService
{
    /**
     * Timeline clínica unificada (mais recente primeiro).
     *
     * @return Collection<int, array{tipo: string, titulo: string, descricao: ?string, ocorrido_em: Carbon, severidade: ?string, meta: array}>
     */
    public function forGestante(Gestante $gestante, bool $incluirClinico = true, bool $incluirWhatsapp = true): Collection
    {
        $relacoes = [
            'consultas',
            'acompanhamentos',
            'alertas',
            'consultaAgendamentos',
        ];
        if ($incluirClinico) {
            $relacoes[] = 'analisesHistorico';
            $relacoes[] = 'interpretacoesClinicas';
        }
        if ($incluirWhatsapp) {
            $relacoes[] = 'gestanteWhatsapps';
        }

        $gestante->loadMissing($relacoes);

        $events = collect();

        foreach ($gestante->consultas as $consulta) {
            $quando = $consulta->data_consulta
                ? Carbon::parse($consulta->data_consulta)->startOfDay()
                : ($consulta->created_at ?? now());

            $parts = array_filter([
                $consulta->idade_gestacional ? $consulta->idade_gestacional.' sem' : null,
                $consulta->idade_materna ? $consulta->idade_materna.' anos' : null,
                $consulta->chd_confirmada ? 'CHD confirmada' : null,
            ]);

            $events->push([
                'tipo' => 'consulta',
                'titulo' => 'Consulta nº '.$consulta->consulta_numero,
                'descricao' => $parts !== [] ? implode(' · ', $parts) : null,
                'ocorrido_em' => $quando,
                'severidade' => $consulta->chd_confirmada ? 'urgente' : null,
                'meta' => ['consulta_id' => $consulta->id],
            ]);
        }

        foreach ($gestante->consultaAgendamentos as $agendamento) {
            /** @var ConsultaAgendamento $agendamento */
            $quando = $agendamento->agendado_para
                ? $agendamento->agendado_para->timezone(config('app.timezone'))
                : ($agendamento->created_at ?? now());

            $parts = array_filter([
                $agendamento->dataFormatada().' às '.$agendamento->horarioFormatado(),
                $agendamento->profissional_nome ?: null,
                'Status: '.$agendamento->labelStatus(),
            ]);

            $events->push([
                'tipo' => 'agendamento',
                'titulo' => 'Consulta agendada',
                'descricao' => implode(' · ', $parts),
                'ocorrido_em' => $agendamento->created_at ?? $quando,
                'severidade' => 'info',
                'meta' => [
                    'consulta_agendamento_id' => $agendamento->id,
                    'status' => $agendamento->status,
                ],
            ]);
        }

        foreach ($incluirClinico ? $gestante->analisesHistorico : [] as $analise) {
            $risco = $analise->classificacao_risco;
            $desc = $analise->status === 'erro'
                ? ($analise->erro_mensagem ?: 'Falha na análise')
                : trim(
                    DashboardAnaliseService::labelRisco($risco)
                    .($analise->probabilidade_ccf !== null
                        ? ' · '.number_format($analise->probabilidade_ccf * 100, 1, ',', '.').'% de cardiopatia congênita'
                        : '')
                    .($analise->recomenda_ecocardiograma
                        ? ' · Ecocardiograma '.DashboardAnaliseService::labelPrioridadeEco($analise->prioridade_ecocardiograma)
                        : '')
                );

            $events->push([
                'tipo' => 'analise',
                'titulo' => 'Análise de risco',
                'descricao' => $desc !== '' ? $desc : null,
                'ocorrido_em' => $analise->created_at ?? now(),
                'severidade' => $analise->status === 'erro'
                    ? 'urgente'
                    : ($risco === 'alto' ? 'urgente' : ($risco === 'moderado' ? 'atencao' : 'info')),
                'meta' => ['analise_id' => $analise->id, 'risco' => $risco],
            ]);
        }

        foreach ($incluirClinico ? $gestante->interpretacoesClinicas : [] as $interp) {
            if (! in_array($interp->status, ['concluida', 'cache_hit'], true)) {
                continue;
            }
            $resumo = $interp->resumo_medico ?: $interp->resumo_gestante;
            if (is_string($resumo) && mb_strlen($resumo) > 160) {
                $resumo = mb_substr($resumo, 0, 157).'…';
            }

            $events->push([
                'tipo' => 'interpretacao',
                'titulo' => 'Laudo clínico (IA)',
                'descricao' => is_string($resumo) && $resumo !== '' ? $resumo : null,
                'ocorrido_em' => $interp->updated_at ?? $interp->created_at ?? now(),
                'severidade' => 'info',
                'meta' => ['interpretacao_id' => $interp->id],
            ]);
        }

        foreach ($incluirWhatsapp ? $gestante->gestanteWhatsapps : [] as $msg) {
            /** @var GestanteWhatsapp $msg */
            $texto = trim((string) ($msg->mensagem ?? ''));
            if (mb_strlen($texto) > 140) {
                $texto = mb_substr($texto, 0, 137).'…';
            }
            $tipoLabel = ($msg->tipo ?? '') === 'saida' ? 'WhatsApp enviado' : 'WhatsApp recebido';

            $events->push([
                'tipo' => 'whatsapp',
                'titulo' => $tipoLabel,
                'descricao' => $texto !== '' ? $texto : null,
                'ocorrido_em' => $msg->created_at ?? now(),
                'severidade' => null,
                'meta' => ['whatsapp_id' => $msg->id],
            ]);
        }

        foreach ($gestante->acompanhamentos as $acomp) {
            /** @var Acompanhamento $acomp */
            $events->push([
                'tipo' => 'acompanhamento',
                'titulo' => $acomp->titulo,
                'descricao' => trim('Status: '.$this->labelStatus($acomp->status).($acomp->observacao ? '. '.$acomp->observacao : '')),
                'ocorrido_em' => $acomp->created_at ?? now(),
                'severidade' => $acomp->prioridade === 'urgente' || $acomp->prioridade === 'alta' ? 'urgente' : 'atencao',
                'meta' => ['acompanhamento_id' => $acomp->id, 'status' => $acomp->status],
            ]);
        }

        foreach ($gestante->alertas as $alerta) {
            /** @var Alerta $alerta */
            $events->push([
                'tipo' => 'alerta',
                'titulo' => $alerta->titulo,
                'descricao' => $alerta->mensagem,
                'ocorrido_em' => $alerta->created_at ?? now(),
                'severidade' => $alerta->severidade,
                'meta' => [
                    'alerta_id' => $alerta->id,
                    'aberto' => $alerta->estaAberto(),
                ],
            ]);
        }

        return $events
            ->sortByDesc(fn (array $e) => $e['ocorrido_em']->getTimestamp())
            ->values();
    }

    public function labelStatus(string $status): string
    {
        return match ($status) {
            Acompanhamento::STATUS_PENDENTE => 'Pendente',
            Acompanhamento::STATUS_EM_ANDAMENTO => 'Em andamento',
            Acompanhamento::STATUS_CONCLUIDO => 'Concluído',
            Acompanhamento::STATUS_CANCELADO => 'Cancelado',
            default => $status,
        };
    }

    public function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            'consulta' => 'Consulta',
            'agendamento' => 'Agendamento',
            'analise' => 'Análise',
            'interpretacao' => 'Laudo IA',
            'whatsapp' => 'WhatsApp',
            'acompanhamento' => 'Acompanhamento',
            'alerta' => 'Alerta',
            default => $tipo,
        };
    }
}
