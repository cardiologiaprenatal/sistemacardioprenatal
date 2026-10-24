<?php

use App\Models\User;
use App\Services\Auth\WhatsappPasswordResetService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Cache::flush();
    config([
        'services.waha.enabled' => true,
        'services.waha.base_url' => 'http://waha.test',
        'services.waha.session' => 'default',
        'services.waha.no_api_key' => true,
    ]);
    Http::fake([
        'waha.test/*' => Http::response(['numberExists' => true, 'chatId' => '5511988887777@c.us'], 200),
    ]);
});

function contaAprovada(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Dra. Login',
        'crm' => 'QA'.strtoupper(uniqid()),
        'email' => 'login.'.uniqid().'@example.test',
        'telefone' => '11988887777',
        'password' => 'senha-correta',
        'role' => User::ROLE_MEDICO,
        'approved_at' => now(),
    ], $overrides));
}

function codigoEnviado(string $phone = '11988887777'): string
{
    $text = '';
    foreach (Http::recorded() as [$request]) {
        if (str_contains($request->url(), 'sendText')) {
            $text = (string) ($request->data()['text'] ?? '');
        }
    }

    expect($text)->toMatch('/\b\d{6}\b/');
    preg_match('/\b(\d{6})\b/', $text, $matches);

    return $matches[1];
}

test('login valido redireciona e senha invalida e negada', function () {
    $user = contaAprovada();

    $this->postJson(route('auth'), [
        'crm' => $user->crm,
        'password' => 'errada-123',
    ])->assertUnauthorized()
        ->assertJsonPath('errors.crm.0', 'CRM ou senha inválidos');

    $this->postJson(route('auth'), [
        'crm' => '  '.strtolower($user->crm).'  ',
        'password' => 'senha-correta',
    ])->assertOk()
        ->assertJsonPath('redirect', route('dashboard'));
});

test('login sem sessao nao abre pagina protegida e logout encerra a sessao', function () {
    $user = contaAprovada();

    $this->get(route('gestantes.index'))->assertRedirect(route('login'));

    $this->postJson(route('auth'), [
        'crm' => $user->crm,
        'password' => 'senha-correta',
    ])->assertOk();

    $this->get(route('gestantes.index'))->assertOk();
    $this->post(route('logout'))->assertRedirect('/');
    $this->get(route('gestantes.index'))->assertRedirect(route('login'));
});

test('login bloqueia apos cinco tentativas invalidas', function () {
    $user = contaAprovada();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('auth'), [
            'crm' => $user->crm,
            'password' => 'errada-'.$i,
        ])->assertUnauthorized();
    }

    $this->postJson(route('auth'), [
        'crm' => $user->crm,
        'password' => 'senha-correta',
    ])->assertStatus(429)
        ->assertJsonPath('message', 'Muitas tentativas. Aguarde um minuto e tente novamente.')
        ->assertJsonMissingPath('exception');
});

test('cadastro exige confirmacao e a conta nova entra em seguida', function () {
    $this->postJson(route('users.store'), [
        'nome' => 'Dra. Nova',
        'crm' => 'QA-CAD-1',
        'telefone' => '11977776666',
        'password' => 'senha-nova-1',
        'password_confirmation' => 'outra-senha',
        'role' => User::ROLE_MEDICO,
    ])->assertStatus(422);

    $this->postJson(route('users.store'), [
        'nome' => 'Dra. Nova',
        'crm' => 'QA-CAD-1',
        'telefone' => '11977776666',
        'password' => 'senha-nova-1',
        'password_confirmation' => 'senha-nova-1',
        'role' => User::ROLE_MEDICO,
    ])->assertCreated()
        ->assertJsonMissingValidationErrors(['email']);

    $user = User::query()->where('crm', 'QACAD1')->first();
    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->approved_at)->not->toBeNull()
        ->and(Hash::check('senha-nova-1', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('senha-nova-1');

    $this->postJson(route('auth'), [
        'crm' => 'QACAD1',
        'password' => 'senha-nova-1',
    ])->assertOk()
        ->assertJsonPath('redirect', route('dashboard'));

    $this->get(route('gestantes.index'))->assertOk();
    $this->get(route('dashboard'))->assertOk();
});

test('login entra mesmo sem approved_at', function () {
    $user = contaAprovada([
        'crm' => 'QAPEND1',
        'approved_at' => null,
        'role' => User::ROLE_ENFERMEIRA,
    ]);

    $this->postJson(route('auth'), [
        'crm' => $user->crm,
        'password' => 'senha-correta',
    ])->assertOk()
        ->assertJsonPath('redirect', route('gestantes.index'))
        ->assertJsonMissingPath('errors');
});

test('whatsapp so envia codigo quando o telefone esta no cadastro', function () {
    $messages = [];
    Log::listen(function ($event) use (&$messages) {
        $messages[] = $event->message.' '.json_encode($event->context);
    });

    $this->postJson(route('password.whatsapp.send'), [
        'telefone' => '11900000000',
    ])->assertStatus(422)
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_NOT_FOUND);

    Http::assertNothingSent();

    contaAprovada(['telefone' => '(11) 98888-7777']);

    $this->postJson(route('password.whatsapp.send'), [
        'telefone' => '(21) 98888-7777',
    ])->assertStatus(422)
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_NOT_FOUND);

    Http::assertNothingSent();

    $this->postJson(route('password.whatsapp.send'), [
        'telefone' => '11988887777',
    ])->assertOk()
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_SENT);

    $code = codigoEnviado();
    expect(implode("\n", $messages))->not->toContain($code);

    Cache::flush();
    Http::fake([
        'waha.test/*' => Http::response(['numberExists' => true, 'chatId' => '5511988887777@c.us'], 200),
    ]);

    $this->postJson(route('password.whatsapp.send'), [
        'telefone' => '+55 (11) 98888-7777',
    ])->assertOk()
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_SENT);
});

