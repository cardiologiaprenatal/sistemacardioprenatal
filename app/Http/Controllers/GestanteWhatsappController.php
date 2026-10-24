<?php

namespace App\Http\Controllers;

use App\Http\Resources\GestanteResource;
use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use App\Services\GestanteWhatsappMessageRecorder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GestanteWhatsappController extends Controller
{
    public function __construct(
        private GestanteWhatsappMessageRecorder $whatsAppMessageRecorder
    ) {}

    /**
     * GET /api/gestante-whatsapp — listagem resumida (última mensagem por gestante), com JOIN em gestantes.
     */
    public function index(Request $request)
    {
        $this->authorize(User::ABILITY_WHATSAPP);
        $query = $this->buildHistoricoResumoQuery($request);
        $this->applyOrdering($request, $query);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())->map(function ($g) {
                $ultima = $this->castUltimaData($g->ultima_data);

                return [
                    'gestante' => (new GestanteResource($g))->resolve(),
                    'ultima_mensagem' => $g->ultima_mensagem,
                    'tipo' => $g->ultimo_tipo,
                    'categoria' => $g->ultima_categoria,
                    'categoria_label' => GestanteWhatsapp::labelCategoria($g->ultima_categoria),
                    'tempo_atendimento' => $g->ultimo_tempo_atendimento !== null ? (int) $g->ultimo_tempo_atendimento : null,
                    'tempo_atendimento_formatado' => $this->formatTempo($g->ultimo_tempo_atendimento !== null ? (int) $g->ultimo_tempo_atendimento : null),
                    'ultima_data' => $ultima?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Página web: histórico de atendimento (mesma consulta da API, renderizada em Blade).
     */
    public function historicoPage(Request $request): View
    {
        $this->authorize(User::ABILITY_WHATSAPP);
        $query = $this->buildHistoricoResumoQuery($request);
        $this->applyOrdering($request, $query);

        $rows = $query->paginate(15)->withQueryString();

        return view('historico_whatsapp.index', [
            'rows' => $rows,
            'sort' => $request->query('sort', 'ultima_data'),
            'direction' => strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ]);
    }

    /**
     * GET /api/gestante-whatsapp/{gestante} — histórico completo da gestante (PK da tabela gestantes).
     */
    public function show(Request $request, Gestante $gestante)
    {
        $this->authorize(User::ABILITY_WHATSAPP);
        $mensagens = GestanteWhatsapp::query()
            ->where('gestante_id', $gestante->id)
            ->when($request->filled('acompanhamento_id'), function ($q) use ($request) {
                $q->where('acompanhamento_id', (int) $request->input('acompanhamento_id'));
            })
            ->when($request->filled('categoria'), function ($q) use ($request) {
                $categoria = (string) $request->input('categoria');
                if ($categoria === GestanteWhatsapp::CATEGORIA_CONVERSA) {
                    $q->where(function ($inner) {
                        $inner->where('categoria', GestanteWhatsapp::CATEGORIA_CONVERSA)
                            ->orWhereNull('categoria');
                    });
                } else {
                    $q->where('categoria', $categoria);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'gestante' => new GestanteResource($gestante),
            'mensagens' => $mensagens->map(fn (GestanteWhatsapp $m) => $m->toConversaPayload()),
        ]);
    }

    /**
     * POST /api/gestante-whatsapp — webhook n8n/WAHA.
     */
    public function store(Request $request)
    {
        $extracted = $this->whatsAppMessageRecorder->extractIncoming($request->all());
        if ($extracted !== null) {
            $request->merge($extracted);
        }

        $validated = $request->validate([
            'telefone' => ['required', 'string', 'max:64'],
            'mensagem' => ['required', 'string'],
            'tipo' => ['required', 'in:entrada,saida'],
        ]);

        $row = $this->whatsAppMessageRecorder->record(
            $validated['telefone'],
            $validated['mensagem'],
            $validated['tipo']
        );
        $fluxo = $this->whatsAppMessageRecorder->ultimoFluxo();

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

    private function buildHistoricoResumoQuery(Request $request)
    {
        $sub = DB::table('gestante_whatsapp as gw')
            ->select('gw.gestante_id', DB::raw('MAX(gw.id) as last_whatsapp_id'))
            ->whereNotNull('gw.gestante_id');

        if ($request->filled('categoria')) {
            $categoria = (string) $request->input('categoria');
            if ($categoria === GestanteWhatsapp::CATEGORIA_CONVERSA) {
                $sub->where(function ($inner) {
                    $inner->where('gw.categoria', GestanteWhatsapp::CATEGORIA_CONVERSA)
                        ->orWhereNull('gw.categoria');
                });
            } else {
                $sub->where('gw.categoria', $categoria);
            }
        }

        $sub->groupBy('gw.gestante_id');

        $q = Gestante::query()
            ->joinSub($sub, 'lw', function ($join) {
                $join->on('gestantes.id', '=', 'lw.gestante_id');
            })
            ->join('gestante_whatsapp as gwl', 'gwl.id', '=', 'lw.last_whatsapp_id')
            ->select([
                'gestantes.*',
                'gwl.mensagem as ultima_mensagem',
                'gwl.tipo as ultimo_tipo',
                'gwl.categoria as ultima_categoria',
                'gwl.tempo_atendimento as ultimo_tempo_atendimento',
                'gwl.created_at as ultima_data',
            ]);

        if ($request->filled('gestante_id')) {
            $codigo = trim((string) $request->input('gestante_id'));
            if ($codigo !== '') {
                $q->where(function ($sub) use ($codigo) {
                    $sub->where('gestantes.id', $codigo)
                        ->orWhere('gestantes.gestante_id', $codigo);
                });
            }
        }

        if ($request->filled('nome')) {
            $raw = (string) $request->input('nome');
            $term = '%'.addcslashes($raw, '%_\\').'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('gestantes.gestante_id', 'like', $term)
                    ->orWhere('gestantes.nome', 'like', $term);
            });
        }

        if ($request->filled('cpf')) {
            $cpf = preg_replace('/\D/', '', (string) $request->input('cpf'));
            if ($cpf !== '') {
                $q->where('gestantes.cpf', 'like', '%'.$cpf.'%');
            }
        }

        if ($request->filled('telefone')) {
            $tel = preg_replace('/\D/', '', (string) $request->input('telefone'));
            if ($tel !== '') {
                $q->where('gestantes.telefone', 'like', '%'.$tel.'%');
            }
        }

        if ($request->filled('data_inicio')) {
            $q->where('gwl.created_at', '>=', Carbon::parse($request->input('data_inicio'))->startOfDay());
        }

        if ($request->filled('data_fim')) {
            $q->where('gwl.created_at', '<=', Carbon::parse($request->input('data_fim'))->endOfDay());
        }

        return $q;
    }

    private function applyOrdering(Request $request, $query): void
    {
        $sort = (string) $request->query('sort', 'ultima_data');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort !== 'ultima_data') {
            $sort = 'ultima_data';
        }

        $query->orderBy($sort, $direction);
    }

    private function formatTempo(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $s = max(0, $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;

        if ($h > 0) {
            return sprintf('%dh %dm %ds', $h, $m, $sec);
        }
        if ($m > 0) {
            return sprintf('%dm %ds', $m, $sec);
        }

        return sprintf('%ds', $sec);
    }

    private function castUltimaData(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse((string) $value);
    }
}
