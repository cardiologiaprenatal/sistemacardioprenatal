<?php

namespace App\Http\Controllers;

use App\Services\Auth\WhatsappPasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappPasswordResetController extends Controller
{
    public function __construct(
        private WhatsappPasswordResetService $resets,
    ) {}

    public function create(): View
    {
        return view('auth.forgot-password', [
            'resendSeconds' => WhatsappPasswordResetService::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telefone' => ['required', 'string', 'max:20'],
        ], [
            'telefone.required' => 'Informe o telefone cadastrado.',
        ]);

        $result = $this->resets->sendCode($data['telefone']);

        return response()->json([
            'message' => $result['message'],
            'retry_after' => $result['retry_after'],
        ], $result['status'] === 'sent' ? 200 : 422);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telefone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:12'],
        ], [
            'telefone.required' => 'Informe o telefone cadastrado.',
            'code.required' => 'Informe o código recebido.',
        ]);

        $result = $this->resets->verifyCode($data['telefone'], $data['code']);
        $payload = ['message' => $result['message']];
        if ($result['ok']) {
            $payload['reset_token'] = $result['reset_token'];
        }

        return response()->json($payload, $result['ok'] ? 200 : 422);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telefone' => ['required', 'string', 'max:20'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.required' => 'Informe a nova senha.',
        ]);

        $result = $this->resets->resetPassword($data['telefone'], $data['reset_token'], $data['password']);

        return response()->json([
            'message' => $result['message'],
            'redirect' => $result['ok'] ? route('login') : null,
        ], $result['ok'] ? 200 : 422);
    }
}
