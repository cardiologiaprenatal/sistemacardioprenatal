<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonApiException;
use App\Models\User;
use App\Services\CohortMlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Análise ML sobre o cadastro local; a API Python só infere.
 */
class CohortGestanteController extends Controller
{
    public function __construct(
        private CohortMlService $cohort,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize(User::ABILITY_ML);
        $perPage = max(10, min(100, (int) $request->integer('per_page', 25)));
        $page = max(1, (int) $request->integer('page', 1));
        $offset = ($page - 1) * $perPage;
        $busca = trim((string) $request->input('q', ''));

        $payload = $this->cohort->listar($offset, $perPage, $busca);

        $total = (int) ($payload['total'] ?? 0);
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));

        return view('cohort.gestantes.index', [
            'gestantes' => $payload['gestantes'] ?? [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
            'dataset' => $payload['dataset'] ?? null,
            'busca' => $busca,
            'error' => null,
        ]);
    }

    public function show(string $id): View
    {
        $this->authorize(User::ABILITY_ML);
        $detalhe = $this->cohort->detalhe($id);
        $local = $detalhe['local'];
        $predicao = session('cohort_predicao.'.$id);

        return view('cohort.gestantes.show', [
            'idGestante' => $id,
            'nomeExibicao' => $local?->nome_exibicao ?? ($detalhe['gestante']['nome'] ?? $id),
            'gestante' => $detalhe['gestante'],
            'predicao' => is_array($predicao) ? $predicao : null,
            'error' => $detalhe['error'],
            'gestanteLocalUrl' => $local ? route('gestantes.show', $local) : null,
        ]);
    }

    public function predict(string $id): RedirectResponse
    {
        $this->authorize(User::ABILITY_ML);
        try {
            $resultado = $this->cohort->predizer($id);
            session()->put('cohort_predicao.'.$id, $resultado);

            return redirect()
                ->route('cohort.gestantes.show', $id)
                ->with('success', 'Inferência concluída para '.$id.'.');
        } catch (PythonApiException $e) {
            return redirect()
                ->route('cohort.gestantes.show', $id)
                ->with('error', 'Falha na inferência: '.$e->getMessage());
        }
    }
}
