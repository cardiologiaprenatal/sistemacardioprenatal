<?php

use App\Mail\GestanteMensagemMail;
use App\Models\Acompanhamento;
use App\Models\ConsultaAgendamento;
use App\Models\Gestante;
use App\Models\GestanteEmail;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function medicoAgenda(): User
{
    return User::query()->create([
        'name' => 'Dra. Helena Costa',
        'crm' => 'CRM-AGENDA-1',
        'password' => 'password',
        'role' => User::ROLE_MEDICO,
    ]);
}

function secretariaAgenda(): User
{
    return User::query()->create([
        'name' => 'Maria Secretária',
        'crm' => 'CRM-AGENDA-SEC',
        'password' => 'password',
        'role' => User::ROLE_SECRETARIA,
    ]);
}

function gestanteAgenda(array $attrs = []): Gestante
{
    return Gestante::query()->create(array_merge([
        'nome' => 'Marina Alves',
        'data_nascimento' => '1994-03-12',
        'cpf' => '52998224725',
        'telefone' => '21988880000',
        'email' => 'marina.alves@example.com',
    ], $attrs));
}

test('secretaria agenda consulta e o canal envia confirmacao sem misturar a tela', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['id' => 'wamid.ok'], 200)]);
    config([
        'mail.standby' => false,
        'mail.default' => 'array',
        'services.waha.enabled' => true,
        'services.waha.base_url' => 'http://waha.test',
        'services.waha.no_api_key' => true,
        'services.waha.session' => 'default',
    ]);

    $medico = medicoAgenda();
    $secretaria = secretariaAgenda();
    $gestante = gestanteAgenda();
    $data = now()->addDay()->toDateString();

    $this->actingAs($secretaria)
        ->post(route('agenda.store'), [
            'gestante_id' => $gestante->id,
            'data' => $data,
            'horario' => '14:30',
            'profissional_id' => $medico->id,
        ])
        ->assertRedirect(route('agenda.index'))
        ->assertSessionHas('success')
        ->assertSessionMissing('warning');

    $agendamento = ConsultaAgendamento::query()->first();

    expect($agendamento)->not->toBeNull()
        ->and($agendamento->gestante_id)->toBe($gestante->id)
        ->and($agendamento->profissional_id)->toBe($medico->id)
        ->and($agendamento->status)->toBe(ConsultaAgendamento::STATUS_AGENDADA)
        ->and($agendamento->tipo)->toBe(ConsultaAgendamento::TIPO_CONSULTA)
        ->and($agendamento->profissional_nome)->toContain('Dra. Helena Costa')
        ->and($agendamento->email_enviado_em)->not->toBeNull()
        ->and($agendamento->whatsapp_enviado_em)->not->toBeNull()
        ->and($agendamento->acompanhamento_id)->not->toBeNull()
        ->and($agendamento->agendado_para->format('Y-m-d H:i'))->toBe($data.' 14:30');

    expect(Acompanhamento::query()->where('motivo', Acompanhamento::MOTIVO_CONSULTA)->count())->toBe(1);

    Mail::assertSent(GestanteMensagemMail::class, function (GestanteMensagemMail $mail) use ($gestante) {
        return $mail->hasTo($gestante->email)
            && str_contains($mail->assunto, 'Consulta confirmada')
            && str_contains($mail->corpo, '14:30')
            && str_contains($mail->corpo, 'Dra. Helena Costa');
    });

    expect(GestanteEmail::query()->where('tipo', 'confirmacao_consulta')->count())->toBe(1);

    $whatsapp = GestanteWhatsapp::query()->where('tipo', 'saida')->first();
    expect($whatsapp)->not->toBeNull()
        ->and($whatsapp->categoria)->toBe(GestanteWhatsapp::CATEGORIA_CONFIRMACAO_CONSULTA)
        ->and($whatsapp->acompanhamento_id)->toBe($agendamento->acompanhamento_id)
        ->and($whatsapp->mensagem)->toContain('14:30')
        ->and($whatsapp->mensagem)->toContain('Dra. Helena Costa');

    $this->actingAs($secretaria)
        ->get(route('agenda.index'))
        ->assertOk()
        ->assertSee('Consultas')
        ->assertSee('Marina Alves')
        ->assertSee('14:30')
        ->assertDontSee('Confirmação WhatsApp')
        ->assertDontSee('Lembretes WhatsApp');

    $this->actingAs($secretaria)
        ->get(route('gestantes.show', $gestante))
        ->assertOk()
        ->assertSee('Próxima consulta')
        ->assertSee('Dra. Helena Costa')
        ->assertSee('14:30')
        ->assertSee('Agendada')
        ->assertDontSee('abrirModalAgendarConsulta', false)
        ->assertDontSee('Confirmação WhatsApp');
});

test('consulta e registrada mesmo sem e-mail da gestante', function () {
    Mail::fake();
    config(['mail.standby' => false, 'mail.default' => 'array']);

    $medico = medicoAgenda();
    $secretaria = secretariaAgenda();
    $gestante = gestanteAgenda(['email' => null, 'cpf' => '39053344705', 'telefone' => '21988880001']);

    $this->actingAs($secretaria)
        ->post(route('agenda.store'), [
            'gestante_id' => $gestante->id,
            'data' => now()->addDays(2)->toDateString(),
            'horario' => '09:00',
            'profissional_id' => $medico->id,
        ])
        ->assertRedirect(route('agenda.index'))
        ->assertSessionHas('success')
        ->assertSessionMissing('warning');

    expect(ConsultaAgendamento::query()->count())->toBe(1)
        ->and(ConsultaAgendamento::query()->first()->email_enviado_em)->toBeNull();

    Mail::assertNothingSent();
});

test('gestantes nao gerenciam consulta; medico nao agenda e secretaria agenda so em consultas', function () {
    $medico = medicoAgenda();
    $secretaria = secretariaAgenda();
    $gestante = gestanteAgenda();

    $this->actingAs($medico)
        ->post(route('agenda.store'), [
            'gestante_id' => $gestante->id,
            'data' => now()->addDay()->toDateString(),
            'horario' => '10:00',
            'profissional_id' => $medico->id,
        ])
        ->assertForbidden();

    $this->actingAs($medico)
        ->get(route('gestantes.index'))
        ->assertOk()
        ->assertDontSee('abrirModalIndicarAgendamento', false)
        ->assertDontSee('abrirModalAgendarConsulta', false)
        ->assertDontSee('Solicitar agendamento', false);

    $this->actingAs($secretaria)
        ->get(route('gestantes.index'))
        ->assertOk()
        ->assertDontSee('abrirModalAgendarConsulta', false)
        ->assertDontSee('aria-label="Agendar consulta de', false);
});
