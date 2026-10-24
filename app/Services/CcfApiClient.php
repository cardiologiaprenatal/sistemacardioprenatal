<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CcfApiClient
{
    /**
     * Cliente HTTP com X-API-Key (mesma chave do microsserviço ML).
     */
    private function httpClient(?int $timeout = null): PendingRequest
    {
        $config = config('services.ccf_api', []);
        $timeout ??= (int) ($config['timeout'] ?? 120);
        $verifySsl = (bool) ($config['verify_ssl'] ?? true);

        $request = Http::timeout($timeout)
            ->acceptJson()
            ->withOptions(['verify' => $verifySsl]);

        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string) (config('services.python_api.api_key') ?? ''));
        }

        if ($apiKey !== '') {
            $request = $request->withHeaders([
                'X-API-Key' => $apiKey,
            ]);
        }

        return $request;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.ccf_api.url', ''), '/');
    }

    /**
     * Envia dados clínicos para POST /analisar/completo (GestRisk FastAPI).
     *
     * @param  array<string, mixed>  $payload  Campos no formato PredictionInput
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function analisarCompleto(array $payload, bool $persistirNaApi = false): array
    {
        $baseUrl = $this->baseUrl();
        if ($baseUrl === '') {
            throw new \RuntimeException('CCF_API_URL não configurada (services.ccf_api.url).');
        }

        $url = $baseUrl.'/analisar/completo';
        $query = ['persistir' => $persistirNaApi ? 'true' : 'false'];

        Log::info('CCF API: enviando análise completa.', [
            'id_gestante' => $payload['id_gestante'] ?? null,
            'url' => $url,
        ]);

        $response = $this->httpClient()
            ->asJson()
            ->post($url.'?'.http_build_query($query), $payload);
        $response->throw();

        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('Resposta inválida da API CCF (JSON esperado).');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $baseUrl = $this->baseUrl();
        $response = $this->httpClient(10)->get($baseUrl.'/health');

        return $response->json() ?? ['status' => 'unknown'];
    }

    /**
     * @return list<array{slug: string, titulo: string, arquivo: string|null, disponivel: bool, url?: string}>
     */
    public function listarGraficosDescritivos(): array
    {
        $baseUrl = $this->baseUrl();
        if ($baseUrl === '') {
            return [];
        }

        try {
            $response = $this->httpClient(15)->get($baseUrl.'/graficos');
            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            if (! is_array($data) || ! is_array($data['graficos'] ?? null)) {
                return [];
            }

            return $data['graficos'];
        } catch (\Throwable $e) {
            Log::warning('CCF API: falha ao listar gráficos descritivos.', ['erro' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Metadados da análise descritiva populacional (CSV / API ML).
     * Fail-soft: retorna [] se a API estiver indisponível.
     *
     * @return array{
     *     total_gestantes?: int,
     *     prevalencia_ccf?: float,
     *     total_graficos?: int,
     *     graficos_disponiveis?: int,
     *     fonte?: string
     * }
     */
    public function obterAnaliseDescritiva(): array
    {
        $baseUrl = $this->baseUrl();
        if ($baseUrl === '') {
            return [];
        }

        try {
            $response = $this->httpClient(12)->get($baseUrl.'/analise/descritiva');

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return [];
            }

            return [
                'fonte' => $data['fonte'] ?? null,
                'total_gestantes' => isset($data['total_gestantes']) ? (int) $data['total_gestantes'] : null,
                'prevalencia_ccf' => isset($data['prevalencia_ccf']) ? (float) $data['prevalencia_ccf'] : null,
                'total_graficos' => isset($data['total_graficos']) ? (int) $data['total_graficos'] : null,
                'graficos_disponiveis' => isset($data['graficos_disponiveis']) ? (int) $data['graficos_disponiveis'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('CCF API: falha ao obter análise descritiva.', ['erro' => $e->getMessage()]);

            return [];
        }
    }

    public function fetchGraficoDescritivo(string $slug): Response
    {
        $baseUrl = $this->baseUrl();
        if ($baseUrl === '') {
            throw new \RuntimeException('CCF_API_URL não configurada.');
        }

        return $this->httpClient(30)->get($baseUrl.'/graficos/'.rawurlencode($slug));
    }
}
