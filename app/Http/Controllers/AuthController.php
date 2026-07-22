<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $crm = strtoupper(preg_replace('/[\s\-]+/u', '', trim((string) $request->input('crm', ''))));
        $request->merge(['crm' => $crm]);

        $credentials = $request->validate([
            'crm' => ['required', 'string', 'min:3', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        try {
            $auth = Auth::attempt([
                'crm' => $crm,
                'password' => $credentials['password'],
            ], $request->boolean('remember'));
        } catch (\Throwable $e) {
            Log::error('Erro ao tentar login: '.$e->getMessage());

            if (str_contains($e->getMessage(), 'Bcrypt algorithm')) {
                return response()->json(['errors' => ['crm' => ['Erro de compatibilidade de senha. Registre-se novamente.']]], 401);
            }

            return response()->json([
                'message' => 'Erro interno no servidor.',
                'error' => $e->getMessage(),
            ], 500);
        }

        if ($auth) {
            $request->session()->regenerate();

            return response()->json(['message' => 'Login realizado com sucesso!']);
        }

        return response()->json([
            'errors' => ['crm' => ['CRM ou senha inválidos']],
        ], 401);
    }

    public function logout()
    {
        Auth::logout();

        return redirect('/');
    }
}