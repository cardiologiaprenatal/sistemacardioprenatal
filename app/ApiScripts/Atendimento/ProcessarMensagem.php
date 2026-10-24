<?php

namespace App\ApiScripts\Atendimento;

use App\ApiScripts\Llm\LlmGateway;
use App\ApiScripts\Rag\RecuperarContextoGestante;
use App\ApiScripts\Tools\ToolRegistry;
use App\Contracts\AtendimentoMenuFlow;
use App\Contracts\MessagingChannel;
use App\Models\Gestante;
use App\Models\GestanteCanal;
use App\Services\GestanteWhatsappMessageRecorder;
use App\Services\Messaging\CanalIdentidadeService;
use App\Services\Messaging\IncomingMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestração de atendimento independente do canal.
 * Menu, RAG, LLM e tools entram aqui; o canal só adapta entrada e saída.
 */
class ProcessarMensagem
{
    public function __construct(
        private CanalIdentidadeService $identidades,
        private GestanteWhatsappMessageRecorder $recorder,
        private AtendimentoMenuFlow $menu,
        private RecuperarContextoGestante $rag,
        private LlmGateway $llm,
        private ToolRegistry $tools,
    ) {}

    public function handle(IncomingMessage $mensagem, MessagingChannel $canal): AtendimentoResultado
    {
        $inicio = hrtime(true);
        $gestanteId = null;
        $intencao = null;
        $fontesRag = 0;
        $ferramenta = null;
        $enviado = false;
        $modelo = null;

        Log::info('ai.message.received', [
            'canal' => $mensagem->canal,
            'tem_texto' => $mensagem->texto !== '',
            'update_id' => $mensagem->metadata['update_id'] ?? null,
        ]);

        $identidade = $this->identidades->identificar($mensagem);

        if (trim($mensagem->texto) === '') {
            $enviado = $this->enviar(
                $canal,
                $mensagem,
                $identidade,
                $identidade->gestante,
                'Por enquanto só consigo ler mensagens de texto. Envie sua dúvida em texto, por favor.',
            );

            return $this->resultado(
                fluxo: 'texto_obrigatorio',
                canal: $mensagem->canal,
                gestanteId: $identidade->gestante_id,
                intencao: 'texto_obrigatorio',
                modelo: null,
                fontesRag: 0,
                ferramenta: null,
                enviado: $enviado,
                inicio: $inicio,
            );
        }

        $this->gravar($mensagem, $identidade, $identidade->gestante, $mensagem->texto, 'entrada');

        $vinculouAgora = false;
        if ($identidade->gestante_id === null) {
            $vinculada = $this->identidades->tentarVincularPorTelefone($identidade, $mensagem->texto);
            if ($vinculada === null) {
                $enviado = $this->enviar(
                    $canal,
                    $mensagem,
                    $identidade,
                    null,
                    'Não identifiquei seu cadastro neste canal. Envie o telefone usado no pré-natal (com DDD) para continuarmos o atendimento.',
                );

                return $this->resultado(
                    fluxo: 'aguardando_vinculo',
                    canal: $mensagem->canal,
                    gestanteId: null,
                    intencao: 'vinculo_telefone',
                    modelo: null,
                    fontesRag: 0,
                    ferramenta: null,
                    enviado: $enviado,
                    inicio: $inicio,
                );
            }
            $identidade = $vinculada;
            $vinculouAgora = true;
        }

        $gestante = $identidade->gestante?->fresh() ?? $identidade->gestante;
        if ($gestante === null) {
            $enviado = $this->enviar(
                $canal,
                $mensagem,
                $identidade,
                null,
                'Não identifiquei seu cadastro neste canal. Envie o telefone usado no pré-natal (com DDD) para continuarmos o atendimento.',
            );

            return $this->resultado(
                fluxo: 'aguardando_vinculo',
                canal: $mensagem->canal,
                gestanteId: null,
                intencao: 'vinculo_telefone',
                modelo: null,
                fontesRag: 0,
                ferramenta: null,
                enviado: $enviado,
                inicio: $inicio,
            );
        }

        $gestanteId = $gestante->id;

        if ($gestante->whatsappIaPausada()) {
            Log::info('ai.skipped.human', [
                'canal' => $mensagem->canal,
                'gestante_id' => $gestante->id,
            ]);

            return $this->resultado(
                fluxo: 'ia_pausada',
                canal: $mensagem->canal,
                gestanteId: $gestanteId,
                intencao: 'atendimento_humano',
                modelo: null,
                fontesRag: 0,
                ferramenta: null,
                enviado: false,
                inicio: $inicio,
            );
        }

        if ($vinculouAgora) {
            $fluxo = $this->menu->irMenuInicial($gestante);
            $enviado = $this->enviar($canal, $mensagem, $identidade, $gestante, $fluxo['resposta'] ?: 'Olá! Como posso ajudar?');

            return $this->resultado(
                fluxo: 'menu',
                canal: $mensagem->canal,
                gestanteId: $gestanteId,
                intencao: $fluxo['intencao'] ?? 'menu',
                modelo: null,
                fontesRag: 0,
                ferramenta: 'menu_inicial',
                enviado: $enviado,
                inicio: $inicio,
            );
        }

        $texto = $this->normalizarComando($mensagem->texto);

        try {
            $fluxo = $this->menu->handle($gestante, $texto);
        } catch (Throwable) {
            Log::error('ai.menu.failed', ['gestante_id' => $gestante->id]);
            $enviado = $this->enviar($canal, $mensagem, $identidade, $gestante, $this->llm->fallback());

            return $this->resultado(
                fluxo: 'erro_menu',
                canal: $mensagem->canal,
                gestanteId: $gestanteId,
                intencao: 'erro',
                modelo: $this->llm->providerName(),
                fontesRag: 0,
                ferramenta: null,
                enviado: $enviado,
                inicio: $inicio,
                erro: 'menu',
            );
        }

        $intencao = $fluxo['intencao'] ?? null;

        if (($fluxo['resposta'] ?? '') !== '') {
            $enviado = $this->enviar($canal, $mensagem, $identidade, $gestante, $fluxo['resposta']);

            return $this->resultado(
                fluxo: 'menu',
                canal: $mensagem->canal,
                gestanteId: $gestanteId,
                intencao: $intencao,
                modelo: null,
                fontesRag: 0,
                ferramenta: 'menu',
                enviado: $enviado,
                inicio: $inicio,
            );
        }

        if ($fluxo['pular_ia'] ?? false) {
            return $this->resultado(
                fluxo: 'pular_ia',
                canal: $mensagem->canal,
                gestanteId: $gestanteId,
                intencao: $intencao,
                modelo: null,
                fontesRag: 0,
                ferramenta: null,
                enviado: false,
                inicio: $inicio,
            );
        }

        $respostaTool = $this->tools->tryHandle($gestante, $mensagem);
        if ($respostaTool !== null && $respostaTool !== '') {
            $enviado = $this->enviar($canal, $mensagem, $identidade, $gestante, $respostaTool);

            return $this->resultado(
                fluxo: 'tool',
                canal: $mensagem->canal,
                gestanteId: $gestanteId,
                intencao: $intencao,
                modelo: null,
                fontesRag: 0,
                ferramenta: 'registry',
                enviado: $enviado,
                inicio: $inicio,
            );
        }

        try {
            Log::info('ai.context.created', [
                'canal' => $mensagem->canal,
                'gestante_id' => $gestante->id,
            ]);
            $rag = $this->rag->recuperar($gestante, $mensagem->texto, $mensagem->canal);
            $fontesRag = (int) ($rag['total_fontes'] ?? 0);
            $intencao = (string) ($rag['intencao'] ?? $intencao ?? 'conversa_livre');
            $modelo = $this->llm->providerName();
            $resposta = $this->llm->generate((string) ($rag['contexto_texto'] ?? ''), $mensagem->texto);
        } catch (Throwable) {
            Log::warning('ai.rag_or_llm.failed', ['gestante_id' => $gestante->id]);
            $resposta = $this->llm->fallback();
            $modelo = $this->llm->providerName();

            $enviado = $this->enviar($canal, $mensagem, $identidade, $gestante, $resposta);

            return $this->resultado(
                fluxo: 'erro_llm',
                canal: $mensagem->canal,
                gestanteId: $gestanteId,
                intencao: $intencao,
                modelo: $modelo,
                fontesRag: $fontesRag,
                ferramenta: null,
                enviado: $enviado,
                inicio: $inicio,
                erro: 'rag_ou_llm',
            );
        }

        $enviado = $this->enviar($canal, $mensagem, $identidade, $gestante, $resposta);

        return $this->resultado(
            fluxo: 'rag_llm',
            canal: $mensagem->canal,
            gestanteId: $gestanteId,
            intencao: $intencao,
            modelo: $modelo,
            fontesRag: $fontesRag,
            ferramenta: null,
            enviado: $enviado,
            inicio: $inicio,
        );
    }

