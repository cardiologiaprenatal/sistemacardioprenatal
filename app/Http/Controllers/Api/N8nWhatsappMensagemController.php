<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GestanteWhatsappMessageRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pensada para workflows n8n + WAHA: grava mensagens recebidas/enviadas por número.
 */
class N8nWhatsappMensagemController extends Controller
{
    public function __construct(
        private GestanteWhatsappMessageRecorder $recorder
    ) {}

    /**
     * Telefone vem na URL (somente dígitos, 10–15 caracteres, ex.: 5521999999999).
     */
    public function store(Request $request, string $telefone): JsonResponse
    {
        $extracted = $this->recorder->extractIncoming($request->all());
        if ($extracted !== null && empty($request->input('mensagem'))) {
            $request->merge([
                'mensagem' => $extracted['mensagem'],
                'tipo' => $request->input('tipo') ?: $extracted['tipo'],
            ]);
        }

        $validated = $request->validate([
            'mensagem' => ['required', 'string'],
            'tipo' => ['required', 'in:entrada,saida'],
        ]);

        $row = $this->recorder->record($telefone, $validated['mensagem'], $validated['tipo']);
        $fluxo = $this->recorder->ultimoFluxo();

        return response()->json([
            'id' => $row->id,
            'gestante_id' => $row->gestante_id,
            'tempo_atendimento' => $row->tempo_atendimento,
            'tempo_atendimento_formatado' => $row->tempo_atendimento_formatado,
            'fluxo_respondido' => (bool) ($fluxo['respondido'] ?? false),
            'pular_ia' => (bool) ($fluxo['pular_ia'] ?? false),
            'fluxo_estado' => $fluxo['estado'] ?? null,
            'intencao' => $fluxo['intencao'] ?? null,
            'resposta_pronta' => $fluxo['resposta'] ?? null,
        ], 201);
    }
}
