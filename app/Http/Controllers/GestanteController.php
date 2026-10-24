<?php

namespace App\Http\Controllers;

use App\Models\Acompanhamento;
use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use App\Services\AcompanhamentoSyncService;
use App\Services\EmailService;
use App\Services\GestanteAtendimentoService;
use App\Services\GestanteTimelineService;
use App\Services\WhatsAppMessageTemplates;
use App\Services\WhatsAppService;
use App\Support\WhatsAppFluxoEstado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class GestanteController extends Controller
{
    public function index()
    {
        $this->authorize(User::ABILITY_GESTANTES_VIEW);
        $query = Gestante::withCount('consultas');
        if (auth()->user()?->can(User::ABILITY_ANALISES)) {
            $query->with('ultimaAnalise');
        }

        $gestantes = $query
            ->orderBy('nome')
            ->orderBy('id')
            ->paginate(15);

        return view('gestantes.index', [
            'gestantes' => $gestantes,
        ]);
    }

    public function create()
    {
        $this->authorize(User::ABILITY_GESTANTES_MANAGE);

        return view('gestantes.create');
    }

    public function store(Request $request, WhatsAppService $whatsAppService, EmailService $emailService)
    {
        $this->authorize(User::ABILITY_GESTANTES_MANAGE);
        $request->merge([
            'cpf' => $this->normalizeDigits($request->input('cpf')),
            'telefone' => $this->normalizeDigits($request->input('telefone')),
            'email' => $emailService->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'required|date',
            'cpf' => ['required', 'regex:/^\d{11}$/', Rule::unique('gestantes', 'cpf')],
            'telefone' => ['required', 'regex:/^\d{10,13}$/', Rule::unique('gestantes', 'telefone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('gestantes', 'email')],
        ], [
            'cpf.required' => 'Informe o CPF da gestante.',
            'cpf.regex' => 'O CPF deve conter 11 dígitos.',
            'telefone.required' => 'Informe o telefone da gestante.',
            'telefone.regex' => 'O telefone deve ter entre 10 e 13 dígitos (com DDD ou 55).',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado para outra gestante.',
        ]);

        $gestante = Gestante::create($validated);

        $whatsappEnviado = false;
        try {
            $whatsappEnviado = $whatsAppService->sendGestanteWelcomeMessage($gestante);
        } catch (Throwable $e) {
            Log::error('Falha ao disparar WhatsApp de boas-vindas após cadastro de gestante.', [
                'gestante_id' => $gestante->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        $emailEnviado = false;
        if (filled($gestante->email) && ! $emailService->isStandby()) {
            try {
                $emailEnviado = $emailService->sendGestanteWelcomeMessage($gestante);
            } catch (Throwable $e) {
                Log::error('Falha ao disparar e-mail de boas-vindas após cadastro de gestante.', [
                    'gestante_id' => $gestante->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $redirect = redirect()
            ->route('gestantes.show', $gestante->id)
            ->with('success', 'Gestante cadastrada com sucesso!');

        $warnings = [];
        if (! $whatsappEnviado && config('services.waha.enabled')) {
            $detalhe = $whatsAppService->getLastError();
            $warnings[] = $detalhe
                ? 'A mensagem de WhatsApp de boas-vindas não foi enviada: '.$detalhe
                : 'A mensagem de WhatsApp de boas-vindas não foi enviada. Confira se o WAHA está em execução e se a sessão está conectada (estado WORKING no painel).';
        }
        if (filled($gestante->email) && ! $emailEnviado && ! $emailService->isStandby()) {
            $warnings[] = $emailService->getLastError()
                ?: 'O e-mail de boas-vindas não foi enviado.';
        }
        if ($warnings !== []) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    public function show(Request $request, Gestante $gestante, AcompanhamentoSyncService $sync, GestanteTimelineService $timeline, WhatsAppService $whatsAppService, EmailService $emailService)
    {
        $this->authorize(User::ABILITY_GESTANTES_VIEW);

        $user = auth()->user();
        $podeAnalises = (bool) $user?->can(User::ABILITY_ANALISES);
        $podeAtenderWhatsapp = (bool) $user?->canAtenderWhatsappGestante();
        $whatsappLiberado = $gestante->atendimentoPersonalizadoLiberado();
        $podeWhatsapp = $podeAtenderWhatsapp && $whatsappLiberado;
        $podeAcompanhamentos = (bool) $user?->can(User::ABILITY_ACOMPANHAMENTOS);
        $podeDirecionar = (bool) $user?->canDirecionarAtendimentoGestante();
        $podeVerAgenda = (bool) $user?->can(User::ABILITY_CONSULTAS_AGENDA);
        $aba = ($podeWhatsapp && $request->input('aba') === 'whatsapp') ? 'whatsapp' : 'consulta';

        $sync->syncGestante($gestante);
        if (! $podeAnalises) {
            $gestante->unsetRelation('ultimaAnalise');
        }

        $relacoes = [
            'consultas' => function ($query) {
                $query->orderByDesc('data_consulta')->orderByDesc('consulta_numero')->orderByDesc('id');
            },
            'documentos' => function ($query) {
                $query->where('ativo', true)
                    ->where('tipo', 'exame')
                    ->orderByDesc('data_documento')
                    ->orderByDesc('id');
            },
        ];
        if ($podeVerAgenda) {
            $relacoes['consultaAgendamentos'] = fn ($q) => $q->orderByDesc('agendado_para');
            $relacoes['agendamentoSolicitacoes'] = fn ($q) => $q->with('solicitante')->orderByDesc('created_at');
        }
        if ($podeAcompanhamentos || $podeDirecionar) {
            $relacoes[] = 'acompanhamentos';
            $relacoes[] = 'alertas';
        }
        if ($podeAnalises) {
            $relacoes[] = 'analisesHistorico';
            $relacoes[] = 'ultimaAnalise';
            $relacoes[] = 'ultimaInterpretacao';
            $relacoes['interpretacoesClinicas'] = function ($query) {
                $query->orderByDesc('created_at')->limit(10);
            };
        }
        if ($podeWhatsapp) {
            $relacoes['gestanteWhatsapps'] = function ($query) {
                $query->orderByDesc('id')->limit(80);
            };
        }

        $gestante->load($relacoes);

        $consultasOrdenadas = $gestante->consultas;
        $consultaAtual = $consultasOrdenadas->first();
        $historicoConsultas = $consultasOrdenadas->slice(1)->values();

        $timelineEventos = $podeWhatsapp
            ? $timeline->forGestante($gestante, incluirClinico: false, incluirWhatsapp: true)
            : collect();

        $whatsappCatalog = $podeWhatsapp
            ? $whatsAppService->templateCatalogForGestante($gestante)
            : ['templates' => [], 'sugerido' => null];
        $emailCatalog = $podeWhatsapp
            ? $emailService->templateCatalogForGestante($gestante)
            : ['templates' => [], 'sugerido' => null];

        return view('gestantes.show', [
            'gestante' => $gestante,
            'timeline' => $timelineEventos,
            'whatsappPrompts' => $podeWhatsapp ? $whatsAppService->previewPromptsForGestante($gestante) : [],
            'whatsappTemplates' => collect($whatsappCatalog['templates'])->mapWithKeys(
                fn ($meta, $key) => [$key => $meta['label']]
            )->all(),
            'whatsappTemplateSugerido' => $whatsappCatalog['sugerido'],
            'whatsappHabilitado' => (bool) config('services.waha.enabled'),
            'emailPrompts' => $podeWhatsapp ? $emailService->previewPromptsForGestante($gestante) : [],
            'emailAssuntos' => $podeWhatsapp ? $emailService->assuntosParaCatalogo() : [],
            'emailTemplates' => collect($emailCatalog['templates'])->mapWithKeys(
                fn ($meta, $key) => [$key => $meta['label']]
            )->all(),
            'emailTemplateSugerido' => $emailCatalog['sugerido'],
            'emailHabilitado' => $emailService->isEnabled(),
            'emailLogMailer' => $emailService->isLogMailer(),
            'whatsappIaPausada' => $podeWhatsapp ? $gestante->whatsappIaPausada() : false,
            'whatsappIaPausadaAte' => ($podeWhatsapp && $gestante->whatsapp_ia_pausada_ate)
                ? $gestante->whatsapp_ia_pausada_ate->timezone(config('app.timezone'))->format('d/m H:i')
                : null,
            'whatsappMenuOpcao' => $podeWhatsapp ? $gestante->whatsapp_menu_opcao : null,
            'podeVerAgenda' => $podeVerAgenda,
            'podeDirecionarAtendimento' => $podeDirecionar,
            'atendimentoPersonalizadoLiberado' => $whatsappLiberado,
            'encaminhadaParaFilaEco' => $gestante->encaminhadaParaFilaEco(),
            'aba' => $aba,
            'consultaAtual' => $consultaAtual,
            'historicoConsultas' => $historicoConsultas,
        ]);
    }

    public function aceitarAtendimentoPersonalizado(Request $request, Gestante $gestante, GestanteAtendimentoService $atendimento)
    {
        $this->authorize(User::ABILITY_CONSULTAS_MANAGE);
        $atendimento->aceitarAtendimentoPersonalizado($gestante, $request->user());

        $params = ['gestante' => $gestante];
        if ($request->user()?->canAtenderWhatsappGestante()) {
            $params['aba'] = 'whatsapp';
        }

        return redirect()
            ->route('gestantes.show', $params)
            ->with('success', 'Atendimento personalizado autorizado. A gestante foi liberada para o WhatsApp.');
    }

    public function encaminharEcocardiograma(Request $request, Gestante $gestante, GestanteAtendimentoService $atendimento)
    {
        $this->authorize(User::ABILITY_CONSULTAS_MANAGE);
        $atendimento->encaminharParaFilaEcocardiograma($gestante, $request->user());

        return redirect()
            ->route('gestantes.show', $gestante)
            ->with('success', 'Gestante encaminhada para a fila do ecocardiograma.');
    }

    public function enviarWhatsapp(Request $request, Gestante $gestante, WhatsAppService $whatsAppService)
    {
        $this->authorize(User::ABILITY_WHATSAPP);
        if (! $request->user()?->canAtenderWhatsappGestante()) {
            abort(403, 'O médico não realiza atendimento pelo WhatsApp nesta tela.');
        }
        if (! $gestante->atendimentoPersonalizadoLiberado()) {
            return $this->whatsappSendResponse(
                $request,
                false,
                'O WhatsApp só é liberado depois que o médico aceitar o atendimento personalizado.'
            );
        }
        $templateKeys = array_merge(array_keys($whatsAppService->templateCatalog()), ['personalizada']);

        $validated = $request->validate([
            'tipo' => ['nullable', 'string', Rule::in($templateKeys)],
            'mensagem' => ['required', 'string', 'min:3', 'max:4000'],
        ], [
            'mensagem.required' => 'Informe o texto da mensagem.',
            'mensagem.min' => 'A mensagem deve ter ao menos 3 caracteres.',
        ]);

        if (empty($gestante->telefone)) {
            return $this->whatsappSendResponse($request, false, 'Esta gestante não possui telefone cadastrado.');
        }

        if (! config('services.waha.enabled')) {
            return $this->whatsappSendResponse($request, false, 'A integração WhatsApp (WAHA) está desabilitada. Configure WAHA_BASE_URL no .env.');
        }

        $enviado = false;
        $tipo = $validated['tipo'] ?? 'personalizada';
        try {
            if ($tipo === WhatsAppMessageTemplates::MENU_INICIAL) {
                $enviado = $whatsAppService->sendGestanteMenuInicial($gestante);
            } else {
                $enviado = $whatsAppService->sendGestanteTextMessage(
                    $gestante,
                    $validated['mensagem'],
                    $whatsAppService->categoriaFromTemplate($tipo),
                    $this->resolverAcompanhamentoParaLembrete($gestante, $tipo),
                );
            }
        } catch (Throwable $e) {
            Log::error('Falha ao enviar WhatsApp manual pela tela da gestante.', [
                'gestante_id' => $gestante->id,
                'tipo' => $validated['tipo'] ?? 'personalizada',
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        if ($enviado) {
            $this->sincronizarPausaIaWhatsapp($gestante, $tipo);
            $gestante->refresh();

            $msg = 'Mensagem enviada pelo WhatsApp com sucesso.';
            if ($tipo === WhatsAppMessageTemplates::ATENDENTE_HUMANO) {
                $msg = 'Atendente assumiu a conversa. A IA fica em pausa para não misturar as mensagens.';
            } elseif ($tipo === WhatsAppMessageTemplates::RETOMAR_ASSISTENTE) {
                $msg = 'Assistente automático retomado.';
            } elseif ($tipo === WhatsAppMessageTemplates::MENU_INICIAL) {
                $msg = 'Menu inicial enviado. A gestante começa com a IA e pode escolher atendente, exames ou médico.';
            }

            return $this->whatsappSendResponse($request, true, $msg, [
                'ia_pausada' => $gestante->whatsappIaPausada(),
                'ia_pausada_ate' => $gestante->whatsapp_ia_pausada_ate
                    ? $gestante->whatsapp_ia_pausada_ate->timezone(config('app.timezone'))->format('d/m H:i')
                    : null,
                'menu_opcao' => $gestante->whatsapp_menu_opcao,
            ]);
        }

        $detalhe = $whatsAppService->getLastError()
            ?: 'Não foi possível enviar a mensagem. Confira se o WAHA está em execução e se a sessão está conectada (estado WORKING).';

        return $this->whatsappSendResponse($request, false, $detalhe);
    }

    private function whatsappSendResponse(Request $request, bool $ok, string $message, array $extra = [])
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'ok' => $ok,
                'message' => $message,
            ], $extra), $ok ? 200 : 422);
        }

        return $ok
            ? back()->with('success', $message)
            : back()->with('warning', $message);
    }

    private function resolverAcompanhamentoParaLembrete(Gestante $gestante, ?string $tipo): ?int
    {
        if (! in_array($tipo, [
            WhatsAppMessageTemplates::LEMBRETE_CONSULTA,
            WhatsAppMessageTemplates::RETORNO,
        ], true)) {
            return GestanteWhatsapp::acompanhamentoIdRecenteParaConversa($gestante->id);
        }

        $query = $gestante->acompanhamentos()
            ->abertos()
            ->orderByDesc('agendado_para')
            ->orderByDesc('id');

        if ($tipo === WhatsAppMessageTemplates::RETORNO) {
            $query->where('motivo', Acompanhamento::MOTIVO_RETORNO);
        } else {
            $query->where(function ($q) {
                $q->whereNull('motivo')
                    ->orWhere('motivo', '!=', Acompanhamento::MOTIVO_RETORNO);
            });
        }

        $id = $query->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function sincronizarPausaIaWhatsapp(Gestante $gestante, ?string $tipo): void
    {
        if ($tipo === WhatsAppMessageTemplates::MENU_INICIAL) {
            $gestante->forceFill([
                'whatsapp_menu_opcao' => null,
                'whatsapp_fluxo_estado' => WhatsAppFluxoEstado::MENU_INICIAL,
                'whatsapp_ia_pausada_ate' => null,
            ])->save();

            return;
        }

        if ($tipo === WhatsAppMessageTemplates::RETOMAR_ASSISTENTE) {
            $gestante->forceFill([
                'whatsapp_ia_pausada_ate' => null,
                'whatsapp_menu_opcao' => null,
                'whatsapp_fluxo_estado' => WhatsAppFluxoEstado::MENU_INICIAL,
            ])->save();

            return;
        }

        $opcao = match ($tipo) {
            WhatsAppMessageTemplates::ATENDENTE_HUMANO => 'atendente',
            default => $gestante->whatsapp_menu_opcao,
        };

        $minutos = max(30, (int) config('services.waha.ia_pausa_minutos', 240));
        $gestante->forceFill([
            'whatsapp_ia_pausada_ate' => now()->addMinutes($minutos),
            'whatsapp_menu_opcao' => $opcao,
        ])->save();
    }

    public function enviarEmail(Request $request, Gestante $gestante, EmailService $emailService)
    {
        $this->authorize(User::ABILITY_WHATSAPP);
        if (! $request->user()?->canAtenderWhatsappGestante()) {
            abort(403, 'O médico não realiza atendimento pelo WhatsApp nesta tela.');
        }
        if (! $gestante->atendimentoPersonalizadoLiberado()) {
            return back()->with('warning', 'O WhatsApp só é liberado depois que o médico aceitar o atendimento personalizado.');
        }
        $templateKeys = array_merge(array_keys($emailService->templateCatalog()), ['personalizada']);

        $validated = $request->validate([
            'tipo' => ['required', 'string', Rule::in($templateKeys)],
            'assunto' => ['required', 'string', 'min:3', 'max:255'],
            'mensagem' => ['required', 'string', 'min:3', 'max:4000'],
        ], [
            'tipo.required' => 'Selecione o template da mensagem.',
            'assunto.required' => 'Informe o assunto do e-mail.',
            'assunto.min' => 'O assunto deve ter ao menos 3 caracteres.',
            'mensagem.required' => 'Informe o texto da mensagem.',
            'mensagem.min' => 'A mensagem deve ter ao menos 3 caracteres.',
        ]);

        if (empty($gestante->email)) {
            return back()->with('warning', 'Esta gestante não possui e-mail cadastrado.');
        }

        if ($emailService->isStandby()) {
            return back();
        }

        if (! $emailService->isEnabled()) {
            return back();
        }

        $enviado = false;
        try {
            $enviado = $emailService->sendGestanteTextMessage(
                $gestante,
                $validated['assunto'],
                $validated['mensagem'],
                $validated['tipo'] === 'personalizada' ? null : $validated['tipo'],
            );
        } catch (Throwable $e) {
            Log::error('Falha ao enviar e-mail manual pela tela da gestante.', [
                'gestante_id' => $gestante->id,
                'tipo' => $validated['tipo'],
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        if ($enviado) {
            return back()->with('success', 'E-mail enviado com sucesso.');
        }

        return back()->with('warning', $emailService->getLastError() ?: 'Não foi possível enviar o e-mail no momento.');
    }

    public function edit($id)
    {
        $this->authorize(User::ABILITY_GESTANTES_MANAGE);
        $gestante = Gestante::findOrFail($id);

        return view('gestantes.edit', compact('gestante'));

    }

    public function update(Request $request, $id, EmailService $emailService)
    {
        $this->authorize(User::ABILITY_GESTANTES_MANAGE);
        $gestante = Gestante::findOrFail($id);

        $request->merge([
            'cpf' => $this->normalizeDigits($request->input('cpf')),
            'telefone' => $this->normalizeDigits($request->input('telefone')),
            'email' => $emailService->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'data_nascimento' => 'required|date',
            'cpf' => ['required', 'regex:/^\d{11}$/', Rule::unique('gestantes', 'cpf')->ignore($gestante->id)],
            'telefone' => ['required', 'regex:/^\d{10,13}$/', Rule::unique('gestantes', 'telefone')->ignore($gestante->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('gestantes', 'email')->ignore($gestante->id)],
        ], [
            'cpf.required' => 'Informe o CPF da gestante.',
            'cpf.regex' => 'O CPF deve conter 11 dígitos.',
            'telefone.required' => 'Informe o telefone da gestante.',
            'telefone.regex' => 'O telefone deve ter entre 10 e 13 dígitos (com DDD ou 55).',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado para outra gestante.',
        ]);

        $gestante->update($validated);

        return redirect()->route('gestantes.show', $gestante->id)->with('success', 'Dados da gestante atualizados com sucesso!');
    }

    public function destroy($id)
    {
        $this->authorize(User::ABILITY_GESTANTES_MANAGE);
        $gestante = Gestante::findOrFail($id);
        $gestante->delete();

        return redirect()->route('gestantes.index')->with('success', 'Gestante removida com sucesso!');
    }

    private function normalizeDigits(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits !== '' ? $digits : null;
    }
}
