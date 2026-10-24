<?php

namespace App\Services;

use App\Contracts\RagContextBuilder;
use App\Models\AnaliseHistorico;
use App\Models\Gestante;
use App\Models\GestanteCanal;
use App\Models\GestanteDocumento;
use App\Models\GestanteDocumentoChunk;
use App\Models\GestanteWhatsapp;
use App\Models\InterpretacaoClinica;
use App\Support\WhatsAppFluxoEstado;
use Illuminate\Support\Str;

/**
 * RAG WhatsApp (fase 1–2): monta chunks a partir do prontuário Laravel
 * (consultas, análises, interpretações, documentos textuais) e ranqueia
 * por sobreposição lexical com a mensagem da gestante.
 *
 * Não inclui CPF/telefone/nome completo (minimização LGPD).
 */
class WhatsAppRagContextService implements RagContextBuilder
{
    public function __construct(
        private WhatsAppMessageTemplates $templates,
    ) {}

    /**
     * @return array{
     *   found: bool,
     *   gestante_id: int|null,
     *   gestante_id_publico: string|null,
     *   chunks: list<array{source: string, title: string, text: string, score: float}>,
     *   contexto_texto: string,
     *   total_fontes: int,
     *   pular_ia: bool,
     *   atendimento_humano: bool,
     *   ia_pausada_ate: string|null,
     *   menu_opcao: string|null,
     *   fluxo_estado: string|null,
     *   intencao: string,
     *   resumo_prenatal: string,
     *   nome: string|null
     * }
     */
    public function buildForTelefone(string $telefoneRaw, ?string $mensagem = null, int $topK = 6): array
    {
        $empty = [
            'found' => false,
            'gestante_id' => null,
            'gestante_id_publico' => null,
            'chunks' => [],
            'contexto_texto' => '',
            'total_fontes' => 0,
            'pular_ia' => false,
            'atendimento_humano' => false,
            'ia_pausada_ate' => null,
            'menu_opcao' => null,
            'fluxo_estado' => null,
            'intencao' => 'conversa_livre',
            'resumo_prenatal' => '',
            'nome' => null,
        ];

        $normalized = preg_replace('/\D/', '', $telefoneRaw) ?: '';
        if ($normalized === '') {
            return $empty;
        }

        $gestante = $this->findGestanteByTelefone($normalized);
        if ($gestante === null) {
            return $empty;
        }

        return $this->buildForGestante($gestante, $mensagem, $topK, GestanteCanal::CANAL_WHATSAPP);
    }

