<?php

use App\Models\AnaliseHistorico;
use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function gestanteAcesso(): Gestante
{
    return Gestante::query()->create([
        'nome' => 'Paciente Acesso',
        'data_nascimento' => '1990-01-15',
        'cpf' => '39053344705',
        'telefone' => '11977776666',
    ]);
}

test('cadastro antigo com role user continua com acesso de medico', function () {
    $user = User::query()->create([
        'name' => 'Dra. Legado',
        'crm' => 'CRM-LEGADO-1',
        'password' => 'password',
        'role' => 'user',
    ]);

    expect($user->normalizedRole())->toBe(User::ROLE_MEDICO)
        ->and($user->hasAbility(User::ABILITY_DASHBOARD))->toBeTrue()
        ->and($user->hasAbility(User::ABILITY_ACOMPANHAMENTOS))->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('medico acessa dashboard, ml, whatsapp e importacao, mas nao a fila de eco', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRMMED1');

    $this->actingAs($medico)->get(route('dashboard'))->assertOk();
    $this->actingAs($medico)
        ->get(route('gestantes.index'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Importar consultas')
        ->assertDontSee('Análise ML')
        ->assertDontSee('Fila ecocardiograma')
        ->assertDontSee('href="'.route('cohort.gestantes.index').'"', false)
        ->assertDontSee('href="'.route('agenda.index').'"', false);

    $this->actingAs($medico)->get(route('cohort.gestantes.index'))->assertOk();
    Http::fake([
        '*/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
    ]);

    $this->actingAs($medico)->get(route('whatsapp.index'))->assertOk();
    $this->actingAs($medico)->get(route('historico-whatsapp.index'))->assertOk();
    $this->actingAs($medico)->get('/telegram/prompt')->assertNotFound();
    $this->actingAs($medico)->get(route('consultas.import'))->assertOk();
    $this->actingAs($medico)->get(route('agenda.index'))->assertOk();
    $this->actingAs($medico)->get(route('acompanhamentos.index'))->assertForbidden();
});

test('secretaria acessa operacao e nao ve analises de risco nem ml', function () {
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-SEC-1');
    $gestante = gestanteAcesso();

    $this->actingAs($secretaria)
        ->get(route('gestantes.index'))
        ->assertOk()
        ->assertSee('Gestantes')
        ->assertSee('Fila eco')
        ->assertSee('WhatsApp')
        ->assertSee('Histórico WhatsApp')
        ->assertDontSee('Telegram')
        ->assertDontSee('Dashboard')
        ->assertDontSee('Análise ML')
        ->assertDontSee('Importar consultas')
        ->assertDontSee('>Risco<')
        ->assertDontSee('href="'.route('cohort.gestantes.index').'"', false)
        ->assertDontSee('href="'.route('agenda.index').'"', false);

    $this->actingAs($secretaria)->get(route('acompanhamentos.index'))->assertOk();
    $this->actingAs($secretaria)->get(route('agenda.index'))->assertOk();
    Http::fake([
        '*/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
    ]);

    $this->actingAs($secretaria)->get(route('whatsapp.index'))->assertOk();
    $this->actingAs($secretaria)->get(route('historico-whatsapp.index'))->assertOk();
    $this->actingAs($secretaria)->get(route('consultas.create', $gestante->id))->assertOk();
    $this->actingAs($secretaria)->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertDontSee('Histórico de análises')
        ->assertDontSee('Último risco:');

    $this->actingAs($secretaria)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($secretaria)->get(route('cohort.gestantes.index'))->assertForbidden();
    $this->actingAs($secretaria)->get(route('consultas.import'))->assertForbidden();
});

test('enfermeira acessa consultas e analises, mas nao fila nem whatsapp', function () {
    $enfermeira = usuarioComPerfil(User::ROLE_ENFERMEIRA, 'CRM-ENF-1');
    $gestante = gestanteAcesso();

    $this->actingAs($enfermeira)
        ->get(route('gestantes.index'))
        ->assertOk()
        ->assertSee('Gestantes')
        ->assertDontSee('Fila eco')
        ->assertDontSee('Histórico WhatsApp')
        ->assertDontSee('Telegram')
        ->assertDontSee('Dashboard')
        ->assertDontSee('Análise ML');

    $this->actingAs($enfermeira)->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertSee('Consulta')
        ->assertSee('Registrar consulta')
        ->assertSee('Salvar consulta')
        ->assertDontSee('+ Nova consulta')
        ->assertDontSee('Conversa WhatsApp')
        ->assertDontSee('data-ficha-aba="whatsapp"', false);

    $this->actingAs($enfermeira)->get(route('consultas.create', $gestante->id))->assertOk();
    $this->actingAs($enfermeira)->get(route('gestantes.create'))->assertForbidden();
    $this->actingAs($enfermeira)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($enfermeira)->get(route('acompanhamentos.index'))->assertForbidden();
    $this->actingAs($enfermeira)->get(route('whatsapp.index'))->assertForbidden();
    $this->actingAs($enfermeira)->get(route('historico-whatsapp.index'))->assertForbidden();
    $this->actingAs($enfermeira)->get(route('agenda.index'))->assertForbidden();
    $this->actingAs($enfermeira)->get(route('cohort.gestantes.index'))->assertForbidden();
    $this->actingAs($enfermeira)->get(route('consultas.import'))->assertForbidden();
    $this->actingAs($enfermeira)->get('/api/gestante-whatsapp')->assertForbidden();
});

test('convidado nao acessa conversas whatsapp pela url nem pela api', function () {
    $gestante = gestanteAcesso();
    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'mensagem' => 'Mensagem confidencial da paciente',
        'tipo' => 'entrada',
        'categoria' => GestanteWhatsapp::CATEGORIA_CONVERSA,
    ]);

    $this->get('/api/gestante-whatsapp')->assertRedirect(route('login'));
    $this->getJson('/api/gestante-whatsapp')->assertUnauthorized();
    $this->getJson('/api/gestante-whatsapp/'.$gestante->id)
        ->assertUnauthorized()
        ->assertDontSee('Mensagem confidencial da paciente');
});

test('enfermeira nao acessa conversa whatsapp pela api mesmo conhecendo o id', function () {
    $enfermeira = usuarioComPerfil(User::ROLE_ENFERMEIRA, 'CRM-ENF-API');
    $gestante = gestanteAcesso();
    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'mensagem' => 'Texto restrito da conversa',
        'tipo' => 'entrada',
        'categoria' => GestanteWhatsapp::CATEGORIA_CONVERSA,
    ]);

    $this->actingAs($enfermeira)
        ->getJson('/api/gestante-whatsapp/'.$gestante->id)
        ->assertForbidden()
        ->assertDontSee('Texto restrito da conversa');

    $this->actingAs($enfermeira)
        ->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertDontSee('Texto restrito da conversa')
        ->assertDontSee('/api/gestante-whatsapp/'.$gestante->id);
});

