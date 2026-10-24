<?php

use App\Http\Controllers\Api\N8nWhatsappMensagemController;
use App\Http\Controllers\Api\N8nWhatsappRagController;
use App\Http\Controllers\GestanteWhatsappController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook WAHA / n8n (sem CSRF). Proteção opcional: WAHA_WEBHOOK_SECRET + header X-Webhook-Secret ou Bearer.
|--------------------------------------------------------------------------
*/
Route::post('/gestante-whatsapp', [GestanteWhatsappController::class, 'store'])
    ->middleware(['whatsapp.webhook', 'throttle:120,1']);

/*
| API v1 — ingestão de mensagens para n8n (telefone na URL).
| Documentação: docs/N8N_WHATSAPP_API.md
*/
Route::post('/v1/n8n/whatsapp/mensagens/{telefone}', [N8nWhatsappMensagemController::class, 'store'])
    ->where('telefone', '[0-9]{10,16}')
    ->middleware(['whatsapp.webhook', 'throttle:120,1']);

/*
| API v1 — RAG: contexto clínico / documentos da gestante para o prompt do WhatsApp.
*/
Route::match(['get', 'post'], '/v1/n8n/whatsapp/rag/{telefone}', [N8nWhatsappRagController::class, 'show'])
    ->where('telefone', '[0-9]{10,16}')
    ->middleware(['whatsapp.webhook', 'throttle:60,1']);
