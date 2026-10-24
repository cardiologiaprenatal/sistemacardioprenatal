<?php

use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function sessaoWhatsapp(string $status): void
{
    test()->mock(WhatsAppService::class, function ($mock) use ($status) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('fetchSessionStatus')->andReturn($status);
        $mock->shouldReceive('templateCatalog')->andReturn([
            'boas_vindas' => 'Boas-vindas',
            'menu_inicial' => 'Menu inicial (3 opções)',
        ]);
        $mock->shouldReceive('previewPromptsForGestante')->andReturn([
            'boas_vindas' => 'Olá',
        ]);
        $mock->shouldNotReceive('sendGestanteTextMessage');
        $mock->shouldNotReceive('sendMessage');
        $mock->shouldNotReceive('sendGestanteMenuInicial');
    });
}

function gestanteCanal(string $nome, string $cpf, ?string $telefone = '11988887777'): Gestante
{
    return Gestante::query()->create([
        'nome' => $nome,
        'data_nascimento' => '1992-04-02',
        'cpf' => $cpf,
        'telefone' => $telefone,
    ]);
}

test('visitante nao abre a tela do whatsapp', function () {
    $this->get(route('whatsapp.index'))->assertRedirect(route('login'));
});

test('enfermeira nao abre a tela do whatsapp', function () {
    $this->actingAs(usuarioComPerfil(User::ROLE_ENFERMEIRA))
        ->get(route('whatsapp.index'))
        ->assertForbidden();
});

test('menu mostra whatsapp e historico e esconde telegram', function () {
    sessaoWhatsapp('WORKING');

    $this->actingAs(usuarioComPerfil(User::ROLE_SECRETARIA))
        ->get(route('whatsapp.index'))
        ->assertOk()
        ->assertSee('WhatsApp')
        ->assertSee('Histórico WhatsApp')
        ->assertSee('href="'.route('whatsapp.index').'"', false)
        ->assertSee('href="'.route('historico-whatsapp.index').'"', false)
        ->assertDontSee('Telegram')
        ->assertDontSee('/telegram', false);
});

test('tela mostra o estado real da sessao e desativa o envio', function () {
    sessaoWhatsapp('SCAN_QR_CODE');
    $gestante = gestanteCanal('Ana Canal', '39053344705');

    $this->actingAs(usuarioComPerfil(User::ROLE_MEDICO))
        ->get(route('whatsapp.index'))
        ->assertOk()
        ->assertSee('SCAN_QR_CODE')
        ->assertSee('O envio não está pronto')
        ->assertSee('O canal não está pronto')
        ->assertSee('Ana Canal')
        ->assertSee('id="gestante-conversa"', false)
        ->assertSee('class="wa-send" disabled', false)
        ->assertSee('data-send-url="'.route('gestantes.whatsapp', $gestante).'"', false)
        ->assertSee('data-poll-url="'.url('/api/gestante-whatsapp/'.$gestante->id).'"', false)
        ->assertDontSee('Histórico de atendimento')
        ->assertDontSee('whatsapp.enviar');
});

test('chat lista todas as gestantes e abre a conversa mais recente', function () {
    sessaoWhatsapp('WORKING');

    $sem = gestanteCanal('Zoe Sem', '15350946056', '11955554444');
    $antiga = gestanteCanal('Ana Antiga', '39053344705', '11988887777');
    $recente = gestanteCanal('Bia Recente', '52998224725', '11977776666');

    GestanteWhatsapp::query()->create([
        'gestante_id' => $antiga->id,
        'mensagem' => 'Mensagem antiga gravada',
        'tipo' => 'entrada',
        'categoria' => GestanteWhatsapp::CATEGORIA_CONVERSA,
        'canal' => GestanteWhatsapp::CANAL_WHATSAPP,
    ]);
    GestanteWhatsapp::query()->create([
        'gestante_id' => $recente->id,
        'mensagem' => 'Última fala da conversa',
        'tipo' => 'saida',
        'categoria' => GestanteWhatsapp::CATEGORIA_CONVERSA,
        'canal' => GestanteWhatsapp::CANAL_WHATSAPP,
    ]);

    $this->actingAs(usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-SEC-WA'))
        ->get(route('whatsapp.index'))
        ->assertOk()
        ->assertSee('WORKING')
        ->assertSee('Bia Recente')
        ->assertSee('Ana Antiga')
        ->assertSee('Zoe Sem')
        ->assertSee('Última fala da conversa')
        ->assertSee('Mensagem antiga gravada')
        ->assertSee('Ainda não há conversa.')
        ->assertSeeInOrder(['Bia Recente', 'Ana Antiga', 'Zoe Sem'])
        ->assertSee('id="gestanteChatThread"', false)
        ->assertSee('data-poll-url="'.url('/api/gestante-whatsapp/'.$recente->id).'"', false)
        ->assertSee('data-send-url="'.route('gestantes.whatsapp', $recente).'"', false)
        ->assertSee(route('historico-whatsapp.index'), false)
        ->assertSee('setInterval', false)
        ->assertDontSee('class="wa-send" disabled', false)
        ->assertDontSee('whatsapp.enviar');

    expect($sem->id)->not->toBe($antiga->id)
        ->and(Route::has('whatsapp.enviar'))->toBeFalse();

    $this->actingAs(usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-SEC-WA2'))
        ->getJson('/api/gestante-whatsapp/'.$recente->id)
        ->assertOk()
        ->assertJsonPath('mensagens.0.mensagem', 'Última fala da conversa');
});

test('gestante sem telefone abre a conversa sem envio', function () {
    sessaoWhatsapp('WORKING');
    gestanteCanal('Lia Sem Fone', '87748248800', null);

    $this->actingAs(usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-SEC-SF'))
        ->get(route('whatsapp.index'))
        ->assertOk()
        ->assertSee('Lia Sem Fone')
        ->assertSee('Sem telefone cadastrado')
        ->assertSee('class="wa-send" disabled', false);
});

test('rotas antigas do telegram respondem 404', function () {
    $this->actingAs(usuarioComPerfil(User::ROLE_ADMIN, 'CRM-ADM-404'))
        ->get('/telegram/prompt')
        ->assertNotFound();

    $this->postJson('/api/telegram/webhook', ['update_id' => 1])->assertNotFound();
});
