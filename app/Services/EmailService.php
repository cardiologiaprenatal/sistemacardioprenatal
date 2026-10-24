<?php

namespace App\Services;

use App\Mail\GestanteMensagemMail;
use App\Mail\ResetSenhaMail;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;
use App\Models\GestanteEmail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    private ?string $lastError = null;

    public function __construct(
        private WhatsAppMessageTemplates $templates,
    ) {}

    public function isStandby(): bool
    {
        return (bool) config('mail.standby', true);
    }

    public function isEnabled(): bool
    {
        if ($this->isStandby()) {
            return false;
        }

        $mailer = (string) config('mail.default');

        return $mailer !== '' && $mailer !== 'array';
    }

    public function isLogMailer(): bool
    {
        return config('mail.default') === 'log';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
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
     * @return array<string, string>
     */
    public function previewPromptsForGestante(Gestante $gestante): array
    {
        return $this->templates->previewForGestante($gestante);
    }

    /**
     * @return array<string, string>
     */
    public function assuntosParaCatalogo(): array
    {
        $out = [];
        foreach (array_keys($this->templates->catalog()) as $key) {
            $out[$key] = $this->assuntoParaTipo($key);
        }

        return $out;
    }

    public function assuntoParaTipo(string $tipo): string
    {
        return match ($tipo) {
            WhatsAppMessageTemplates::BOAS_VINDAS => 'Cardioprenatal. Rastreio de cardiopatias congênitas em gestantes diabéticas',
            WhatsAppMessageTemplates::RISCO_ALTO => 'Acompanhamento do pré-natal. Cardioprenatal',
            WhatsAppMessageTemplates::ECO_RECOMENDADA => 'Ecocardiograma fetal. Cardioprenatal',
            WhatsAppMessageTemplates::RETORNO => 'Lembrete de retorno. Cardioprenatal',
            WhatsAppMessageTemplates::LEMBRETE_CONSULTA => 'Lembrete de consulta. Cardioprenatal',
            WhatsAppMessageTemplates::CONFIRMACAO_CONSULTA => 'Consulta confirmada. Cardioprenatal',
            WhatsAppMessageTemplates::LEMBRETE_EXAME => 'Lembrete de exame. Cardioprenatal',
            WhatsAppMessageTemplates::MANUAL => 'Acompanhamento. Cardioprenatal',
            WhatsAppMessageTemplates::CONFIRMACAO_CONTATO => 'Recebemos seu contato. Cardioprenatal',
            WhatsAppMessageTemplates::ORIENTACAO_GERAL => 'Orientação do pré-natal. Cardioprenatal',
            WhatsAppMessageTemplates::MENU_INICIAL => 'Como podemos ajudar. Cardioprenatal',
            WhatsAppMessageTemplates::ATENDENTE_HUMANO => 'Você fala com um atendente. Cardioprenatal',
            WhatsAppMessageTemplates::RETOMAR_ASSISTENTE => 'Resposta automática retomada. Cardioprenatal',
            default => 'Mensagem do Cardioprenatal',
        };
    }

    public function sendGestanteWelcomeMessage(Gestante $gestante): bool
    {
        $nome = trim((string) ($gestante->nome ?? ''));
        if ($nome === '') {
            Log::warning('E-mail: boas-vindas não enviadas — nome da gestante vazio.', [
                'gestante_id' => $gestante->id,
            ]);

            $this->lastError = 'Nome da gestante vazio.';

            return false;
        }

        return $this->sendGestanteTextMessage(
            $gestante,
            $this->assuntoParaTipo(WhatsAppMessageTemplates::BOAS_VINDAS),
            $this->templates->buildForGestante(WhatsAppMessageTemplates::BOAS_VINDAS, $gestante),
            WhatsAppMessageTemplates::BOAS_VINDAS,
        );
    }

    public function sendConfirmacaoConsulta(Gestante $gestante, ConsultaAgendamento $agendamento): bool
    {
        return $this->sendGestanteTextMessage(
            $gestante,
            $this->assuntoParaTipo(WhatsAppMessageTemplates::CONFIRMACAO_CONSULTA),
            $this->templates->textoConfirmacaoConsulta($gestante, $agendamento),
            WhatsAppMessageTemplates::CONFIRMACAO_CONSULTA,
        );
    }

    public function sendGestanteTextMessage(
        Gestante $gestante,
        string $assunto,
        string $texto,
        ?string $tipo = null,
    ): bool {
        $this->lastError = null;

        if ($this->isStandby()) {
            Log::info('E-mail: envio ignorado — SMTP em espera.', [
                'gestante_id' => $gestante->id,
                'tipo' => $tipo,
            ]);

            return false;
        }

        $assunto = trim($assunto);
        $texto = trim($texto);

        if ($assunto === '') {
            $this->lastError = 'Informe o assunto do e-mail.';

            return false;
        }

        if ($texto === '') {
            Log::warning('E-mail: envio ignorado — mensagem vazia.', [
                'gestante_id' => $gestante->id,
            ]);
            $this->lastError = 'Informe o texto da mensagem.';

            return false;
        }

        $email = $this->normalizeEmail($gestante->email);
        if ($email === null) {
            Log::warning('E-mail: envio ignorado — e-mail vazio.', [
                'gestante_id' => $gestante->id,
            ]);
            $this->lastError = 'Esta gestante não possui e-mail cadastrado.';

            return false;
        }

        try {
            Mail::to($email)->send(new GestanteMensagemMail($gestante, $assunto, $texto));
            $this->persistOutgoing($gestante->id, $email, $assunto, $texto, $tipo);

            Log::info('E-mail enviado para gestante.', [
                'gestante_id' => $gestante->id,
                'tipo' => $tipo,
                'mailer' => config('mail.default'),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('E-mail: falha ao enviar mensagem.', [
                'gestante_id' => $gestante->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->lastError = 'Não foi possível enviar o e-mail no momento.';

            return false;
        }
    }

    public function sendPasswordReset(User $user, string $token): bool
    {
        $this->lastError = null;

        $email = $this->normalizeEmail($user->email);
        if ($email === null) {
            $this->lastError = 'Usuário sem e-mail cadastrado.';

            return false;
        }

        $expires = (int) config('auth.passwords.users.expire', 60);
        $url = url(route('password.reset', [
            'token' => $token,
            'email' => $email,
        ], false));

        if ($this->isStandby()) {
            Log::info('E-mail: redefinição de senha ignorada — SMTP em espera.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new ResetSenhaMail($user, $url, $expires));

            Log::info('E-mail de redefinição de senha enviado.', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('E-mail: falha ao enviar redefinição de senha.', [
                'user_id' => $user->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->lastError = 'Não foi possível enviar o e-mail no momento.';

            return false;
        }
    }

    public function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = strtolower(trim($value));

        return $email !== '' ? $email : null;
    }

    private function persistOutgoing(
        int $gestanteId,
        string $destinatario,
        string $assunto,
        string $mensagem,
        ?string $tipo,
    ): void {
        try {
            GestanteEmail::query()->create([
                'gestante_id' => $gestanteId,
                'destinatario' => $destinatario,
                'assunto' => $assunto,
                'mensagem' => $mensagem,
                'tipo' => $tipo,
            ]);
        } catch (Throwable $e) {
            Log::error('E-mail: falha ao salvar mensagem no histórico (gestante_emails).', [
                'gestante_id' => $gestanteId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
