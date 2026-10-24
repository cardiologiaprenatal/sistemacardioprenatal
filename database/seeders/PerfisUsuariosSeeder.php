<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cria (ou atualiza) os três perfis de acesso da equipe.
 *
 * Login: CRM + senha. O CRM pode ser digitado com ou sem hífen (SP-100001 ou SP100001).
 *
 * Uso: php artisan db:seed --class=PerfisUsuariosSeeder
 */
class PerfisUsuariosSeeder extends Seeder
{
    public const SENHA = 'senha1234';

    /**
     * @return list<array{name: string, crm: string, email: string, telefone: string, role: string}>
     */
    public static function contas(): array
    {
        return [
            [
                'name' => 'Admin CardioPrenatal',
                'crm' => 'SP100000',
                'email' => 'admin@cardioprenatal.test',
                'telefone' => '11988880000',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Dra. Ana Médica',
                'crm' => 'SP100001',
                'email' => 'medico@cardioprenatal.test',
                'telefone' => '11988880001',
                'role' => User::ROLE_MEDICO,
            ],
            [
                'name' => 'Maria Secretária',
                'crm' => 'SP100002',
                'email' => 'secretaria@cardioprenatal.test',
                'telefone' => '11988880002',
                'role' => User::ROLE_SECRETARIA,
            ],
            [
                'name' => 'Enf. Carla Oliveira',
                'crm' => 'SP100003',
                'email' => 'enfermeira@cardioprenatal.test',
                'telefone' => '11988880003',
                'role' => User::ROLE_ENFERMEIRA,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::contas() as $conta) {
            User::query()->updateOrCreate(
                ['crm' => $conta['crm']],
                [
                    'name' => $conta['name'],
                    'email' => $conta['email'],
                    'telefone' => $conta['telefone'],
                    'password' => self::SENHA,
                    'role' => $conta['role'],
                ]
            );
        }
    }
}
