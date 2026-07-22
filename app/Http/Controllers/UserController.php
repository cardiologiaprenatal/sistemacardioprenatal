<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            $request->merge(['crm' => $crm]);

            $validator = Validator::make($request->all(), [
                'nome' => ['required', 'string', 'max:255'],
                'crm' => ['required', 'string', 'min:3', 'max:20', 'unique:users,crm'],
                'telefone' => ['required', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            User::create([
                'name' => $request->nome,
                'crm' => $request->crm,
                'telefone' => $request->telefone,
                // Plain password: User model casts 'password' => 'hashed'
                'password' => $request->password,
                'role' => 'user',
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao registrar usuário: '.$e->getMessage());

            return response()->json([
                'message' => 'Erro ao criar usuário. Verifique se a tabela users possui as colunas name, crm e telefone.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Cadastro realizado com sucesso! Faça login com seu CRM e senha.',
            'redirect' => route('login'),
        ], 201);
    }
}