    /**
     * @return array{
     *   found: bool,
     *   gestante_id: int|null,
     *   gestante_id_publico: string|null,
     *   chunks: list<array{source: string, title: string, text: string, score: float}>,
     *   contexto_texto: string,
     *   total_fontes: int,
     *   pular_ia: bool,
     *   atendimento_humano: bool,
     *   ia_pausada_ate: string|null,
     *   menu_opcao: string|null,
     *   fluxo_estado: string|null,
     *   intencao: string,
     *   resumo_prenatal: string,
     *   nome: string|null
     * }
     */
    public function buildForGestante(Gestante $gestante, ?string $mensagem = null, int $topK = 6, string $canal = GestanteCanal::CANAL_WHATSAPP): array
    {
        $fluxoEstado = $gestante->whatsapp_fluxo_estado;
        $menuOpcao = $gestante->whatsapp_menu_opcao;
        $modoSintoma = $fluxoEstado === WhatsAppFluxoEstado::CONVERSA_SINTOMA || $menuOpcao === 'sintoma';
        $intencaoLivre = $this->templates->matchIntencaoLivre($mensagem);
        $opcaoMenu = $this->templates->matchMenuOpcao($mensagem) ?: $menuOpcao;
        $usarProntuario = in_array($intencaoLivre, ['exames_dados', 'consultas_dados', 'compromissos'], true);

        $chunks = [];
        $ranked = [];
        if ($usarProntuario) {
            $chunks = $this->collectChunks($gestante, $canal);
            $ranked = $this->rankChunks($chunks, $mensagem, $topK);
        }

        $linhas = [];
        foreach ($ranked as $i => $c) {
            $n = $i + 1;
            $linhas[] = "[{$n}] ({$c['source']}) {$c['title']}: {$c['text']}";
        }

        $contexto = $linhas === []
            ? 'Nenhum documento clínico indexado para esta gestante.'
            : "Documentos recuperados do prontuário (use apenas se forem relevantes; não invente):\n".implode("\n", $linhas);

        $pularIa = $gestante->whatsappIaPausada();
        $ctx = $this->templates->contextFromGestante($gestante);
        $resumoPrenatal = $this->montarResumoPrenatal($ctx);
        $historicoConversa = $this->montarHistoricoConversa($gestante, $canal);

        $regras = <<<'TXT'
REGRAS OBRIGATÓRIAS (não viole):
- Nunca invente informações da gestante, exames, resultados, consultas, datas, horários, profissionais ou locais.
- Nunca afirme que uma informação existe se ela não estiver neste contexto.
- Não indique medicamentos nem faça diagnóstico.
- Se o dado não estiver aqui, diga que a informação não está disponível no sistema e ofereça encaminhamento à equipe.
- Não complete lacunas com conhecimento médico geral. Não altere dados retornados.
TXT;

        if ($usarProntuario) {
            $contexto = $regras."\n\nA gestante pediu informações de consulta, exame ou compromisso. Use apenas os registros abaixo; não invente datas nem resultados.\n\n".$resumoPrenatal."\n\n".$contexto;
        } elseif ($modoSintoma || $intencaoLivre === 'sintoma') {
            $contexto = $this->templates->instrucoesConversaSintoma()
                ."\n\n".$resumoPrenatal
                ."\n\n".$historicoConversa;
        } elseif ($menuOpcao === 'atendente') {
            $contexto = "A gestante pediu falar com atendente. Não responda a dúvida médica; o backend registra a solicitação.";
        } elseif ($menuOpcao === 'medico') {
            $contexto = "A gestante pediu falar com o médico. Não avalie clinicamente; o backend registra a solicitação.";
        } else {
            $contexto = "Converse de forma natural e acolhedora. ".$resumoPrenatal."\n\n".$historicoConversa;
        }

        $intencao = match ($intencaoLivre ?? $opcaoMenu) {
            'atendente' => 'atendimento_humano',
            'medico', 'interpretacao' => 'falar_medico',
            'exames', 'exames_dados', 'consultas_dados', 'compromissos' => 'exames_consultas',
            default => 'conversa_livre',
        };
        if (($modoSintoma || $intencaoLivre === 'sintoma') && ! $usarProntuario) {
            $intencao = 'conversa_livre';
        }

        $estadosComRespostaPronta = [
            WhatsAppFluxoEstado::ATENDENTE,
            WhatsAppFluxoEstado::ATENDENTE_CATEGORIA,
            WhatsAppFluxoEstado::EXAMES_CONSULTAS,
            WhatsAppFluxoEstado::MEUS_EXAMES,
            WhatsAppFluxoEstado::MINHAS_CONSULTAS,
            WhatsAppFluxoEstado::PROXIMOS_COMPROMISSOS,
            WhatsAppFluxoEstado::FALAR_COM_MEDICO,
            WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO,
        ];

        return [
            'found' => true,
            'gestante_id' => $gestante->id,
            'gestante_id_publico' => $gestante->gestante_id ?: ('G-'.$gestante->id),
            'chunks' => $ranked,
            'contexto_texto' => $contexto,
            'resumo_prenatal' => $resumoPrenatal,
            'nome' => $ctx['primeiro_nome'] ?: null,
            'total_fontes' => count($chunks),
            'pular_ia' => $pularIa || (
                ! $modoSintoma && in_array($fluxoEstado, $estadosComRespostaPronta, true)
            ),
            'atendimento_humano' => $pularIa,
            'ia_pausada_ate' => $pularIa
                ? $gestante->whatsapp_ia_pausada_ate?->toIso8601String()
                : null,
            'menu_opcao' => $menuOpcao,
            'fluxo_estado' => $fluxoEstado,
            'intencao' => $intencao,
        ];
    }

