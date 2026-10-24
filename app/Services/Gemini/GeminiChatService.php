<?php

namespace App\Services\Gemini;

use App\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiChatService implements LlmProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function generate(string $systemContext, string $userMessage): string
    {
        return $this->gerar($systemContext, $userMessage);
    }

    public function fallbackMessage(): string
    {
        return $this->fallback();
    }

    public function gerar(string $contexto, string $mensagem): string
    {
        $apiKey = (string) config('services.gemini.api_key', '');
        if ($apiKey === '') {
            Log::warning('gemini.request.skipped', ['motivo' => 'api_key_ausente']);

            return $this->fallback();
        }

        $model = (string) config('services.gemini.model', 'gemini-2.0-flash');
        $base = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $timeout = (int) config('services.gemini.timeout', 25);
        $url = $base.'/models/'.$model.':generateContent';

        $mensagem = mb_substr(trim($mensagem), 0, 4000);
        $contexto = mb_substr(trim($contexto), 0, 12000);

        Log::info('gemini.request', [
            'model' => $model,
            'contexto_chars' => mb_strlen($contexto),
            'mensagem_chars' => mb_strlen($mensagem),
        ]);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post($url, [
                    'systemInstruction' => [
                        'parts' => [['text' => $contexto]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $mensagem]],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 1024,
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('gemini.request.failed', ['motivo' => 'timeout_ou_rede']);

            return $this->fallback();
        }

        if (! $response->successful()) {
            Log::warning('gemini.response.error', [
                'status' => $response->status(),
            ]);

            return $this->fallback();
        }

        $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        if ($text === '') {
            Log::warning('gemini.response.empty');

            return $this->fallback();
        }

        Log::info('gemini.response', ['chars' => mb_strlen($text)]);

        return $text;
    }

    public function fallback(): string
    {
        return 'Não consegui processar sua mensagem neste momento. Por favor, tente novamente em alguns instantes.';
    }
}
