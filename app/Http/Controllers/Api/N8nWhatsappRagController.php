<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppRagContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RAG para n8n: devolve trechos do prontuário da gestante (por telefone).
 */
class N8nWhatsappRagController extends Controller
{
    public function __construct(
        private WhatsAppRagContextService $rag
    ) {}

    /**
     * GET/POST /api/v1/n8n/whatsapp/rag/{telefone}?mensagem=...&top_k=6
     */
    public function show(Request $request, string $telefone): JsonResponse
    {
        $validated = $request->validate([
            'mensagem' => ['nullable', 'string', 'max:4000'],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $payload = $this->rag->buildForTelefone(
            $telefone,
            $validated['mensagem'] ?? null,
            (int) ($validated['top_k'] ?? 6),
        );

        return response()->json($payload);
    }
}
