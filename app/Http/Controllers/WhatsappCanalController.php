<?php

namespace App\Http\Controllers;

use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WhatsappCanalController extends Controller
{
    public function index(WhatsAppService $whatsApp): View
    {
        $this->authorize(User::ABILITY_WHATSAPP);

        $status = $this->statusSessao($whatsApp);
        $gestantes = $this->gestantesParaChat();
        $selecionada = $gestantes->first();

        if ($selecionada) {
            $selecionada->setRelation(
                'gestanteWhatsapps',
                GestanteWhatsapp::query()
                    ->where('gestante_id', $selecionada->id)
                    ->orderBy('id')
                    ->get()
            );
        }

        $gestantes->load(['ultimaAnalise', 'consultas', 'acompanhamentos']);

        $catalogo = $whatsApp->templateCatalog();
        $lista = $gestantes->map(function (Gestante $gestante) use ($whatsApp) {
            return [
                'id' => $gestante->id,
                'nome' => $gestante->nome_exibicao,
                'telefone' => $gestante->telefone,
                'telefone_formatado' => $gestante->telefone_formatado,
                'ultima_mensagem' => $gestante->ultima_mensagem,
                'ultima_id' => $gestante->ultima_id !== null ? (int) $gestante->ultima_id : null,
                'prompts' => $whatsApp->previewPromptsForGestante($gestante),
                'ia_pausada' => $gestante->whatsappIaPausada(),
                'ia_ate' => $gestante->whatsapp_ia_pausada_ate
                    ? $gestante->whatsapp_ia_pausada_ate->timezone(config('app.timezone'))->format('d/m H:i')
                    : null,
                'menu_opcao' => $gestante->whatsapp_menu_opcao,
                'send_url' => route('gestantes.whatsapp', $gestante),
                'poll_url' => url('/api/gestante-whatsapp/'.$gestante->id),
            ];
        })->values();

        return view('whatsapp.index', [
            'sessao' => (string) config('services.waha.session', 'default'),
            'configurado' => $whatsApp->isConfigured(),
            'status' => $status,
            'pronto' => $status === 'WORKING',
            'gestantes' => $gestantes,
            'selecionada' => $selecionada,
            'lista' => $lista,
            'whatsappTemplates' => collect($catalogo)->mapWithKeys(
                fn ($label, $key) => [$key => $label]
            )->all(),
            'whatsappPrompts' => $selecionada
                ? ($lista->firstWhere('id', $selecionada->id)['prompts'] ?? [])
                : [],
            'whatsappHabilitado' => $whatsApp->isConfigured(),
            'whatsappEnvioPronto' => $status === 'WORKING',
            'whatsappIaPausada' => $selecionada?->whatsappIaPausada() ?? false,
            'whatsappIaPausadaAte' => ($selecionada && $selecionada->whatsapp_ia_pausada_ate)
                ? $selecionada->whatsapp_ia_pausada_ate->timezone(config('app.timezone'))->format('d/m H:i')
                : null,
            'whatsappMenuOpcao' => $selecionada?->whatsapp_menu_opcao,
        ]);
    }

    private function statusSessao(WhatsAppService $whatsApp): ?string
    {
        if (! $whatsApp->isConfigured()) {
            return null;
        }

        return $whatsApp->fetchSessionStatus();
    }

    /**
     * @return Collection<int, Gestante>
     */
    private function gestantesParaChat(): Collection
    {
        $ultimas = DB::table('gestante_whatsapp as gw')
            ->select('gw.gestante_id', DB::raw('MAX(gw.id) as last_id'))
            ->whereNotNull('gw.gestante_id')
            ->where(function ($query) {
                $query->where('gw.canal', GestanteWhatsapp::CANAL_WHATSAPP)
                    ->orWhereNull('gw.canal');
            })
            ->groupBy('gw.gestante_id');

        return Gestante::query()
            ->leftJoinSub($ultimas, 'lw', function ($join) {
                $join->on('gestantes.id', '=', 'lw.gestante_id');
            })
            ->leftJoin('gestante_whatsapp as gwl', 'gwl.id', '=', 'lw.last_id')
            ->select([
                'gestantes.*',
                'gwl.mensagem as ultima_mensagem',
                'gwl.id as ultima_id',
            ])
            ->orderByRaw('CASE WHEN gwl.id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('gwl.id')
            ->orderBy('gestantes.nome')
            ->orderBy('gestantes.id')
            ->get();
    }
}
