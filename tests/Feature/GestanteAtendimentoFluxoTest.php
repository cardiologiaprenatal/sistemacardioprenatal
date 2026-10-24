<?php

use App\Models\Acompanhamento;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Models\User;
use App\Services\WhatsAppMenuFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function gestanteFluxo(array $attrs = []): Gestante
{
    return Gestante::query()->create(array_merge([
        'nome' => 'Paciente Fluxo',
        'data_nascimento' => '1992-03-10',
        'cpf' => '39053344705',
        'telefone' => '11970001111',
    ], $attrs));
}

function consultaFluxo(Gestante $gestante): Consulta
{
    return Consulta::query()->create([
        'gestante_id' => $gestante->id,
        'consulta_numero' => 1,
        'data_consulta' => '2026-09-19',
        'idade_gestacional' => 24,
        'pressao_sistolica' => 118,
        'bpm_materno' => 80,
        'saturacao' => 98,
        'temperatura_corporal' => 36.5,
        'altura' => 160,
        'peso' => 65,
        'diabetes_gestacional' => false,
        'hipertensao' => false,
        'hipertensao_pre_eclampsia' => false,
        'obesidade_pre_gestacional' => false,
        'historico_familiar_chd' => false,
        'uso_medicamentos' => false,
        'tabagismo' => false,
        'alcoolismo' => false,
        'frequencia_cardiaca_fetal' => 140,
        'circunferencia_cefalica_fetal_mm' => 180,
        'circunferencia_abdominal_mm' => 160,
        'comprimento_femur_mm' => 40,
        'translucencia_nucal_mm' => 1.5,
        'doppler_ducto_venoso' => 'normal',
        'eixo_cardiaco' => 'normal',
        'quatro_camaras' => 'normal',
        'chd_confirmada' => false,
    ]);
}

test('medico nao ve a aba whatsapp na ficha da gestante', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRMFLOWMED');
    $gestante = gestanteFluxo();
    consultaFluxo($gestante);

    $this->actingAs($medico)
        ->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertSee('Aceitar atendimento personalizado')
        ->assertDontSee('data-ficha-aba="whatsapp"', false);
});

test('medico nao envia whatsapp e so aceita apos consulta', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRMFLOWWA');
    $gestante = gestanteFluxo(['cpf' => '52998224725', 'telefone' => '11970002222']);

    $this->actingAs($medico)
        ->post(route('gestantes.atendimento-personalizado', $gestante))
        ->assertSessionHasErrors('consulta');

    consultaFluxo($gestante);

    $this->actingAs($medico)
        ->post(route('gestantes.whatsapp', $gestante), [
            'mensagem' => 'Olá, sou o médico.',
        ])
        ->assertForbidden();

    $this->actingAs($medico)
        ->post(route('gestantes.atendimento-personalizado', $gestante))
        ->assertRedirect(route('gestantes.show', $gestante));

    $gestante->refresh();
    expect($gestante->atendimentoPersonalizadoLiberado())->toBeTrue()
        ->and($gestante->atendimento_personalizado_aceito_por)->toBe($medico->id)
        ->and($gestante->fluxo_atendimento)->toBe(Gestante::FLUXO_ATENDIMENTO_PERSONALIZADO);

    $this->actingAs($medico)
        ->post(route('gestantes.atendimento-personalizado', $gestante))
        ->assertRedirect();

    expect(Gestante::query()->where('id', $gestante->id)->count())->toBe(1);
});

test('secretaria so atende whatsapp depois do aceite do medico', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRMFLOWSEC1');
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRMFLOWSEC2');
    $gestante = gestanteFluxo(['cpf' => '11144477735', 'telefone' => '11970003333']);
    consultaFluxo($gestante);

    $this->actingAs($secretaria)
        ->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertDontSee('data-ficha-aba="whatsapp"', false);

    $this->actingAs($secretaria)
        ->post(route('gestantes.whatsapp', $gestante), [
            'mensagem' => 'Mensagem da secretaria.',
        ])
        ->assertSessionHas('warning');

    $this->actingAs($secretaria)
        ->post(route('gestantes.atendimento-personalizado', $gestante))
        ->assertForbidden();

    $this->actingAs($medico)
        ->post(route('gestantes.atendimento-personalizado', $gestante))
        ->assertRedirect();

    $this->actingAs($secretaria)
        ->get(route('gestantes.show', ['gestante' => $gestante, 'aba' => 'whatsapp']))
        ->assertOk()
        ->assertDontSee('data-ficha-aba="whatsapp"', false)
        ->assertDontSee('id="gestante-conversa"', false);

    $this->actingAs($secretaria)
        ->get(route('historico-whatsapp.index', ['gestante_id' => $gestante->id]))
        ->assertOk();
});

test('encaminhamento para fila eco e visivel para a secretaria sem duplicar', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRMFLOWECO1');
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRMFLOWECO2');
    $gestante = gestanteFluxo(['cpf' => '85351346893', 'telefone' => '11970004444', 'nome' => 'Gestante Eco']);
    consultaFluxo($gestante);

    $this->actingAs($medico)
        ->post(route('gestantes.encaminhar-ecocardiograma', $gestante))
        ->assertRedirect(route('gestantes.show', $gestante));

    $this->actingAs($medico)
        ->post(route('gestantes.encaminhar-ecocardiograma', $gestante))
        ->assertRedirect();

    expect(Acompanhamento::query()->where('gestante_id', $gestante->id)->count())->toBe(1);

    $item = Acompanhamento::query()->first();
    expect($item->motivo)->toBe(Acompanhamento::MOTIVO_ECO_RECOMENDADA)
        ->and($item->encaminhado_por_user_id)->toBe($medico->id)
        ->and($item->encaminhado_em)->not->toBeNull();

    $gestante->refresh();
    expect($gestante->fluxo_atendimento)->toBe(Gestante::FLUXO_AGUARDANDO_ECOCARDIOGRAMA);

    $this->actingAs($secretaria)
        ->get(route('acompanhamentos.index'))
        ->assertOk()
        ->assertSee('Gestante Eco')
        ->assertSee($medico->name)
        ->assertSee('Aguardando agendamento');
});

test('whatsapp da gestante pede aceite do medico para atendimento personalizado', function () {
    $gestante = gestanteFluxo(['cpf' => '07311631754', 'telefone' => '11970005555']);
    $fluxo = app(WhatsAppMenuFlowService::class)->handle($gestante, '1');

    expect($fluxo['intencao'])->toBe('atendimento_bloqueado')
        ->and($fluxo['resposta'])->toContain('ainda não foi autorizado');
});