    /**
     * Indexa texto livre em chunks (~500–700 chars) para retrieval.
     */
    public function indexDocumentoTexto(
        Gestante $gestante,
        string $titulo,
        string $conteudoTexto,
        string $tipo = 'outro',
        ?string $fonte = 'manual',
        ?string $dataDocumento = null,
    ): GestanteDocumento {
        $doc = GestanteDocumento::query()->create([
            'gestante_id' => $gestante->id,
            'titulo' => Str::limit(trim($titulo), 255, ''),
            'tipo' => $tipo,
            'fonte' => $fonte,
            'data_documento' => $dataDocumento,
            'conteudo_texto' => $conteudoTexto,
            'ativo' => true,
        ]);

        $parts = $this->splitIntoChunks($conteudoTexto, 650, 80);
        foreach ($parts as $i => $part) {
            GestanteDocumentoChunk::query()->create([
                'gestante_documento_id' => $doc->id,
                'gestante_id' => $gestante->id,
                'chunk_index' => $i,
                'conteudo' => $part,
            ]);
        }

        return $doc->load('chunks');
    }

    /**
     * @return list<array{source: string, title: string, text: string, score: float}>
     */
    private function collectChunks(Gestante $gestante, string $canal = GestanteCanal::CANAL_WHATSAPP): array
    {
        $out = [];

        $consultas = $gestante->consultas()
            ->orderByDesc('consulta_numero')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        foreach ($consultas as $c) {
            $bits = array_filter([
                $c->data_consulta ? 'data '.$c->data_consulta->format('Y-m-d') : null,
                $c->idade_gestacional !== null ? 'IG '.$c->idade_gestacional.' sem' : null,
                $c->idade_materna !== null ? 'idade materna '.$c->idade_materna : null,
                $c->pressao_sistolica !== null ? 'PA sistólica '.$c->pressao_sistolica : null,
                $c->bpm_materno !== null ? 'FC materna '.$c->bpm_materno : null,
                $c->glicemia_jejum !== null ? 'glicemia jejum '.$c->glicemia_jejum : null,
                $c->glicemia_pos_prandial !== null ? 'glicemia pós '.$c->glicemia_pos_prandial : null,
                $c->hba1c !== null ? 'HbA1c '.$c->hba1c : null,
                $c->peso !== null ? 'peso '.$c->peso : null,
                $c->altura !== null ? 'altura '.$c->altura : null,
                $c->diabetes_gestacional ? 'diabetes gestacional' : null,
                $c->hipertensao ? 'hipertensão registrada' : null,
                $c->hipertensao_cronica ? 'hipertensão crônica' : null,
                $c->hipertensao_pre_eclampsia ? 'pré-eclâmpsia' : null,
                $c->saturacao !== null ? 'saturação '.$c->saturacao : null,
            ]);
            if ($bits === []) {
                continue;
            }
            $out[] = [
                'source' => 'consulta',
                'title' => 'Consulta #'.($c->consulta_numero ?? $c->id),
                'text' => implode('; ', $bits),
                'score' => 0.0,
            ];
        }

        $analise = AnaliseHistorico::query()
            ->where('gestante_id', $gestante->id)
            ->where('status', 'concluida')
            ->latest('id')
            ->first();

        if ($analise) {
            $bits = array_filter([
                $analise->classificacao_risco ? 'risco '.$analise->classificacao_risco : null,
                $analise->probabilidade_ccf !== null ? 'probabilidade CCF '.$analise->probabilidade_ccf : null,
                $analise->recomenda_ecocardiograma ? 'recomenda ecocardiograma' : null,
                $analise->prioridade_ecocardiograma ? 'prioridade eco '.$analise->prioridade_ecocardiograma : null,
            ]);
            if ($bits !== []) {
                $out[] = [
                    'source' => 'analise',
                    'title' => 'Última análise de risco',
                    'text' => implode('; ', $bits),
                    'score' => 0.0,
                ];
            }
        }

        $interp = InterpretacaoClinica::query()
            ->where('gestante_id', $gestante->id)
            ->whereIn('status', [
                InterpretacaoClinica::STATUS_CONCLUIDA,
                InterpretacaoClinica::STATUS_CACHE_HIT,
            ])
            ->latest('id')
            ->first();

        if ($interp && filled($interp->resumo_gestante)) {
            $out[] = [
                'source' => 'interpretacao',
                'title' => 'Última interpretação (linguagem gestante)',
                'text' => Str::limit((string) $interp->resumo_gestante, 700, '…'),
                'score' => 0.0,
            ];
        }

        $docChunks = GestanteDocumentoChunk::query()
            ->where('gestante_id', $gestante->id)
            ->whereHas('documento', fn ($q) => $q->where('ativo', true))
            ->with('documento:id,titulo,tipo')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($docChunks as $chunk) {
            $out[] = [
                'source' => 'documento:'.($chunk->documento->tipo ?? 'outro'),
                'title' => (string) ($chunk->documento->titulo ?? 'Documento'),
                'text' => Str::limit((string) $chunk->conteudo, 800, '…'),
                'score' => 0.0,
            ];
        }

        $msgs = $this->mensagensDoCanal($gestante, $canal, 12);

        foreach ($msgs as $m) {
            $out[] = [
                'source' => 'whatsapp_'.$m->tipo,
                'title' => 'Mensagem anterior ('.$m->tipo.')',
                'text' => Str::limit((string) $m->mensagem, 280, '…'),
                'score' => 0.0,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{source: string, title: string, text: string, score: float}>  $chunks
     * @return list<array{source: string, title: string, text: string, score: float}>
     */
    private function rankChunks(array $chunks, ?string $mensagem, int $topK): array
    {
        $tokens = $this->tokenize($mensagem ?? '');
        if ($tokens === [] || $chunks === []) {
            return array_slice($chunks, 0, $topK);
        }

        foreach ($chunks as &$c) {
            $hay = mb_strtolower($c['title'].' '.$c['text'], 'UTF-8');
            $score = 0.0;
            foreach ($tokens as $t) {
                if (str_contains($hay, $t)) {
                    $score += 1.0;
                }
            }
            // leve boost para fontes clínicas estruturadas
            if (str_starts_with($c['source'], 'consulta') || $c['source'] === 'analise') {
                $score += 0.25;
            }
            if (str_starts_with($c['source'], 'whatsapp_')) {
                $score += 0.4;
            }
            if (str_starts_with($c['source'], 'documento')) {
                $score += 0.15;
            }
            $c['score'] = $score;
        }
        unset($c);

        usort($chunks, fn ($a, $b) => $b['score'] <=> $a['score']);

        $filtered = array_values(array_filter($chunks, fn ($c) => $c['score'] > 0));
        if ($filtered === []) {
            return array_slice($chunks, 0, $topK);
        }

        return array_slice($filtered, 0, $topK);
    }

    /**
     * @return list<string>
     */
    private function tokenize(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stop = [
            'a', 'o', 'e', 'de', 'da', 'do', 'em', 'um', 'uma', 'para', 'com', 'por', 'que',
            'meu', 'minha', 'eu', 'oi', 'ola', 'olá', 'sim', 'nao', 'não', 'por', 'favor',
        ];

        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p, 'UTF-8') < 3) {
                continue;
            }
            if (in_array($p, $stop, true)) {
                continue;
            }
            $out[] = $p;
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function splitIntoChunks(string $text, int $size, int $overlap): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return [];
        }

        $len = mb_strlen($text, 'UTF-8');
        if ($len <= $size) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        while ($start < $len) {
            $chunks[] = mb_substr($text, $start, $size, 'UTF-8');
            $start += max(1, $size - $overlap);
            if (count($chunks) > 80) {
                break;
            }
        }

        return $chunks;
    }

