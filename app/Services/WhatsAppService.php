<?php

namespace App\Services;

use App\Models\Acompanhamento;
use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Support\WhatsAppFluxoEstado;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    private ?string $lastError = null;

    public function __construct(
        private WhatsAppMessageTemplates $templates,
    ) {}

    /**
     * Envia texto via WAHA — {@see https://waha.devlike.pro/docs/how-to/send-messages/}
     * POST /api/sendText com body: session, chatId ({numero}@c.us), text.
     * Autenticação opcional: header X-Api-Key (WAHA_API_KEY), omitido se WAHA_NO_API_KEY=true.
     *
     * @return bool true se o WAHA respondeu com sucesso ao envio
     */
    public function sendMessage(string $telefone, string $mensagem): bool
    {
        $config = config('services.waha', []);

        if (empty($config['enabled']) || empty($config['base_url'])) {
            Log::debug('WhatsApp (WAHA): envio ignorado — integração desabilitada ou base URL vazia (WAHA_BASE_URL / WAHA_API_URL).', [
                'enabled' => ! empty($config['enabled']),
                'has_base_url' => ! empty($config['base_url']),
            ]);

            $this->lastError = 'Integração WAHA desabilitada ou WAHA_BASE_URL vazia.';

            return false;
        }

        $digits = $this->normalizePhoneToBrazilE164Digits($telefone);
        if ($digits === null || $digits === '') {
            Log::warning('WhatsApp (WAHA): telefone inválido ou vazio após normalização.', [
                'telefone_prefix' => $this->maskPhoneForLog($telefone),
            ]);

            $this->lastError = 'Telefone inválido para envio pelo WhatsApp.';

            return false;
        }

        $this->lastError = null;

        $headers = $this->wahaHeaders($config, json: true);
        $session = (string) ($config['session'] ?? 'default');
        $chatId = $this->resolveChatId($digits, $config, $headers) ?? ($digits.'@c.us');

        return $this->postToWaha($config, $headers, '/api/sendText', [
            'session' => $session,
            'chatId' => $chatId,
            'text' => $mensagem,
        ], $chatId);
    }

    /**
     * Lista nativa: botão "Selecionar" abre as opções. WEBJS costuma recusar; NOWEB/GOWS (Plus) suportam.
     *
     * @param  array{
     *   title: string,
     *   description: string,
     *   footer?: string,
     *   button: string,
     *   sections: list<array{title: string, rows: list<array{title: string, rowId?: string, id?: string, description?: string|null}>}>
     * }  $lista
     */
    public function sendListMessage(string $telefone, array $lista): bool
    {
        $config = config('services.waha', []);
        if (empty($config['enabled']) || empty($config['base_url'])) {
            $this->lastError = 'Integração WAHA desabilitada ou WAHA_BASE_URL vazia.';

            return false;
        }

        $digits = $this->normalizePhoneToBrazilE164Digits($telefone);
        if ($digits === null || $digits === '') {
            $this->lastError = 'Telefone inválido para envio pelo WhatsApp.';

            return false;
        }

        $this->lastError = null;
        $headers = $this->wahaHeaders($config, json: true);
        $session = (string) ($config['session'] ?? 'default');
        $chatId = $this->resolveChatId($digits, $config, $headers) ?? ($digits.'@c.us');

        foreach ($this->listPayloads($session, $chatId, $lista) as $payload) {
            if ($this->postToWaha($config, $headers, '/api/sendList', $payload, $chatId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $lista
     * @return list<array<string, mixed>>
     */
    private function listPayloads(string $session, string $chatId, array $lista): array
    {
        $sectionsRowId = $lista['sections'] ?? [];
        $sectionsId = [];
        foreach ($sectionsRowId as $section) {
            $rows = [];
            foreach ($section['rows'] ?? [] as $row) {
                $rows[] = [
                    'id' => $row['rowId'] ?? $row['id'] ?? '',
                    'title' => $row['title'] ?? '',
                    'description' => $row['description'] ?? null,
                ];
            }
            $sectionsId[] = [
                'title' => $section['title'] ?? '',
                'rows' => $rows,
            ];
        }

        return [
            [
                'session' => $session,
                'chatId' => $chatId,
                'message' => [
                    'title' => $lista['title'] ?? '',
                    'description' => $lista['description'] ?? '',
                    'footer' => $lista['footer'] ?? '',
                    'button' => $lista['button'] ?? 'Selecionar',
                    'sections' => $sectionsRowId,
                ],
            ],
            [
                'session' => $session,
                'chatId' => $chatId,
                'header' => $lista['title'] ?? '',
                'body' => $lista['description'] ?? '',
                'footer' => $lista['footer'] ?? '',
                'buttonText' => $lista['button'] ?? 'Selecionar',
                'sections' => $sectionsId,
            ],
        ];
    }

    /**
     * Botões nativos (WAHA). Deprecated — lista de seleção é o caminho preferido.
     *
     * @param  list<array{type: string, id?: string, text: string}>  $buttons
     */
    public function sendButtonsMessage(
        string $telefone,
        string $body,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
    ): bool {
        $config = config('services.waha', []);
        if (empty($config['enabled']) || empty($config['base_url'])) {
            $this->lastError = 'Integração WAHA desabilitada ou WAHA_BASE_URL vazia.';

            return false;
        }

        $digits = $this->normalizePhoneToBrazilE164Digits($telefone);
        if ($digits === null || $digits === '') {
            $this->lastError = 'Telefone inválido para envio pelo WhatsApp.';

            return false;
        }

        $this->lastError = null;
        $headers = $this->wahaHeaders($config, json: true);
        $session = (string) ($config['session'] ?? 'default');
        $chatId = $this->resolveChatId($digits, $config, $headers) ?? ($digits.'@c.us');

        $payload = [
            'session' => $session,
            'chatId' => $chatId,
            'body' => $body,
            'buttons' => $buttons,
        ];
        if ($header) {
            $payload['header'] = $header;
        }
        if ($footer) {
            $payload['footer'] = $footer;
        }

        return $this->postToWaha($config, $headers, '/api/sendButtons', $payload, $chatId);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    private function postToWaha(array $config, array $headers, string $path, array $payload, string $chatId): bool
    {
        $url = rtrim((string) $config['base_url'], '/').$path;

        try {
            $response = Http::timeout((int) ($config['timeout'] ?? 20))
                ->withHeaders($headers)
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp (WAHA): mensagem enviada.', [
                    'path' => $path,
                    'chatId_suffix' => substr($chatId, -12),
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::warning('WhatsApp (WAHA): resposta não bem-sucedida.', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $this->truncate($response->body()),
                'url' => $url,
            ]);

            $this->lastError = $this->humanizeWahaError($response->json(), $response->status());

            return false;
        } catch (Throwable $e) {
            Log::error('WhatsApp (WAHA): exceção inesperada ao enviar mensagem.', [
                'path' => $path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->lastError = 'Falha de conexão com o WAHA: '.$e->getMessage();

            return false;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function isConfigured(): bool
    {
        $config = config('services.waha', []);

        return ! empty($config['enabled']) && ! empty($config['base_url']);
    }

    /**
     * Consulta o status da sessão WAHA (ex.: WORKING, STARTING, SCAN_QR_CODE).
     */
    public function fetchSessionStatus(): ?string
    {
        $config = config('services.waha', []);
        if (empty($config['base_url'])) {
            return null;
        }

        $session = (string) ($config['session'] ?? 'default');
        $url = rtrim((string) $config['base_url'], '/').'/api/sessions/'.$session;

        $headers = $this->wahaHeaders($config);

        try {
            $response = Http::timeout(8)->withHeaders($headers)->get($url);
            if (! $response->successful()) {
                return null;
            }

            $status = $response->json('status');

            return is_string($status) ? $status : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Lista interativa só existe em NOWEB/GOWS. WEBJS responde 501.
     */
    private function wahaSupportsList(array $config): bool
    {
        $session = (string) ($config['session'] ?? 'default');
        $url = rtrim((string) $config['base_url'], '/').'/api/sessions/'.$session;
        $headers = $this->wahaHeaders($config);

        try {
            $response = Http::timeout(8)->withHeaders($headers)->get($url);
            if (! $response->successful()) {
                return false;
            }

            $engine = strtoupper((string) (
                $response->json('engine')
                ?: $response->json('config.engine')
                ?: ''
            ));

            return in_array($engine, ['NOWEB', 'GOWS'], true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function humanizeWahaError(?array $body, int $httpStatus): string
    {
        $sessionStatus = is_array($body) ? ($body['status'] ?? null) : null;
        $error = is_array($body) ? ($body['error'] ?? null) : null;
        $exception = is_array($body['exception'] ?? null) ? ($body['exception']['message'] ?? null) : null;

        if (is_string($sessionStatus) && $sessionStatus !== 'WORKING' && $httpStatus === 422) {
            return "A sessão WhatsApp está em {$sessionStatus} (precisa estar WORKING). Abra o painel do WAHA em http://127.0.0.1:3000/dashboard, reinicie a sessão default e escaneie o QR Code no celular.";
        }

        $detail = is_string($exception) && $exception !== '' ? $exception : (is_string($error) ? $error : '');
        if ($detail !== '' && str_contains($detail, 'No LID for user')) {
            return 'O WhatsApp não reconheceu o identificador deste número (LID). Tente enviar de novo; se persistir, reinicie a sessão default no painel do WAHA (http://127.0.0.1:3000/dashboard).';
        }

        if (is_string($error) && $error !== '') {
            return $error;
        }

        if ($detail !== '') {
            $firstLine = trim(explode("\n", $detail)[0]);

            return "O WAHA respondeu com HTTP {$httpStatus}: {$firstLine}";
        }

        return "O WAHA respondeu com HTTP {$httpStatus}. Confira se a sessão está WORKING no painel http://127.0.0.1:3000/dashboard.";
    }

    /**
     * Números BR novos exigem o chatId atual (@lid ou @c.us) via check-exists.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $headers
     */
    private function resolveChatId(string $digits, array $config, array $headers): ?string
    {
        $candidates = [$digits];
        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $candidates = [substr($digits, 2), $digits];
        } else {
            $candidates = [$digits, '55'.$digits];
        }

        $base = rtrim((string) $config['base_url'], '/');
        $session = (string) ($config['session'] ?? 'default');
        $timeout = min(12, (int) ($config['timeout'] ?? 20));

        foreach (array_unique($candidates) as $phone) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->get($base.'/api/contacts/check-exists', [
                        'phone' => $phone,
                        'session' => $session,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $exists = $response->json('numberExists');
                $chatId = $response->json('chatId');
                if ($exists && is_string($chatId) && $chatId !== '') {
                    Log::debug('WhatsApp (WAHA): chatId resolvido via check-exists.', [
                        'chatId_suffix' => substr($chatId, -16),
                    ]);

                    return $chatId;
                }
            } catch (Throwable $e) {
                Log::debug('WhatsApp (WAHA): check-exists falhou.', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function wahaHeaders(array $config, bool $json = false): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        $omitApiKey = ! empty($config['no_api_key']);
        if (! $omitApiKey && ! empty($config['api_token'])) {
            $headers['X-Api-Key'] = (string) $config['api_token'];
        }

        return $headers;
    }

    /**
     * Mensagem de boas-vindas após cadastro de gestante (GestRisk).
     *
     * @return bool true se o WAHA aceitou o envio; false se ignorado ou falhou
     */
    public function sendGestanteWelcomeMessage(Gestante $gestante): bool
    {
        $nome = trim((string) ($gestante->nome ?? ''));
        if ($nome === '') {
            Log::warning('WhatsApp (WAHA): boas-vindas não enviadas — nome da gestante vazio.', [
                'gestante_id' => $gestante->id,
            ]);

            return false;
        }

        return $this->sendGestanteMenuInicial($gestante);
    }

    /**
     * Menu inicial: lista para selecionar. Se o WAHA recusar, envia o menu numerado.
     */
    public function sendGestanteMenuInicial(Gestante $gestante): bool
    {
        $nome = trim((string) ($gestante->nome ?? ''));
        if ($nome === '') {
            $this->lastError = 'Nome da gestante vazio.';

            return false;
        }

        $telefone = $gestante->telefone ?? '';
        if ($telefone === '') {
            $this->lastError = 'Esta gestante não possui telefone cadastrado.';

            return false;
        }

        $primeiro = explode(' ', $nome)[0] ?: $nome;
        $config = config('services.waha', []);

        if ($this->wahaSupportsList($config)) {
            $lista = $this->templates->menuLista($primeiro);
            if ($this->sendListMessage($telefone, $lista)) {
                $this->persistOutgoingMessage(
                    $gestante->id,
                    $this->templates->menuListaHistorico($primeiro),
                    GestanteWhatsapp::CATEGORIA_CONVERSA,
                );
                $this->marcarMenuInicial($gestante);

                return true;
            }

            Log::info('WhatsApp (WAHA): lista de seleção indisponível, enviando menu numerado.', [
                'gestante_id' => $gestante->id,
                'detalhe' => $this->lastError,
            ]);
        } else {
            Log::info('WhatsApp (WAHA): engine sem lista interativa, enviando menu numerado.', [
                'gestante_id' => $gestante->id,
            ]);
        }

        $body = $this->templates->buildForGestante(WhatsAppMessageTemplates::MENU_INICIAL, $gestante);
        if (! $this->sendMessage($telefone, $body)) {
            return false;
        }

        $this->persistOutgoingMessage($gestante->id, $body, GestanteWhatsapp::CATEGORIA_CONVERSA);
        $this->marcarMenuInicial($gestante);

        return true;
    }

    /**
     * Envia texto livre à gestante via WAHA e grava no histórico.
     */
    public function sendGestanteTextMessage(
        Gestante $gestante,
        string $texto,
        ?string $categoria = null,
        ?int $acompanhamentoId = null,
    ): bool {
        $texto = trim($texto);
        if ($texto === '') {
            Log::warning('WhatsApp (WAHA): envio ignorado — mensagem vazia.', [
                'gestante_id' => $gestante->id,
            ]);

            return false;
        }

        $telefone = $gestante->telefone ?? '';
        if ($telefone === '') {
            Log::warning('WhatsApp (WAHA): envio ignorado — telefone vazio.', [
                'gestante_id' => $gestante->id,
            ]);

            return false;
        }

        if (! $this->sendMessage($telefone, $texto)) {
            return false;
        }

        $this->persistOutgoingMessage($gestante->id, $texto, $categoria, $acompanhamentoId);

        return true;
    }

    private function marcarMenuInicial(Gestante $gestante): void
    {
        $gestante->forceFill([
            'whatsapp_fluxo_estado' => WhatsAppFluxoEstado::MENU_INICIAL,
            'whatsapp_menu_opcao' => null,
        ])->save();
    }

    /**
     * Monta e envia prompt de acompanhamento (motivo) para a gestante.
     */
    public function sendAcompanhamentoPrompt(
        Gestante $gestante,
        string $motivo,
        ?string $prioridade = null,
        ?string $dataAgendada = null,
        ?int $acompanhamentoId = null,
    ): bool {
        $nome = trim((string) ($gestante->nome ?? ''));
        if ($nome === '') {
            Log::warning('WhatsApp (WAHA): aviso de acompanhamento não enviado — nome vazio.', [
                'gestante_id' => $gestante->id,
            ]);

            return false;
        }

        $texto = $this->templates->build($motivo, $nome, $prioridade, $dataAgendada);

        return $this->sendGestanteTextMessage(
            $gestante,
            $texto,
            $this->categoriaFromTemplate($motivo),
            $acompanhamentoId,
        );
    }

    /**
     * Avisa a gestante por WhatsApp (texto livre via WAHA) com base em um acompanhamento.
     */
    public function sendAcompanhamentoNotificacao(Gestante $gestante, Acompanhamento $acompanhamento): bool
    {
        $dataAgendada = $acompanhamento->agendado_para
            ? $acompanhamento->agendado_para->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : null;

        return $this->sendAcompanhamentoPrompt(
            $gestante,
            (string) $acompanhamento->motivo,
            $acompanhamento->prioridade,
            $dataAgendada,
            $acompanhamento->id,
        );
    }

    /**
     * Textos prontos para a UI (modal de envio manual).
     *
     * @return array<string, string>
     */
    public function previewPromptsForGestante(Gestante $gestante): array
    {
        return $this->templates->previewForGestante($gestante);
    }

    /**
     * @return array<string, string>
     */
    public function templateCatalog(): array
    {
        return $this->templates->catalog();
    }

    /**
     * @return array{sugerido: string, templates: array<string, array{label: string, sugerido: bool}>}
     */
    public function templateCatalogForGestante(Gestante $gestante): array
    {
        return $this->templates->catalogForGestante($gestante);
    }

    /**
     * Grava mensagem de saída no histórico (mesma tabela do webhook n8n/WAHA).
     */
    private function persistOutgoingMessage(
        int $gestanteId,
        string $mensagem,
        ?string $categoria = null,
        ?int $acompanhamentoId = null,
    ): void {
        try {
            $tempoAtendimento = $this->resolveTempoAtendimentoSegundos($gestanteId);
            $categoria = $categoria ?: GestanteWhatsapp::CATEGORIA_CONVERSA;

            if ($acompanhamentoId === null && $categoria === GestanteWhatsapp::CATEGORIA_CONVERSA) {
                $acompanhamentoId = GestanteWhatsapp::acompanhamentoIdRecenteParaConversa($gestanteId);
            }

            GestanteWhatsapp::query()->create([
                'gestante_id' => $gestanteId,
                'acompanhamento_id' => $acompanhamentoId,
                'mensagem' => $mensagem,
                'tipo' => 'saida',
                'categoria' => $categoria,
                'tempo_atendimento' => $tempoAtendimento,
            ]);
        } catch (Throwable $e) {
            Log::error('WhatsApp: falha ao salvar mensagem no histórico (gestante_whatsapp).', [
                'gestante_id' => $gestanteId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function categoriaFromTemplate(?string $templateOrMotivo): string
    {
        return match ($templateOrMotivo) {
            WhatsAppMessageTemplates::RETORNO, Acompanhamento::MOTIVO_RETORNO => GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO,
            WhatsAppMessageTemplates::LEMBRETE_CONSULTA, Acompanhamento::MOTIVO_CONSULTA => GestanteWhatsapp::CATEGORIA_LEMBRETE_CONSULTA,
            WhatsAppMessageTemplates::CONFIRMACAO_CONSULTA => GestanteWhatsapp::CATEGORIA_CONFIRMACAO_CONSULTA,
            default => GestanteWhatsapp::CATEGORIA_CONVERSA,
        };
    }

    /**
     * Mesma regra do webhook: intervalo em segundos desde a última mensagem desta gestante, ou null se for a primeira.
     */
    private function resolveTempoAtendimentoSegundos(int $gestanteId): ?int
    {
        $prev = GestanteWhatsapp::query()
            ->where('gestante_id', $gestanteId)
            ->orderByDesc('id')
            ->first();

        if ($prev === null) {
            return null;
        }

        return (int) abs($prev->created_at->diffInSeconds(now()));
    }

    /**
     * Garante dígitos no formato 55 + DDD + número (celular/fixo BR).
     */
    public function normalizePhoneToBrazilE164Digits(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return $digits;
        }

        if (strlen($digits) === 11) {
            return '55'.$digits;
        }

        if (strlen($digits) === 10) {
            return '55'.$digits;
        }

        if (strlen($digits) === 13 && str_starts_with($digits, '55')) {
            return $digits;
        }

        return strlen($digits) >= 12 ? $digits : null;
    }

    private function maskPhoneForLog(?string $raw): string
    {
        $d = preg_replace('/\D/', '', (string) $raw);
        if (strlen($d) < 4) {
            return '***';
        }

        return '***'.substr($d, -4);
    }

    private function truncate(string $body, int $max = 500): string
    {
        if (strlen($body) <= $max) {
            return $body;
        }

        return substr($body, 0, $max).'…';
    }
}