test('whatsapp aceita o codigo correto e recusa incorreto expirado e reutilizado', function () {
    $user = contaAprovada(['telefone' => '11988887777']);

    $this->postJson(route('password.whatsapp.send'), ['telefone' => '11 988887777'])
        ->assertOk();
    $code = codigoEnviado();

    $this->postJson(route('password.whatsapp.verify'), [
        'telefone' => '11988887777',
        'code' => '000000',
    ])->assertStatus(422)
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_INVALID_CODE)
        ->assertJsonMissingPath('reset_token');

    $this->travel(11)->minutes();
    $this->postJson(route('password.whatsapp.verify'), [
        'telefone' => '11988887777',
        'code' => $code,
    ])->assertStatus(422)
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_INVALID_CODE);

    $this->travelBack();
    $this->postJson(route('password.whatsapp.send'), ['telefone' => '11988887777'])->assertOk();
    $code = codigoEnviado();

    $ok = $this->postJson(route('password.whatsapp.verify'), [
        'telefone' => '11988887777',
        'code' => $code,
    ])->assertOk()
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_CODE_OK);

    $token = $ok->json('reset_token');
    expect($token)->toBeString()->not->toBe('');

    $this->postJson(route('password.whatsapp.verify'), [
        'telefone' => '11988887777',
        'code' => $code,
    ])->assertStatus(422)
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_INVALID_CODE);

    $this->postJson(route('password.whatsapp.update'), [
        'telefone' => '11988887777',
        'reset_token' => $token,
        'password' => 'nova-senha-123',
        'password_confirmation' => 'diferente-123',
    ])->assertStatus(422);

    DB::table('sessions')->insert([
        'id' => 'sessao-whatsapp',
        'user_id' => $user->id,
        'payload' => 'teste',
        'last_activity' => time(),
    ]);

    $this->postJson(route('password.whatsapp.update'), [
        'telefone' => '11988887777',
        'reset_token' => $token,
        'password' => 'nova-senha-123',
        'password_confirmation' => 'nova-senha-123',
    ])->assertOk()
        ->assertJsonPath('redirect', route('login'));

    expect(Hash::check('nova-senha-123', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('senha-correta', $user->fresh()->password))->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'sessao-whatsapp')->exists())->toBeFalse();

    $this->postJson(route('password.whatsapp.update'), [
        'telefone' => '11988887777',
        'reset_token' => $token,
        'password' => 'outra-senha-999',
        'password_confirmation' => 'outra-senha-999',
    ])->assertStatus(422);

    $this->postJson(route('auth'), [
        'crm' => $user->crm,
        'password' => 'senha-correta',
    ])->assertUnauthorized();

    $this->postJson(route('auth'), [
        'crm' => $user->crm,
        'password' => 'nova-senha-123',
    ])->assertOk();
});

test('whatsapp bloqueia apos cinco codigos incorretos', function () {
    contaAprovada(['telefone' => '11988887777']);
    $this->postJson(route('password.whatsapp.send'), ['telefone' => '11988887777'])->assertOk();

    for ($i = 0; $i < 4; $i++) {
        $this->postJson(route('password.whatsapp.verify'), [
            'telefone' => '11988887777',
            'code' => '11111'.$i,
        ])->assertStatus(422)
            ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_INVALID_CODE);
    }

    $this->postJson(route('password.whatsapp.verify'), [
        'telefone' => '11988887777',
        'code' => '222222',
    ])->assertStatus(422)
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_TOO_MANY);
});

test('reenvio imediato nao dispara outro whatsapp', function () {
    contaAprovada(['telefone' => '11988887777']);

    $this->postJson(route('password.whatsapp.send'), ['telefone' => '11988887777'])->assertOk();
    $this->postJson(route('password.whatsapp.send'), ['telefone' => '11988887777'])
        ->assertOk()
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_SENT);

    $sends = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'sendText'))->count();
    expect($sends)->toBe(1);
});

test('falha no whatsapp nao informa sucesso', function () {
    contaAprovada(['telefone' => '11988887777']);
    $http = new \Illuminate\Http\Client\Factory;
    $http->fake(function ($request) {
        if (str_contains($request->url(), 'sendText')) {
            return \Illuminate\Http\Client\Factory::response(['error' => 'falha'], 500);
        }

        return \Illuminate\Http\Client\Factory::response(['numberExists' => false], 200);
    });
    Http::swap($http);

    $this->postJson(route('password.whatsapp.send'), ['telefone' => '11988887777'])
        ->assertStatus(422)
        ->assertJsonPath('message', WhatsappPasswordResetService::MESSAGE_UNAVAILABLE);

    $this->postJson(route('password.whatsapp.verify'), [
        'telefone' => '11988887777',
        'code' => '123456',
    ])->assertStatus(422);
});

test('resposta de erro de autenticacao nao expoe excecao interna', function () {
    $this->postJson(route('auth'), [])
        ->assertStatus(422)
        ->assertJsonMissingPath('exception')
        ->assertJsonMissingPath('trace');
});