    private function findGestanteByTelefone(string $normalized): ?Gestante
    {
        $candidates = [$normalized];

        if (strlen($normalized) > 11) {
            $candidates[] = substr($normalized, -11);
        }

        if (str_starts_with($normalized, '55') && strlen($normalized) >= 12) {
            $candidates[] = substr($normalized, 2);
        }

        if (strlen($normalized) === 11) {
            $candidates[] = '55'.$normalized;
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        return Gestante::query()->whereIn('telefone', $candidates)->first();
    }

    /**
     * Garante consulta recente e histórico do WhatsApp no contexto da conversa de sintoma.
     *
     * @param  list<array{source: string, title: string, text: string, score: float}>  $todos
     * @param  list<array{source: string, title: string, text: string, score: float}>  $ranked
     * @return list<array{source: string, title: string, text: string, score: float}>
     */
    private function garantirContextoConversa(array $todos, array $ranked, int $topK): array
    {
        $consultas = array_values(array_filter($todos, fn (array $c): bool => $c['source'] === 'consulta'));
        $whatsapp = array_values(array_filter($todos, fn (array $c): bool => str_starts_with($c['source'], 'whatsapp_')));
        $obrigatorios = array_merge(array_slice($consultas, 0, 2), $whatsapp);

        $vistos = [];
        $out = [];
        foreach (array_merge($obrigatorios, $ranked) as $c) {
            $chave = $c['source'].'|'.$c['title'].'|'.$c['text'];
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;
            $out[] = $c;
            if (count($out) >= max($topK, count($obrigatorios))) {
                break;
            }
        }

        return $out;
    }

    private function montarHistoricoConversa(Gestante $gestante, string $canal = GestanteCanal::CANAL_WHATSAPP): string
    {
        $msgs = $this->mensagensDoCanal($gestante, $canal, 12);

        if ($msgs->isEmpty()) {
            return 'Ainda não há histórico de mensagens desta conversa.';
        }

        $linhas = ['Histórico recente desta conversa (não invente falas que não estejam aqui):'];
        foreach ($msgs as $m) {
            $quem = $m->tipo === 'entrada' ? 'Gestante' : 'Assistente';
            $linhas[] = '- '.$quem.': '.Str::limit((string) $m->mensagem, 280, '…');
        }

        return implode("\n", $linhas);
    }

    /**
     * @return \Illuminate\Support\Collection<int, GestanteWhatsapp>
     */
    private function mensagensDoCanal(Gestante $gestante, string $canal, int $limit)
    {
        return GestanteWhatsapp::query()
            ->where('gestante_id', $gestante->id)
            ->where(function ($q) use ($canal) {
                $q->where('canal', $canal);
                if ($canal === GestanteCanal::CANAL_WHATSAPP) {
                    $q->orWhereNull('canal');
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function montarResumoPrenatal(array $ctx): string
    {
        $linhas = ['Dados do acompanhamento (só o que está cadastrado):'];
        $nome = trim((string) ($ctx['primeiro_nome'] ?? ''));
        if ($nome !== '') {
            $linhas[] = '- Primeiro nome: '.$nome;
        }
        if (! empty($ctx['idade_gestacional'])) {
            $linhas[] = '- Idade gestacional na última consulta: '.$ctx['idade_gestacional'].' semanas';
        }
        if (! empty($ctx['data_ultima_consulta'])) {
            $linhas[] = '- Data da última consulta: '.$ctx['data_ultima_consulta'];
        }
        if (! empty($ctx['data_agendada'])) {
            $linhas[] = '- Compromisso agendado: '.$ctx['data_agendada'];
        }
        if (isset($ctx['pressao_sistolica']) && $ctx['pressao_sistolica'] !== null && $ctx['pressao_sistolica'] !== '') {
            $linhas[] = '- Pressão sistólica na última consulta: '.$ctx['pressao_sistolica'];
        }
        if (isset($ctx['bpm_materno']) && $ctx['bpm_materno'] !== null && $ctx['bpm_materno'] !== '') {
            $linhas[] = '- Frequência cardíaca materna na última consulta: '.$ctx['bpm_materno'];
        }
        if (isset($ctx['peso']) && $ctx['peso'] !== null && $ctx['peso'] !== '') {
            $linhas[] = '- Peso na última consulta: '.$ctx['peso'];
        }
        if (isset($ctx['saturacao']) && $ctx['saturacao'] !== null && $ctx['saturacao'] !== '') {
            $linhas[] = '- Saturação na última consulta: '.$ctx['saturacao'];
        }
        if (count($linhas) === 1) {
            return 'Não há resumo de consulta cadastrado além dos trechos abaixo.';
        }

        $linhas[] = 'Não mencione classificação de risco de modelo de ML. Não invente campos ausentes.';

        return implode("\n", $linhas);
    }
}
