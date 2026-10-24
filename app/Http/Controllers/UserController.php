<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        try {
            $crm = strtoupper(preg_replace('/[\s\-]+/u', '', trim((string) $request->input('crm', ''))));
            $request->merge([
                'crm' => $crm,
            ]);

            $validator = Validator::make($request->all(), [
                'nome' => ['required', 'string', 'max:255'],
                'crm' => ['required', 'string', 'min:3', 'max:20', 'unique:users,crm'],
                'telefone' => ['required', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'role' => ['required', Rule::in(User::publicRoles())],
            ], [
                'password.confirmed' => 'A confirmação da senha não confere.',
                'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
                'nome.required' => 'Informe o nome completo.',
                'crm.required' => 'Informe o CRM.',
                'telefone.required' => 'Informe o telefone.',
                'role.required' => 'Escolha o perfil de acesso.',
                'role.in' => 'Escolha um perfil de acesso válido.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            User::create([
                'name' => $request->nome,
                'crm' => $request->crm,
                'email' => null,
                'telefone' => $request->telefone,
                'password' => $request->password,
                'role' => $request->role,
                'approved_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao registrar usuário: '.$e->getMessage());

            return response()->json([
                'message' => 'Erro ao criar usuário.',
            ], 500);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Cadastro realizado. Entre com seu CRM e senha.',
            'redirect' => route('login'),
        ], 201);
    }
}
