<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OTP de redefinição guardado no cache já usado pela aplicação (curto, de uso único).
 * Não reutiliza password_reset_tokens: aquela tabela é a do link por e-mail (chave = e-mail).
 */
class WhatsappPasswordResetService
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_SENDS_PER_HOUR = 5;

    public const GRANT_TTL_MINUTES = 10;

    public const MESSAGE_SENT = 'Enviamos um código para o WhatsApp cadastrado.';

    public const MESSAGE_NOT_FOUND = 'Não encontramos uma conta com este telefone. Confira o número cadastrado e tente novamente.';

    public const MESSAGE_UNAVAILABLE = 'Não foi possível enviar o código pelo WhatsApp agora. Tente novamente em instantes.';

    public const MESSAGE_INVALID_PHONE = 'Informe um telefone válido com DDD.';

    public const MESSAGE_INVALID_CODE = 'Código inválido ou expirado.';

    public const MESSAGE_TOO_MANY = 'Muitas tentativas. Solicite um novo código.';

    public const MESSAGE_CODE_OK = 'Código confirmado. Defina a nova senha.';

    public const MESSAGE_PASSWORD_OK = 'Senha redefinida. Entre com seu CRM e a nova senha.';

    public const MESSAGE_RESET_INVALID = 'Este processo expirou ou é inválido. Solicite um novo código.';

    public function __construct(
        private WhatsAppService $whatsapp,
    ) {}

    /**
     * @return array{status: string, message: string, retry_after: int}
     */
    public function sendCode(string $phone): array
    {
        $digits = $this->whatsapp->normalizePhoneToBrazilE164Digits($phone);
        if ($digits === null) {
            return $this->sendResult('invalid_phone', self::MESSAGE_INVALID_PHONE, 0);
        }

        $user = $this->findUniqueUser($digits);
        if ($user === null) {
            return $this->sendResult('not_found', self::MESSAGE_NOT_FOUND, 0);
        }

        $digits = $this->whatsapp->normalizePhoneToBrazilE164Digits($user->telefone) ?? $digits;

        if (! $this->whatsapp->isConfigured()) {
            return $this->sendResult('unavailable', self::MESSAGE_UNAVAILABLE, 0);
        }

        $otpKey = $this->otpKey($digits);
        $existing = $this->readOtp($otpKey);
        if ($existing !== null) {
            $elapsed = now()->timestamp - (int) $existing['sent_at'];
            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                return $this->sendResult(
                    'sent',
                    self::MESSAGE_SENT,
                    self::RESEND_COOLDOWN_SECONDS - $elapsed,
                );
            }
        }

        $sendKey = $this->sendCountKey($digits);
        if ((int) Cache::get($sendKey, 0) >= self::MAX_SENDS_PER_HOUR) {
            return $this->sendResult('sent', self::MESSAGE_SENT, self::RESEND_COOLDOWN_SECONDS);
        }

        $code = (string) random_int(100000, 999999);
        $sent = $this->whatsapp->sendMessage(
            $digits,
            "Cardioprenatal: seu código para redefinir a senha é {$code}. "
            .'Ele vale por '.self::CODE_TTL_MINUTES.' minutos e só pode ser usado uma vez. '
            .'Se você não solicitou, ignore esta mensagem.'
        );

        if (! $sent) {
            Cache::forget($otpKey);
            Log::warning('Redefinição por WhatsApp: envio não concluído.', [
                'user_id' => $user->id,
            ]);

            return $this->sendResult('unavailable', self::MESSAGE_UNAVAILABLE, 0);
        }

        $this->forgetGrantForUser($user->id);
        $expiresAt = now()->addMinutes(self::CODE_TTL_MINUTES);
        Cache::put($otpKey, [
            'user_id' => $user->id,
            'hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now()->timestamp,
            'expires_at' => $expiresAt->timestamp,
        ], $expiresAt);
        Cache::put($sendKey, (int) Cache::get($sendKey, 0) + 1, now()->addHour());

        return $this->sendResult('sent', self::MESSAGE_SENT, self::RESEND_COOLDOWN_SECONDS);
    }

    /**
     * @return array{ok: bool, message: string, reset_token: ?string}
     */
    public function verifyCode(string $phone, string $code): array
    {
        $digits = $this->whatsapp->normalizePhoneToBrazilE164Digits($phone);
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($digits === null || strlen($code) !== 6) {
            return ['ok' => false, 'message' => self::MESSAGE_INVALID_CODE, 'reset_token' => null];
        }

        $otpKey = $this->otpKey($digits);
        $payload = $this->readOtp($otpKey);
        if ($payload === null) {
            return ['ok' => false, 'message' => self::MESSAGE_INVALID_CODE, 'reset_token' => null];
        }

        $expiresAt = Carbon::createFromTimestamp((int) $payload['expires_at']);
        $payload['attempts'] = (int) $payload['attempts'] + 1;

        if (! Hash::check($code, (string) $payload['hash'])) {
            if ($payload['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($otpKey);

                return ['ok' => false, 'message' => self::MESSAGE_TOO_MANY, 'reset_token' => null];
            }

            Cache::put($otpKey, $payload, $expiresAt);

            return ['ok' => false, 'message' => self::MESSAGE_INVALID_CODE, 'reset_token' => null];
        }

        Cache::forget($otpKey);
        $token = Str::random(64);
        $grantKey = $this->grantKey($token);
        $this->forgetGrantForUser((int) $payload['user_id']);
        Cache::put($grantKey, [
            'user_id' => (int) $payload['user_id'],
            'phone' => $digits,
        ], now()->addMinutes(self::GRANT_TTL_MINUTES));
        Cache::put($this->grantPointerKey((int) $payload['user_id']), $grantKey, now()->addMinutes(self::GRANT_TTL_MINUTES));

        return ['ok' => true, 'message' => self::MESSAGE_CODE_OK, 'reset_token' => $token];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function resetPassword(string $phone, string $token, string $password): array
    {
        $digits = $this->whatsapp->normalizePhoneToBrazilE164Digits($phone);
        if ($digits === null || $token === '') {
            return ['ok' => false, 'message' => self::MESSAGE_RESET_INVALID];
        }

        $grantKey = $this->grantKey($token);
        $grant = Cache::get($grantKey);
        if (! is_array($grant) || ($grant['phone'] ?? null) !== $digits) {
            return ['ok' => false, 'message' => self::MESSAGE_RESET_INVALID];
        }

        $user = User::query()->find($grant['user_id'] ?? 0);
        if (! $user || $this->whatsapp->normalizePhoneToBrazilE164Digits($user->telefone) !== $digits) {
            Cache::forget($grantKey);

            return ['ok' => false, 'message' => self::MESSAGE_RESET_INVALID];
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
        $user->deleteAuthSessions();

        Cache::forget($grantKey);
        Cache::forget($this->grantPointerKey($user->id));
        Cache::forget($this->otpKey($digits));

        return ['ok' => true, 'message' => self::MESSAGE_PASSWORD_OK];
    }

    /**
     * @return array{status: string, message: string, retry_after: int}
     */
    private function sendResult(string $status, string $message, int $retryAfter): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'retry_after' => max(0, $retryAfter),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readOtp(string $key): ?array
    {
        $payload = Cache::get($key);
        if (! is_array($payload) || ! isset($payload['hash'], $payload['expires_at'], $payload['user_id'])) {
            return null;
        }

        if (Carbon::createFromTimestamp((int) $payload['expires_at'])->isPast()) {
            Cache::forget($key);

            return null;
        }

        return $payload;
    }

    private function findUniqueUser(string $e164): ?User
    {
        $matches = User::query()
            ->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->get()
            ->filter(function (User $user) use ($e164) {
                return $this->whatsapp->normalizePhoneToBrazilE164Digits($user->telefone) === $e164;
            })
            ->values();

        if ($matches->count() !== 1) {
            if ($matches->count() > 1) {
                Log::warning('Redefinição por WhatsApp: telefone ambíguo, código não enviado.', [
                    'matches' => $matches->count(),
                ]);
            }

            return null;
        }

        return $matches->first();
    }

    private function forgetGrantForUser(int $userId): void
    {
        $pointer = Cache::pull($this->grantPointerKey($userId));
        if (is_string($pointer) && $pointer !== '') {
            Cache::forget($pointer);
        }
    }

    private function otpKey(string $digits): string
    {
        return 'pwd-otp:'.hash('sha256', $digits);
    }

    private function sendCountKey(string $digits): string
    {
        return 'pwd-otp-sends:'.hash('sha256', $digits);
    }

    private function grantKey(string $token): string
    {
        return 'pwd-grant:'.hash('sha256', $token);
    }

    private function grantPointerKey(int $userId): string
    {
        return 'pwd-grant-user:'.$userId;
    }
}