test('secretaria acessa whatsapp mas a ficha nao carrega analises de risco', function () {
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-SEC-ANALISE');
    $gestante = gestanteAcesso();
    AnaliseHistorico::query()->create([
        'gestante_id' => $gestante->id,
        'evento_tipo' => AnaliseHistorico::EVENTO_CONSULTA,
        'classificacao_risco' => 'alto',
        'probabilidade_ccf' => 0.81,
        'status' => 'concluida',
        'full_response' => ['ok' => true],
        'analise' => ['resumo_clinico' => 'Resumo clinico secreto para teste'],
    ]);

    $this->actingAs($secretaria)
        ->getJson('/api/gestante-whatsapp/'.$gestante->id)
        ->assertOk();

    $this->actingAs($secretaria)
        ->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertDontSee('Histórico de análises')
        ->assertDontSee('Último risco:')
        ->assertDontSee('Resumo clinico secreto para teste')
        ->assertDontSee('Laudo clínico (IA)');
});

test('dashboard mostra analise descritiva da populacao', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRMDASHSEP1');

    $this->actingAs($medico)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Perfil da população')
        ->assertSee('Análise por variáveis')
        ->assertSee('Características da população')
        ->assertSee('Catálogo de experimentos')
        ->assertDontSee('Análise Individual')
        ->assertDontSee('>Risco clínico<', false)
        ->assertDontSee('id="painel-individual"', false)
        ->assertDontSee('id="tabelas"', false)
        ->assertSee('id="indicadores"', false)
        ->assertSee('id="analises"', false)
        ->assertSee('id="graficos"', false);
});

test('admin acessa todas as telas do sistema', function () {
    $admin = usuarioComPerfil(User::ROLE_ADMIN, 'SPADMIN1');
    $gestante = gestanteAcesso();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->hasAbility(User::ABILITY_DASHBOARD))->toBeTrue()
        ->and($admin->hasAbility(User::ABILITY_ACOMPANHAMENTOS))->toBeTrue()
        ->and($admin->hasAbility(User::ABILITY_WHATSAPP))->toBeTrue()
        ->and($admin->hasAbility(User::ABILITY_ML))->toBeTrue();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('gestantes.index'))->assertOk();
    $this->actingAs($admin)->get(route('gestantes.show', $gestante))->assertOk();
    $this->actingAs($admin)->get(route('consultas.create', $gestante->id))->assertOk();
    Http::fake([
        '*/api/sessions/*' => Http::response(['status' => 'WORKING'], 200),
    ]);

    $this->actingAs($admin)->get(route('whatsapp.index'))->assertOk();
    $this->actingAs($admin)->get(route('historico-whatsapp.index'))->assertOk();
    $this->actingAs($admin)->get(route('acompanhamentos.index'))->assertOk();
    $this->actingAs($admin)->get(route('agenda.index'))->assertOk();
    $this->actingAs($admin)->get(route('cohort.gestantes.index'))->assertOk();
    $this->actingAs($admin)->get(route('consultas.import'))->assertOk();
});

test('login redireciona secretaria para gestantes e nao para o dashboard', function () {
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRMSECLOGIN1');

    $this->postJson(route('auth'), [
        'crm' => 'CRMSECLOGIN1',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('redirect', route('gestantes.index'));
});
