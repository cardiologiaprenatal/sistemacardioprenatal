<?php

use App\Models\Gestante;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('logout encerra a sessao e nao apaga gestantes nem o usuario', function () {
    $medico = User::query()->create([
        'name' => 'Dra. Persistencia',
        'crm' => 'CRM-LOGOUT-'.uniqid(),
        'password' => 'password',
    ]);

    $gestante = Gestante::query()->create([
        'nome' => 'Ana Persistente',
        'data_nascimento' => '1992-05-20',
        'cpf' => '39053344705',
        'telefone' => '11988887777',
    ]);

    $this->actingAs($medico)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();

    expect(Gestante::query()->whereKey($gestante->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($medico->id)->exists())->toBeTrue();

    $this->actingAs($medico)
        ->get(route('gestantes.index'))
        ->assertOk()
        ->assertSee('Ana Persistente');
});
