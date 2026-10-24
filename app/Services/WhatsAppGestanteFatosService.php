<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\AnaliseHistorico;
use App\Models\Consulta;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;
use App\Models\GestanteDocumento;
use Carbon\Carbon;

/**
 * Fonte de fatos do pré-natal para o WhatsApp.
 * Só devolve campos realmente persistidos — nunca completa ausências.
 */
class WhatsAppGestanteFatosService
{
    /**
     * @return list<array{nome:?string, data:?string, status:?string, local:?string}>
     */
    public function exames(Gestante $gestante): array
    {
        $out = [];

        $documentos = GestanteDocumento::query()
            ->where('gestante_id', $gestante->id)
            ->where('ativo', true)
            ->where('tipo', 'exame')
            ->orderByRaw('data_documento is null')
            ->orderByDesc('data_documento')
            ->orderByDesc('id')
            ->get();

        foreach ($documentos as $doc) {
            $item = $this->exameFromDocumento($doc);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        $analisesExame = AnaliseHistorico::query()
            ->where('gestante_id', $gestante->id)
            ->where('evento_tipo', AnaliseHistorico::EVENTO_EXAME)
            ->orderByDesc('id')
            ->get();

        foreach ($analisesExame as $analise) {
            $item = $this->exameFromAnalise($analise);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return list<array{tipo:?string, data:?string, horario:?string, local:?string, profissional:?string, idade_gestacional:?string}>
     */
    public function consultas(Gestante $gestante): array
    {
        $consultas = Consulta::query()
            ->where('gestante_id', $gestante->id)
            ->orderByDesc('data_consulta')
            ->orderByDesc('consulta_numero')
            ->orderByDesc('id')
            ->get();

        $out = [];
        foreach ($consultas as $consulta) {
            $item = $this->consultaToFact($consulta);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Exames e consultas futuras + acompanhamentos com data agendada.
     *
     * @return list<array{quando:Carbon, data:string, horario:?string, descricao:string}>
     */
    public function proximosCompromissos(Gestante $gestante): array
    {
        $hoje = Carbon::today(config('app.timezone'));
        $itens = [];

        $consultas = Consulta::query()
            ->where('gestante_id', $gestante->id)
            ->whereDate('data_consulta', '>=', $hoje->toDateString())
            ->orderBy('data_consulta')
            ->orderBy('consulta_numero')
            ->get();

        foreach ($consultas as $consulta) {
            if ($consulta->data_consulta === null) {
                continue;
            }
            $descricao = $this->descricaoConsulta($consulta);
            if ($descricao === '') {
                continue;
            }
            $itens[] = [
                'quando' => $consulta->data_consulta->copy()->startOfDay(),
                'data' => $consulta->data_consulta->format('d/m/Y'),
                'horario' => null,
                'descricao' => $descricao,
            ];
        }

        $documentos = GestanteDocumento::query()
            ->where('gestante_id', $gestante->id)
            ->where('ativo', true)
            ->where('tipo', 'exame')
            ->whereNotNull('data_documento')
            ->whereDate('data_documento', '>=', $hoje->toDateString())
            ->orderBy('data_documento')
            ->get();

        foreach ($documentos as $doc) {
            $nome = trim((string) $doc->titulo);
            if ($nome === '' || $doc->data_documento === null) {
                continue;
            }
            $itens[] = [
                'quando' => $doc->data_documento->copy()->startOfDay(),
                'data' => $doc->data_documento->format('d/m/Y'),
                'horario' => null,
                'descricao' => $nome,
            ];
        }

        $acompanhamentos = Acompanhamento::query()
            ->where('gestante_id', $gestante->id)
            ->whereNotNull('agendado_para')
            ->where('agendado_para', '>=', $hoje->copy()->startOfDay())
            ->orderBy('agendado_para')
            ->get();

        foreach ($acompanhamentos as $acomp) {
            if ($acomp->agendado_para === null) {
                continue;
            }
            $titulo = trim((string) $acomp->titulo);
            if ($titulo === '') {
                continue;
            }
            $quando = $acomp->agendado_para->timezone(config('app.timezone'));
            $itens[] = [
                'quando' => $quando,
                'data' => $quando->format('d/m/Y'),
                'horario' => $quando->format('H:i'),
                'descricao' => $titulo,
            ];
        }

        $agendamentos = ConsultaAgendamento::query()
            ->where('gestante_id', $gestante->id)
            ->where('status', ConsultaAgendamento::STATUS_AGENDADA)
            ->where('agendado_para', '>=', $hoje->copy()->startOfDay())
            ->orderBy('agendado_para')
            ->get();

        foreach ($agendamentos as $agendamento) {
            if ($agendamento->agendado_para === null) {
                continue;
            }
            $quando = $agendamento->agendado_para->timezone(config('app.timezone'));
            $descricao = 'Consulta agendada';
            if (filled($agendamento->profissional_nome)) {
                $descricao .= ' com '.$agendamento->profissional_nome;
            }
            $itens[] = [
                'quando' => $quando,
                'data' => $quando->format('d/m/Y'),
                'horario' => $quando->format('H:i'),
                'descricao' => $descricao,
            ];
        }

        usort($itens, function (array $a, array $b): int {
            return $a['quando']->timestamp <=> $b['quando']->timestamp;
        });

        return array_map(static function (array $item): array {
            return [
                'data' => $item['data'],
                'horario' => $item['horario'],
                'descricao' => $item['descricao'],
            ];
        }, $itens);
    }

    /**
     * @return array{nome:?string, data:?string, status:?string, local:?string}|null
     */
    private function exameFromDocumento(GestanteDocumento $doc): ?array
    {
        $nome = trim((string) $doc->titulo);
        $data = $doc->data_documento?->format('d/m/Y');

        if ($nome === '' && $data === null) {
            return null;
        }

        return [
            'nome' => $nome !== '' ? $nome : null,
            'data' => $data,
            'status' => null,
            'local' => null,
        ];
    }

    /**
     * @return array{nome:?string, data:?string, status:?string, local:?string}|null
     */
    private function exameFromAnalise(AnaliseHistorico $analise): ?array
    {
        $data = $analise->created_at?->timezone(config('app.timezone'))->format('d/m/Y');

        if ($data === null) {
            return null;
        }

        return [
            'nome' => null,
            'data' => $data,
            'status' => null,
            'local' => null,
        ];
    }

    /**
     * @return array{tipo:?string, data:?string, horario:?string, local:?string, profissional:?string, idade_gestacional:?string}|null
     */
    private function consultaToFact(Consulta $consulta): ?array
    {
        $tipo = $consulta->consulta_numero !== null
            ? 'Consulta '.$consulta->consulta_numero
            : null;
        $data = $consulta->data_consulta?->format('d/m/Y');
        $ig = $consulta->idade_gestacional !== null && $consulta->idade_gestacional !== ''
            ? (string) $consulta->idade_gestacional
            : null;

        if ($tipo === null && $data === null && $ig === null) {
            return null;
        }

        return [
            'tipo' => $tipo,
            'data' => $data,
            'horario' => null,
            'local' => null,
            'profissional' => null,
            'idade_gestacional' => $ig,
        ];
    }

    private function descricaoConsulta(Consulta $consulta): string
    {
        if ($consulta->consulta_numero !== null) {
            return 'Consulta '.$consulta->consulta_numero;
        }

        return $consulta->data_consulta ? 'Consulta' : '';
    }
}
