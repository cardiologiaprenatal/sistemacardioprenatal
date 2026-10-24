<?php

use App\Models\Consulta;
use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function gestanteFicha(array $attrs = []): Gestante
{
    return Gestante::query()->create(array_merge([
        'nome' => 'Helena Ficha',
        'data_nascimento' => '1991-04-18',
        'cpf' => '39053344705',
        'telefone' => '21911112222',
    ], $attrs));
}

test('ficha abre na aba consulta sem botao de entrada nova consulta', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRM-FICHA-1');
    $gestante = gestanteFicha();
    Consulta::query()->create([
        'gestante_id' => $gestante->id,
        'consulta_numero' => 1,
        'data_consulta' => '2026-08-20',
        'idade_gestacional' => 22,
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

    $this->actingAs($medico)
        ->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertDontSee('data-ficha-aba="consulta"', false)
        ->assertDontSee('data-ficha-aba="whatsapp"', false)
        ->assertSee('Dados clínicos da gestante')
        ->assertSee('Consulta atual')
        ->assertSee('Histórico de consultas')
        ->assertSee('Exames')
        ->assertSee('Análises / risco')
        ->assertSee('Observações')
        ->assertSee('Ações relacionadas à consulta')
        ->assertSee('Aceitar atendimento personalizado')
        ->assertSee('Encaminhar para fila do ecocardiograma')
        ->assertSee('Editar consulta atual')
        ->assertSee('Registrar próxima consulta')
        ->assertDontSee('+ Nova consulta')
        ->assertDontSee('Conversa WhatsApp');
});

test('ficha da gestante nao exibe conversa whatsapp mesmo com aceite', function () {
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-FICHA-WA');
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRM-FICHA-ACEITE');
    $gestante = gestanteFicha(['cpf' => '52998224725', 'telefone' => '21911113333']);
    Consulta::query()->create([
        'gestante_id' => $gestante->id,
        'consulta_numero' => 1,
        'data_consulta' => '2026-08-20',
        'idade_gestacional' => 22,
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
    $this->actingAs($medico)
        ->post(route('gestantes.atendimento-personalizado', $gestante))
        ->assertRedirect();
    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'mensagem' => 'Mensagem só da aba WhatsApp',
        'tipo' => 'entrada',
        'categoria' => GestanteWhatsapp::CATEGORIA_CONVERSA,
    ]);

    $this->actingAs($secretaria)
        ->get(route('gestantes.show', ['gestante' => $gestante, 'aba' => 'whatsapp']))
        ->assertOk()
        ->assertSee('Consulta atual')
        ->assertDontSee('data-ficha-aba="whatsapp"', false)
        ->assertDontSee('id="painel-whatsapp"', false)
        ->assertDontSee('id="gestante-conversa"', false)
        ->assertDontSee('Mensagem só da aba WhatsApp');

    $this->actingAs($secretaria)
        ->get(route('historico-whatsapp.index', ['gestante_id' => $gestante->id]))
        ->assertOk()
        ->assertSee('Mensagem só da aba WhatsApp');
});

test('enfermeira permanece na consulta e nao ve a aba whatsapp', function () {
    $enfermeira = usuarioComPerfil(User::ROLE_ENFERMEIRA, 'CRM-FICHA-ENF');
    $gestante = gestanteFicha(['cpf' => '11144477735', 'telefone' => '21911114444']);

    $this->actingAs($enfermeira)
        ->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertSee('Registrar consulta')
        ->assertDontSee('data-ficha-aba="whatsapp"', false)
        ->assertDontSee('Mensagem só da aba WhatsApp');
});
