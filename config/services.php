<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | WAHA — WhatsApp HTTP API (devlikeapro/waha)
    | Docs: https://waha.devlike.pro/docs/how-to/send-messages/
    | POST {base_url}/api/sendText — JSON: session, chatId (5511...@c.us), text
    | Header X-Api-Key: use WAHA_API_KEY (ou legado WAHA_API_TOKEN), salvo se WAHA_NO_API_KEY=true
    | Base URL: WAHA_BASE_URL (preferido) ou WAHA_API_URL
    | Sessão: WAHA_SESSION_NAME (preferido) ou WAHA_SESSION
    */
    'waha' => [
        'base_url' => env('WAHA_BASE_URL', env('WAHA_API_URL')),
        'api_token' => env('WAHA_API_KEY', env('WAHA_API_TOKEN')),
        'session' => env('WAHA_SESSION_NAME', env('WAHA_SESSION', 'default')),
        'no_api_key' => filter_var(env('WAHA_NO_API_KEY', false), FILTER_VALIDATE_BOOLEAN),
        'enabled' => env('WAHA_ENABLED') !== null
            ? filter_var(env('WAHA_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : (filled(env('WAHA_BASE_URL')) || filled(env('WAHA_API_URL'))),
        'timeout' => (int) env('WAHA_HTTP_TIMEOUT', 20),
        'webhook_secret' => env('WAHA_WEBHOOK_SECRET'),
        // Lembrete automático diário (agendado_para = hoje)
        'auto_lembrete' => filter_var(env('WHATSAPP_AUTO_LEMBRETE', false), FILTER_VALIDATE_BOOLEAN),
        // Minutos em que a IA/n8n deve pular resposta após a equipe falar no chat
        'ia_pausa_minutos' => (int) env('WHATSAPP_IA_PAUSA_MINUTOS', 240),
    ],

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'gemini'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_HTTP_TIMEOUT', 25),
    ],

    /*
    | Microsserviço FastAPI (ML) — predição via REST
    | Laravel = orquestrador | Python = apenas inferência (sem banco)
    | Portas: Laravel/nginx = 8000 | Python ML = 8010
    */
    'python_api' => [
        'url' => rtrim(env('PYTHON_API_URL', 'http://host.docker.internal:8010'), '/'),
        // Mesma chave do API_KEY do microsserviço FastAPI (header X-API-Key)
        'api_key' => env('PYTHON_API_KEY', env('CCF_API_KEY')),
        'timeout' => (int) env('PYTHON_API_TIMEOUT', 30),
        'retries' => (int) env('PYTHON_API_RETRIES', 2),
        'retry_sleep_ms' => (int) env('PYTHON_API_RETRY_SLEEP_MS', 200),
        // Em produção com HTTPS: true. Só false para certificado autoassinado em staging.
        'verify_ssl' => filter_var(env('PYTHON_API_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | Interpretação clínica individualizada (Gemini / Vertex) — Fase 0 mock no FastAPI.
    | CatBoost permanece a única fonte de predição; este flag só liga a narrativa.
    */
    'interpretacao' => [
        'enabled' => filter_var(env('INTERPRETACAO_GEMINI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'prompt_version' => env('INTERPRETACAO_PROMPT_VERSION', 'system_v2'),
    ],

    /*
    | Legado GestRisk/CCF (endpoints ricos: /analisar/completo)
    | Preferir services.python_api para o contrato /predict.
    */
    'ccf_api' => [
        'url' => rtrim(env('CCF_API_URL', env('PYTHON_API_URL', 'http://host.docker.internal:8010')), '/'),
        'api_key' => env('CCF_API_KEY', env('PYTHON_API_KEY')),
        'timeout' => (int) env('CCF_API_TIMEOUT', 120),
        'verify_ssl' => filter_var(env('CCF_API_VERIFY_SSL', env('PYTHON_API_VERIFY_SSL', true)), FILTER_VALIDATE_BOOLEAN),
    ],

];
