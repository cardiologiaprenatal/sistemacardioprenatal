<?php

namespace App\Models;

use App\Services\EmailService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_MEDICO = 'medico';

    public const ROLE_SECRETARIA = 'secretaria';

    public const ROLE_ENFERMEIRA = 'enfermeira';

    public const ROLE_ADMIN = 'admin';

    public const ABILITY_DASHBOARD = 'dashboard.view';

    public const ABILITY_GESTANTES_VIEW = 'gestantes.view';

    public const ABILITY_GESTANTES_MANAGE = 'gestantes.manage';

    public const ABILITY_CONSULTAS_MANAGE = 'consultas.manage';

    public const ABILITY_CONSULTAS_IMPORT = 'consultas.import';

    public const ABILITY_CONSULTAS_AGENDAR = 'consultas.agendar';

    public const ABILITY_CONSULTAS_AGENDA = 'consultas.agenda';

    public const ABILITY_AGENDAMENTOS_INDICAR = 'agendamentos.indicar';

    public const ABILITY_ACOMPANHAMENTOS = 'acompanhamentos.manage';

    public const ABILITY_ANALISES = 'analises.view';

    public const ABILITY_ML = 'ml.view';

    public const ABILITY_WHATSAPP = 'whatsapp.manage';

    /**
     * Mapa perfil → capacidades. Cadastros antigos com role `user` entram como médico.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_ABILITIES = [
        self::ROLE_MEDICO => [
            self::ABILITY_DASHBOARD,
            self::ABILITY_GESTANTES_VIEW,
            self::ABILITY_GESTANTES_MANAGE,
            self::ABILITY_CONSULTAS_MANAGE,
            self::ABILITY_CONSULTAS_IMPORT,
            self::ABILITY_CONSULTAS_AGENDA,
            self::ABILITY_AGENDAMENTOS_INDICAR,
            self::ABILITY_ANALISES,
            self::ABILITY_ML,
            self::ABILITY_WHATSAPP,
        ],
        self::ROLE_SECRETARIA => [
            self::ABILITY_GESTANTES_VIEW,
            self::ABILITY_GESTANTES_MANAGE,
            self::ABILITY_CONSULTAS_MANAGE,
            self::ABILITY_CONSULTAS_AGENDA,
            self::ABILITY_CONSULTAS_AGENDAR,
            self::ABILITY_ACOMPANHAMENTOS,
            self::ABILITY_WHATSAPP,
        ],
        self::ROLE_ENFERMEIRA => [
            self::ABILITY_GESTANTES_VIEW,
            self::ABILITY_CONSULTAS_MANAGE,
            self::ABILITY_ANALISES,
        ],
    ];

    protected $fillable = [
        'name',
        'crm',
        'telefone',
        'email',
        'password',
        'role',
        'approved_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_MEDICO,
            self::ROLE_SECRETARIA,
            self::ROLE_ENFERMEIRA,
            self::ROLE_ADMIN,
        ];
    }

    /**
     * Perfis disponíveis no cadastro público. Admin só é criado internamente.
     *
     * @return list<string>
     */
    public static function publicRoles(): array
    {
        return [
            self::ROLE_MEDICO,
            self::ROLE_SECRETARIA,
            self::ROLE_ENFERMEIRA,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_MEDICO => 'Médico',
            self::ROLE_SECRETARIA => 'Secretária do médico',
            self::ROLE_ENFERMEIRA => 'Enfermeira',
            self::ROLE_ADMIN => 'Administrador',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function publicRoleOptions(): array
    {
        return array_intersect_key(self::roleOptions(), array_flip(self::publicRoles()));
    }

    /**
     * @return list<string>
     */
    public static function abilities(): array
    {
        return [
            self::ABILITY_DASHBOARD,
            self::ABILITY_GESTANTES_VIEW,
            self::ABILITY_GESTANTES_MANAGE,
            self::ABILITY_CONSULTAS_MANAGE,
            self::ABILITY_CONSULTAS_IMPORT,
            self::ABILITY_CONSULTAS_AGENDAR,
            self::ABILITY_CONSULTAS_AGENDA,
            self::ABILITY_AGENDAMENTOS_INDICAR,
            self::ABILITY_ACOMPANHAMENTOS,
            self::ABILITY_ANALISES,
            self::ABILITY_ML,
            self::ABILITY_WHATSAPP,
        ];
    }

    public function normalizedRole(): string
    {
        $role = strtolower(trim((string) $this->role));

        if (in_array($role, self::roles(), true)) {
            return $role;
        }

        return self::ROLE_MEDICO;
    }

    public function roleLabel(): string
    {
        return self::roleOptions()[$this->normalizedRole()] ?? 'Médico';
    }

    public function hasAbility(string $ability): bool
    {
        if ($this->normalizedRole() === self::ROLE_ADMIN) {
            return in_array($ability, self::abilities(), true);
        }

        return in_array($ability, self::ROLE_ABILITIES[$this->normalizedRole()] ?? [], true);
    }

    public function isAdmin(): bool
    {
        return $this->normalizedRole() === self::ROLE_ADMIN;
    }

    public function isMedico(): bool
    {
        return $this->normalizedRole() === self::ROLE_MEDICO;
    }

    public function isSecretaria(): bool
    {
        return $this->normalizedRole() === self::ROLE_SECRETARIA;
    }

    public function canDirecionarAtendimentoGestante(): bool
    {
        return ($this->isMedico() || $this->isAdmin())
            && $this->hasAbility(self::ABILITY_CONSULTAS_MANAGE);
    }

    public function canAtenderWhatsappGestante(): bool
    {
        return $this->hasAbility(self::ABILITY_WHATSAPP)
            && ($this->isSecretaria() || $this->isAdmin());
    }

    public function homeRouteName(): string
    {
        return match ($this->normalizedRole()) {
            self::ROLE_SECRETARIA, self::ROLE_ENFERMEIRA => 'gestantes.index',
            default => 'dashboard',
        };
    }

    public function homeRoute(): string
    {
        return route($this->homeRouteName());
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function deleteAuthSessions(): void
    {
        $table = (string) config('session.table', 'sessions');
        if ($table !== '' && Schema::hasTable($table)) {
            DB::table($table)->where('user_id', $this->id)->delete();
        }
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        app(EmailService::class)->sendPasswordReset($this, $token);
    }
}