    private function enviar(
        MessagingChannel $canal,
        IncomingMessage $mensagem,
        GestanteCanal $identidade,
        ?Gestante $gestante,
        string $texto,
    ): bool {
        $ok = $canal->send($mensagem->chatId, $texto);
        Log::info('canal.message.sent', [
            'canal' => $canal->name(),
            'ok' => $ok,
        ]);

        if ($texto !== '') {
            $this->gravar($mensagem, $identidade, $gestante, $texto, 'saida');
        }

        return $ok;
    }

    private function gravar(
        IncomingMessage $mensagem,
        GestanteCanal $identidade,
        ?Gestante $gestante,
        string $texto,
        string $tipo,
    ): void {
        $this->recorder->record($gestante?->telefone ?? '', $texto, $tipo, [
            'gestante' => $gestante,
            'canal' => $mensagem->canal,
            'canal_identidade_id' => $identidade->id,
            'external_user_id' => $mensagem->externalUserId,
            'external_chat_id' => $mensagem->chatId,
            'processar_fluxo' => false,
            'metadata' => array_filter([
                'username' => $mensagem->username,
                'update_id' => $mensagem->metadata['update_id'] ?? null,
                'message_id' => $mensagem->metadata['message_id'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    private function normalizarComando(string $texto): string
    {
        $t = trim($texto);
        if (preg_match('/^\/(start|menu|ajuda|help)\b/i', $t)) {
            return 'menu';
        }

        return $t;
    }

    private function resultado(
        string $fluxo,
        string $canal,
        ?int $gestanteId,
        ?string $intencao,
        ?string $modelo,
        int $fontesRag,
        ?string $ferramenta,
        bool $enviado,
        int $inicio,
        ?string $erro = null,
    ): AtendimentoResultado {
        $resultado = new AtendimentoResultado(
            fluxo: $fluxo,
            canal: $canal,
            gestanteId: $gestanteId,
            intencao: $intencao,
            modelo: $modelo,
            fontesRag: $fontesRag,
            ferramenta: $ferramenta,
            enviado: $enviado,
            durationMs: (int) ((hrtime(true) - $inicio) / 1_000_000),
            erro: $erro,
        );

        Log::info('ai.completed', $resultado->toLogContext());

        return $resultado;
    }
}
