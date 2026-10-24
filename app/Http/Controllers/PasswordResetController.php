<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(
        private EmailService $emails,
    ) {}

    public function create(): View
    {
        return view('auth.forgot-password-email');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => $this->emails->normalizeEmail($request->input('email')) ?? '',
        ]);

        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Informe o e-mail cadastrado.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        if ($this->emails->isStandby()) {
            return back()->withErrors([
                'email' => 'A recuperação por e-mail está indisponível no momento. Use a recuperação por WhatsApp.',
            ]);
        }

        $generic = 'Se este e-mail estiver cadastrado, enviaremos um link para redefinir a senha.';
        $user = User::query()->where('email', $request->input('email'))->first();
        if (! $user) {
            return back()->with('status', $generic);
        }

        $existing = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();
        $throttleSeconds = (int) config('auth.passwords.users.throttle', 60);
        if ($existing && $existing->created_at && now()->parse($existing->created_at)->gt(now()->subSeconds($throttleSeconds))) {
            return back()->with('status', $generic);
        }

        $token = Password::broker()->createToken($user);
        if (! $this->emails->sendPasswordReset($user, $token)) {
            Password::broker()->deleteToken($user);

            return back()->withErrors([
                'email' => 'Não foi possível enviar o e-mail agora. Tente novamente ou use o WhatsApp.',
            ]);
        }

        return back()->with('status', $generic);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => $this->emails->normalizeEmail($request->input('email')) ?? '',
        ]);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->deleteAuthSessions();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors([
                    'email' => 'Este link é inválido ou expirou. Solicite uma nova recuperação.',
                ])
                ->withInput($request->only('email'));
        }

        return redirect()
            ->route('login')
            ->with('success', 'Senha redefinida com sucesso. Entre com seu CRM e a nova senha.');
    }
}
