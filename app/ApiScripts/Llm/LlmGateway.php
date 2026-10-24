<?php

namespace App\ApiScripts\Llm;

use App\Contracts\LlmProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Porta única para o orquestrador falar com o LLM, sem conhecer Gemini.
 */
class LlmGateway
{
    public function __construct(
        private LlmProvider $provider,
    ) {}

    public function providerName(): string
    {
        return $this->provider->name();
    }

    public function generate(string $systemContext, string $userMessage): string
    {
        $inicio = hrtime(true);

        try {
            $texto = $this->provider->generate($systemContext, $userMessage);
        } catch (Throwable) {
            Log::warning('llm.failed', [
                'provider' => $this->provider->name(),
                'motivo' => 'excecao',
            ]);

            return $this->fallback();
        }

        Log::info('llm.completed', [
            'provider' => $this->provider->name(),
            'duration_ms' => (int) ((hrtime(true) - $inicio) / 1_000_000),
            'resposta_chars' => mb_strlen($texto),
        ]);

        return $texto;
    }

    public function fallback(): string
    {
        return $this->provider->fallbackMessage();
    }
}
