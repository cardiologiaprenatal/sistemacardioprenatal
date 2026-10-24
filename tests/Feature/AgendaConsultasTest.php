<?php

use App\Models\Acompanhamento;
use App\Models\AgendamentoSolicitacao;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('medico indica e secretaria agenda a partir da tela de consultas', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRM-IND-1');
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-IND-SEC');
    $gestante = Gestante::query()->create([
        'nome' => 'Beatriz Lima',
        'data_nascimento' => '1992-08-20',
        'cpf' => '39053344705',
        'telefone' => '21977770000',
    ]);

    $this->actingAs($medico)
        ->get(route('agenda.index'))
        ->assertOk()
        ->assertSee('Consultas')
        ->assertSee('Solicitar agendamento')
        ->assertDontSee('Nova consulta')
        ->assertDontSee('Lembretes WhatsApp')
        ->assertDontSee('Confirmação WhatsApp');

    $this->actingAs($medico)
        ->post(route('agenda.indicar'), [
            'gestante_id' => $gestante->id,
            'tipo' => AgendamentoSolicitacao::TIPO_CONSULTA,
            'observacao' => 'Retorno clínico em 15 dias.',
        ])
        ->assertRedirect(route('agenda.index', ['gestante_id' => $gestante->id]))
        ->assertSessionHas('success');

    $solicitacao = AgendamentoSolicitacao::query()->first();
    expect($solicitacao)->not->toBeNull()
        ->and($solicitacao->status)->toBe(AgendamentoSolicitacao::STATUS_PENDENTE)
        ->and($solicitacao->solicitado_por)->toBe($medico->id)
        ->and($solicitacao->observacao)->toContain('Retorno clínico');

    $this->actingAs(usuarioComPerfil(User::ROLE_ENFERMEIRA, 'CRM-IND-ENF'))
        ->get(route('agenda.index'))
        ->assertForbidden();

    $this->actingAs($secretaria)
        ->get(route('agenda.index'))
        ->assertOk()
        ->assertSee('Pendências')
        ->assertSee('Nova consulta')
        ->assertSee('Beatriz Lima')
        ->assertSee('Retorno clínico')
        ->assertDontSee('Solicitar agendamento')
        ->assertDontSee('Lembretes WhatsApp')
        ->assertDontSee('Confirmação WhatsApp');

    $data = now()->addDays(3)->toDateString();
    $this->actingAs($secretaria)
        ->post(route('agenda.store'), [
            'gestante_id' => $gestante->id,
            'data' => $data,
            'horario' => '11:00',
            'profissional_id' => $medico->id,
            'solicitacao_id' => $solicitacao->id,
            'tipo' => AgendamentoSolicitacao::TIPO_CONSULTA,
        ])
        ->assertRedirect(route('agenda.index'))
        ->assertSessionHas('success');

    $solicitacao->refresh();
    $agendamento = ConsultaAgendamento::query()->first();

    expect($solicitacao->status)->toBe(AgendamentoSolicitacao::STATUS_AGENDADA)
        ->and($agendamento)->not->toBeNull()
        ->and($agendamento->solicitacao_id)->toBe($solicitacao->id)
        ->and($agendamento->agendado_para->format('Y-m-d H:i'))->toBe($data.' 11:00');
});

test('secretaria agenda ecocardiograma pendente da analise pela tela de consultas', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRM-ECO-MED');
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-ECO-SEC');
    $gestante = Gestante::query()->create([
        'nome' => 'Carla Eco',
        'cpf' => '52998224725',
        'telefone' => '21966660000',
    ]);
    $eco = Acompanhamento::query()->create([
        'gestante_id' => $gestante->id,
        'status' => Acompanhamento::STATUS_PENDENTE,
        'motivo' => Acompanhamento::MOTIVO_ECO_RECOMENDADA,
        'titulo' => 'Ecocardiograma recomendado',
    ]);

    $this->actingAs($secretaria)
        ->get(route('agenda.index'))
        ->assertOk()
        ->assertSee('Carla Eco')
        ->assertSee('Ecocardiograma');

    $this->actingAs($secretaria)
        ->post(route('agenda.store'), [
            'gestante_id' => $gestante->id,
            'data' => now()->addDay()->toDateString(),
            'horario' => '08:30',
            'profissional_id' => $medico->id,
            'tipo' => AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA,
            'acompanhamento_id' => $eco->id,
        ])
        ->assertRedirect(route('agenda.index'));

    expect($eco->fresh()->agendado_para)->not->toBeNull()
        ->and(ConsultaAgendamento::query()->first()->tipo)->toBe(AgendamentoSolicitacao::TIPO_ECOCARDIOGRAMA);
});

test('secretaria nao indica agendamento', function () {
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-NO-IND');
    $gestante = Gestante::query()->create([
        'nome' => 'Paciente Sem Indicacao',
        'cpf' => '11144477735',
        'telefone' => '21955550000',
    ]);

    $this->actingAs($secretaria)
        ->post(route('agenda.indicar'), [
            'gestante_id' => $gestante->id,
            'tipo' => AgendamentoSolicitacao::TIPO_CONSULTA,
        ])
        ->assertForbidden();
});

test('secretaria reagenda e cancela consulta na tela de consultas', function () {
    $medico = usuarioComPerfil(User::ROLE_MEDICO, 'CRM-REAG-MED');
    $secretaria = usuarioComPerfil(User::ROLE_SECRETARIA, 'CRM-REAG-SEC');
    $gestante = Gestante::query()->create([
        'nome' => 'Elena Reagendada',
        'cpf' => '39053344705',
        'telefone' => '21944440000',
    ]);

    $this->actingAs($secretaria)
        ->post(route('agenda.store'), [
            'gestante_id' => $gestante->id,
            'data' => now()->addDays(2)->toDateString(),
            'horario' => '09:00',
            'profissional_id' => $medico->id,
            'tipo' => AgendamentoSolicitacao::TIPO_CONSULTA,
        ])
        ->assertRedirect(route('agenda.index'));

    $agendamento = ConsultaAgendamento::query()->first();
    $novaData = now()->addDays(4)->toDateString();

    $this->actingAs($secretaria)
        ->put(route('agenda.update', $agendamento), [
            'data' => $novaData,
            'horario' => '16:15',
            'profissional_id' => $medico->id,
        ])
        ->assertRedirect(route('agenda.index'))
        ->assertSessionHas('success');

    $agendamento->refresh();
    expect($agendamento->agendado_para->format('Y-m-d H:i'))->toBe($novaData.' 16:15')
        ->and($agendamento->status)->toBe(ConsultaAgendamento::STATUS_AGENDADA);

    $this->actingAs($medico)
        ->post(route('agenda.cancel', $agendamento))
        ->assertForbidden();

    $this->actingAs($secretaria)
        ->post(route('agenda.cancel', $agendamento))
        ->assertRedirect(route('agenda.index'))
        ->assertSessionHas('success');

    $agendamento->refresh();
    expect($agendamento->status)->toBe(ConsultaAgendamento::STATUS_CANCELADA)
        ->and($agendamento->solicitacao)->toBeNull();
});
