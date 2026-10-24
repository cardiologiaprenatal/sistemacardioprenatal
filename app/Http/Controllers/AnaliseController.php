<?php

namespace App\Http\Controllers;

use App\Jobs\AnalisarDadosIA;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AnaliseController extends Controller
{
    // POST /analise
    public function iniciarAnalise()
    {
        $this->authorize(User::ABILITY_DASHBOARD);

        // Dispara o job
        AnalisarDadosIA::dispatch();

        return response()->json(['status' => 'ok']);
    }

    // GET /analise/status
    public function verificarStatus()
    {
        $this->authorize(User::ABILITY_DASHBOARD);
        $status = Cache::get('analise_ia_status', 'nao_iniciado');

        return response()->json(['status' => $status]);
    }

    // GET /analise/resultado
    public function obterResultado()
    {
        $this->authorize(User::ABILITY_DASHBOARD);
        $resultado = Cache::get('resultado_analise_ia', [
            'status' => 'nao_disponivel',
            'imagens' => [],
        ]);

        return response()->json($resultado);
    }
}
