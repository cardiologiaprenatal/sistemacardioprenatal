<?php

namespace App\Contracts;

/**
 * Provedor de LLM desacoplado do canal e da API concreta (Gemini, OpenAI, etc.).
 */
interface LlmProvider
{
    public function name(): string;

    public function generate(string $systemContext, string $userMessage): string;

    public function fallbackMessage(): string;
}
