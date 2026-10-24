<?php

namespace App\Services;

use App\Exceptions\PythonApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Cliente HTTP do microsserviço FastAPI (ML).
 *
 * Laravel orquestra; Python só infere. Sem acesso ao MySQL.
 */
class PythonApiService
{
    private string $baseUrl;

    private ?string $apiKey;

    private int $timeout;

    private int $retries;

    private int $retrySleepMs;

    private bool $verifySsl;

    public function __construct()
    {
        $config = config('services.python_api', []);
        $this->baseUrl = rtrim((string) ($config['url'] ?? ''), '/');
        if ($this->baseUrl === '') {
            $this->baseUrl = rtrim((string) (
                config('services.ccf_api.url')
                ?: env('PYTHON_API_URL')
                ?: env('CCF_API_URL')
                ?: 'http://host.docker.internal:8010'
            ), '/');
        }
        $this->apiKey = $this->resolveApiKey($config);
        $this->timeout = max(1, (int) ($config['timeout'] ?? 30));
        $this->retries = max(0, (int) ($config['retries'] ?? 2));
        $this->retrySleepMs = max(0, (int) ($config['retry_sleep_ms'] ?? 200));
        $this->verifySsl = (bool) ($config['verify_ssl'] ?? true);

        if ($this->baseUrl === '') {
            throw new InvalidArgumentException('PYTHON_API_URL não configurada (services.python_api.url).');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveApiKey(array $config): ?string
    {
        $key = trim((string) ($config['api_key'] ?? ''));
        if ($key === '') {
            $key = trim((string) (config('services.ccf_api.api_key') ?? ''));
        }

        return $key !== '' ? $key : null;
    }

    /**
     * Cliente HTTP autenticado (X-API-Key) para o microsserviço ML.
     */
    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->retry($this->retries, $this->retrySleepMs, function ($exception) {
                return $exception instanceof ConnectionException;
            }, throw: false);

        if ($this->apiKey !== null) {
            $request = $request->withHeaders([
                'X-API-Key' => $this->apiKey,
            ]);
        }

        return $request;
    }

    /**
     * @return array{status: string}
     */
    public function health(): array
    {
        $data = $this->getJson('/health');

        if (($data['status'] ?? null) !== 'ok') {
            throw new PythonApiException('Health check da API Python falhou.', null, $data);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function modelInfo(): array
    {
        return $this->getJson('/model/info');
    }

    /**
     * Comparativo de modelos treinados (GET /modelos).
     *
     * @return array{
     *     disponivel: bool,
     *     experimento?: string|null,
     *     fonte?: string|null,
     *     criterio_selecao?: string|null,
     *     total_modelos: int,
     *     destaques: array<string, mixed>,
     *     modelos: list<array<string, mixed>>,
     *     legendas?: array<string, string>,
     *     mensagem?: string|null
     * }
     */
    public function listModelos(bool $todosBalanceamentos = false): array
    {
        try {
            // /modelos = melhor balanceamento por modelo; /modelos/metricas aceita todas_combinacoes
            $path = $todosBalanceamentos ? '/modelos/metricas' : '/modelos';
            $query = $todosBalanceamentos ? ['todas_combinacoes' => 'true'] : [];
            $data = $this->getJson($path, $query);
        } catch (PythonApiException $e) {
            if (in_array($e->statusCode, [404, 405], true)) {
                return $this->catalogoModeloServido();
            }
            throw $e;
        }

        $modelos = is_array($data['modelos'] ?? null) ? $data['modelos'] : [];

        return [
            'disponivel' => (bool) ($data['disponivel'] ?? $modelos !== []),
            'experimento' => $data['experimento'] ?? null,
            'fonte' => $data['fonte'] ?? null,
            'criterio_selecao' => $data['criterio_selecao'] ?? null,
            'total_modelos' => (int) ($data['total_modelos'] ?? count($modelos)),
            'destaques' => is_array($data['destaques'] ?? null) ? $data['destaques'] : [],
            'modelos' => $modelos,
            'legendas' => is_array($data['legendas'] ?? null) ? $data['legendas'] : [],
            'producao' => is_array($data['producao'] ?? null) ? $data['producao'] : [],
            'mensagem' => $data['mensagem'] ?? null,
        ];
    }

    /**
     * Fallback quando a API não tem GET /modelos: descreve o modelo de /model/info.
     *
     * @return array{
     *     disponivel: bool,
     *     experimento?: string|null,
     *     fonte?: string|null,
     *     criterio_selecao?: string|null,
     *     total_modelos: int,
     *     destaques: array<string, mixed>,
     *     modelos: list<array<string, mixed>>,
     *     legendas?: array<int, string>,
     *     mensagem?: string|null
     * }
     */
    private function catalogoModeloServido(): array
    {
        try {
            $info = $this->modelInfo();
        } catch (PythonApiException) {
            return [
                'disponivel' => false,
                'total_modelos' => 0,
                'destaques' => [],
                'modelos' => [],
                'mensagem' => 'Endpoint /modelos indisponível nesta API.',
            ];
        }

        $loaded = (bool) ($info['loaded'] ?? false);
        $framework = (string) ($info['framework'] ?? 'heuristic');
        $display = $loaded ? $framework : 'Heurística';

        return [
            'disponivel' => false,
            'experimento' => null,
            'fonte' => 'model/info',
            'total_modelos' => 0,
            'destaques' => [],
            'modelos' => [],
            'producao' => [
                'modelo' => $loaded ? $framework : null,
                'nome_exibicao' => $display,
                'carregado' => $loaded,
                'caminho' => $info['model_path'] ?? null,
                'framework' => $framework,
                'modo_inferencia' => $loaded ? 'modelo' : 'heuristica',
                'mensagem' => $loaded
                    ? null
                    : 'Nenhum artefato .pkl carregado; a inferência usa regras clínicas.',
            ],
            'legendas' => [],
            'mensagem' => 'Catálogo de experimentos indisponível nesta API.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        return $this->getJson('/metrics');
    }

    /**
     * Lista paginada do dataset ML (sem inferência).
     *
     * @return array{total: int, offset: int, limit: int, dataset?: string, gestantes: list<array<string, mixed>>}
     */
    public function listGestantes(int $offset = 0, int $limit = 50): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(1000, $limit));

        $data = $this->getJson('/gestantes', [
            'offset' => $offset,
            'limit' => $limit,
        ]);

        if (! isset($data['gestantes']) || ! is_array($data['gestantes'])) {
            throw new PythonApiException('Resposta /gestantes inválida.', null, $data);
        }

        return [
            'total' => (int) ($data['total'] ?? count($data['gestantes'])),
            'offset' => (int) ($data['offset'] ?? $offset),
            'limit' => (int) ($data['limit'] ?? $limit),
            'dataset' => $data['dataset'] ?? null,
            'gestantes' => $data['gestantes'],
        ];
    }

    /**
     * Dados clínicos de uma gestante do dataset (sem ML).
     *
     * @return array<string, mixed>
     */
    public function getGestante(string $idGestante): array
    {
        $id = trim($idGestante);
        if ($id === '') {
            throw new InvalidArgumentException('id_gestante vazio.');
        }

        return $this->getJson('/gestantes/'.rawurlencode($id));
    }

    /**
     * Sync paginado (API AnalisePython completa). Retorna null se o endpoint não existir.
     *
     * @return array{total: int, page: int, per_page: int, total_pages: int, gestantes: list<array<string, mixed>>}|null
     */
    public function syncGestantesPage(int $page = 1, int $perPage = 500): ?array
    {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));

        try {
            $data = $this->getJson('/gestantes/sync', [
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (PythonApiException $e) {
            if (in_array($e->statusCode, [404, 405], true)) {
                return null;
            }
            // Algumas APIs tratam "sync" como id_gestante → 404 genérico no path /gestantes/{id}
            if ($e->statusCode === 404 || str_contains(strtolower($e->getMessage()), 'não encontrada')) {
                return null;
            }
            throw $e;
        }

        if (! isset($data['gestantes']) || ! is_array($data['gestantes'])) {
            return null;
        }

        return [
            'total' => (int) ($data['total'] ?? count($data['gestantes'])),
            'page' => (int) ($data['page'] ?? $page),
            'per_page' => (int) ($data['per_page'] ?? $perPage),
            'total_pages' => (int) ($data['total_pages'] ?? 1),
            'gestantes' => $data['gestantes'],
        ];
    }

    /**
     * Inferência unitária: POST /predict/{id_gestante}
     *
     * @return array<string, mixed>
     */
    public function predictById(string $idGestante): array
    {
        $id = trim($idGestante);
        if ($id === '') {
            throw new InvalidArgumentException('id_gestante vazio.');
        }

        Log::info('PythonApiService: POST /predict/{id}.', ['id_gestante' => $id]);

        $data = $this->postJson('/predict/'.rawurlencode($id), []);
        $normalized = $this->normalizeInferenceResult($data);
        $this->assertUnitaryInference($normalized);

        return $normalized;
    }

    /**
     * Inferência em lote (todas as gestantes, uma a uma na API).
     * Timeout elevado — usar com cautela.
     *
     * @return array<string, mixed>
     */
    public function predictAll(): array
    {
        Log::info('PythonApiService: POST /predict/all.');

        $previous = $this->timeout;
        $this->timeout = max($this->timeout, 180);

        try {
            return $this->postJson('/predict/all', []);
        } finally {
            $this->timeout = $previous;
        }
    }

    /**
     * JSON clínico direto (legado): POST /predict
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function predict(array $dados): array
    {
        if ($dados === []) {
            throw new InvalidArgumentException('Payload de predição vazio.');
        }

        Log::info('PythonApiService: enviando /predict.', [
            'keys' => array_keys($dados),
            'url' => $this->baseUrl.'/predict',
        ]);

        $data = $this->postJson('/predict', $dados);
        $normalized = $this->normalizeInferenceResult($data);
        $this->assertUnitaryInference($normalized);

        Log::info('PythonApiService: predição recebida.', [
            'id_gestante' => $normalized['id_gestante'] ?? null,
            'classificacao_risco' => $normalized['classificacao_risco'],
            'probabilidade_risco' => $normalized['probabilidade_risco'],
        ]);

        return $normalized;
    }

    /**
     * Interpretação clínica individualizada (Fase 0: mock Vertex).
     * POST /interpretar/gestante — não altera CatBoost.
     *
     * @param  array<string, mixed>  $patientContext
     * @return array<string, mixed>
     */
    public function interpretarGestante(array $patientContext): array
    {
        Log::info('PythonApiService: POST /interpretar/gestante.', [
            'schema_version' => $patientContext['schema_version'] ?? null,
            'analise_id' => $patientContext['predicao_catboost']['analise_id'] ?? null,
        ]);

        $data = $this->postJson('/interpretar/gestante', $patientContext);

        if (! is_string($data['resumo_medico'] ?? null) || $data['resumo_medico'] === '') {
            throw new PythonApiException('Resposta de interpretação sem resumo_medico.', null, $data);
        }

        return $data;
    }

    /**
     * Unifica contratos legado (risk/probability) e unitário (classificacao_risco/...).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeInferenceResult(array $data): array
    {
        $probability = $data['probabilidade_risco'] ?? $data['probability'] ?? $data['score'] ?? null;
        $risk = $data['classificacao_risco'] ?? $data['risk'] ?? null;

        $fonte = $data['fonte'] ?? $data['modo_inferencia'] ?? null;
        if (is_string($fonte)) {
            $fonte = strtolower($fonte);
            $fonte = match ($fonte) {
                'modelo', 'full_pipeline', 'catboost' => 'catboost',
                'heuristica', 'heuristic' => 'heuristica',
                default => $fonte,
            };
        }

        return [
            'id_gestante' => $data['id_gestante'] ?? null,
            'probabilidade_risco' => $probability !== null ? (float) $probability : null,
            'classificacao_risco' => is_string($risk) ? $risk : null,
            'score' => isset($data['score']) ? (float) $data['score'] : ($probability !== null ? (float) $probability : null),
            'class' => isset($data['class']) ? (int) $data['class'] : null,
            'shap' => is_array($data['shap'] ?? null) ? $data['shap'] : [],
            'execution_time_ms' => isset($data['execution_time_ms']) ? (float) $data['execution_time_ms'] : null,
            'fonte' => is_string($fonte) ? $fonte : null,
            'modelo' => is_string($data['modelo'] ?? null) ? $data['modelo'] : null,
            'experimento' => is_string($data['experimento'] ?? null) ? $data['experimento'] : null,
            'modo_inferencia' => is_string($data['modo_inferencia'] ?? null) ? $data['modo_inferencia'] : $fonte,
            'fallback_motivo' => is_string($data['fallback_motivo'] ?? null) ? $data['fallback_motivo'] : null,
            // aliases úteis para views/jobs legados
            'risk' => is_string($risk) ? $risk : null,
            'probability' => $probability !== null ? (float) $probability : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        try {
            $request = $this->httpClient();

            $response = $payload === []
                ? $request->post($path)
                : $request->asJson()->post($path, $payload);

            if ($response->status() === 401) {
                throw new PythonApiException(
                    'API Python rejeitou a autenticação (401). Confira PYTHON_API_KEY = API_KEY do AnalisePython.',
                    401,
                    $response->json() ?? $response->body(),
                );
            }

            if ($response->failed()) {
                throw new PythonApiException(
                    'API Python retornou erro HTTP em '.$path.'.',
                    $response->status(),
                    $response->json() ?? $response->body(),
                );
            }

            $json = $response->json();
            if (! is_array($json)) {
                throw new PythonApiException('Resposta JSON inválida da API Python.', $response->status(), $response->body());
            }

            return $json;
        } catch (PythonApiException $e) {
            Log::error('PythonApiService: falha de negócio/HTTP.', [
                'path' => $path,
                'status' => $e->statusCode,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (ConnectionException $e) {
            Log::error('PythonApiService: falha de conexão.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
            throw new PythonApiException('Não foi possível conectar à API Python.', null, null, $e);
        } catch (RequestException $e) {
            Log::error('PythonApiService: request exception.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
            throw new PythonApiException('Falha na requisição à API Python.', null, null, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $path, array $query = []): array
    {
        try {
            $response = $this->httpClient()->get($path, $query);

            if ($response->failed()) {
                throw new PythonApiException(
                    'API Python retornou erro HTTP em '.$path.'.',
                    $response->status(),
                    $response->json() ?? $response->body(),
                );
            }

            $json = $response->json();
            if (! is_array($json)) {
                throw new PythonApiException('Resposta JSON inválida da API Python.', $response->status(), $response->body());
            }

            return $json;
        } catch (PythonApiException $e) {
            throw $e;
        } catch (ConnectionException $e) {
            throw new PythonApiException('Não foi possível conectar à API Python.', null, null, $e);
        } catch (RequestException $e) {
            throw new PythonApiException('Falha na requisição à API Python.', null, null, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertUnitaryInference(array $data): void
    {
        if (! is_string($data['classificacao_risco'] ?? null) || $data['classificacao_risco'] === '') {
            throw new PythonApiException('Resposta sem classificacao_risco/risk.', null, $data);
        }

        if (! is_numeric($data['probabilidade_risco'] ?? null)) {
            throw new PythonApiException('Resposta sem probabilidade_risco/probability.', null, $data);
        }

        $probability = (float) $data['probabilidade_risco'];
        if ($probability < 0 || $probability > 1) {
            throw new PythonApiException('probabilidade_risco fora de [0, 1].', null, $data);
        }

        if (! is_array($data['shap'] ?? null)) {
            throw new PythonApiException('Campo shap deve ser uma lista.', null, $data);
        }
    }
}
