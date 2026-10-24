<?php

use App\Models\Acompanhamento;
use App\Models\Gestante;
use App\Models\GestanteWhatsapp;
use App\Models\User;
use App\Services\GestanteWhatsappMessageRecorder;
use App\Services\WhatsAppLembreteService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function medicoHistoricoWhatsapp(): User
{
    return User::query()->create([
        'name' => 'Médica Histórico',
        'crm' => 'CRM-HIST-WA',
        'password' => 'password',
    ]);
}

function gestanteHistoricoWhatsapp(array $attrs = []): Gestante
{
    return Gestante::query()->create(array_merge([
        'nome' => 'Ana Clara',
        'telefone' => '21988887777',
        'whatsapp_ia_pausada_ate' => now()->addHours(4),
    ], $attrs));
}

function acompanhamentoHistorico(Gestante $gestante, array $attrs = []): Acompanhamento
{
    return Acompanhamento::query()->create(array_merge([
        'gestante_id' => $gestante->id,
        'status' => Acompanhamento::STATUS_PENDENTE,
        'motivo' => Acompanhamento::MOTIVO_RETORNO,
        'titulo' => 'Retorno clínico',
        'agendado_para' => now()->addDay()->setTime(14, 30),
        'whatsapp_lembrete_enviado_em' => now(),
    ], $attrs));
}

test('historico whatsapp lista conversas e nao mistura agenda', function () {
    $gestante = gestanteHistoricoWhatsapp();
    $retorno = acompanhamentoHistorico($gestante);

    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'acompanhamento_id' => $retorno->id,
        'mensagem' => 'Obrigada, confirmo o retorno.',
        'tipo' => 'entrada',
        'categoria' => GestanteWhatsapp::CATEGORIA_CONVERSA,
    ]);

    $this->actingAs(medicoHistoricoWhatsapp())
        ->get(route('historico-whatsapp.index'))
        ->assertOk()
        ->assertSee('Conversas')
        ->assertSee('Ana Clara')
        ->assertSee('Obrigada, confirmo o retorno.')
        ->assertDontSee('Consultas e retornos')
        ->assertDontSee('Tipo de agendamento');
});

test('api da conversa devolve categoria e filtra por acompanhamento', function () {
    $gestante = gestanteHistoricoWhatsapp();
    $retorno = acompanhamentoHistorico($gestante);
    $consulta = acompanhamentoHistorico($gestante, [
        'motivo' => Acompanhamento::MOTIVO_MANUAL,
        'titulo' => 'Consulta',
    ]);

    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'acompanhamento_id' => $retorno->id,
        'mensagem' => 'Lembrete de retorno',
        'tipo' => 'saida',
        'categoria' => GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO,
    ]);
    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'acompanhamento_id' => $consulta->id,
        'mensagem' => 'Lembrete de consulta',
        'tipo' => 'saida',
        'categoria' => GestanteWhatsapp::CATEGORIA_LEMBRETE_CONSULTA,
    ]);

    $this->actingAs(medicoHistoricoWhatsapp())
        ->getJson('/api/gestante-whatsapp/'.$gestante->id.'?acompanhamento_id='.$retorno->id)
        ->assertOk()
        ->assertJsonCount(1, 'mensagens')
        ->assertJsonPath('mensagens.0.categoria', GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO)
        ->assertJsonPath('mensagens.0.categoria_label', 'Lembrete de retorno')
        ->assertJsonPath('mensagens.0.remetente', 'sistema')
        ->assertJsonPath('mensagens.0.remetente_label', 'Sistema')
        ->assertJsonPath('mensagens.0.acompanhamento_id', $retorno->id);
});

test('resposta da gestante fica vinculada ao lembrete recente', function () {
    $gestante = gestanteHistoricoWhatsapp();
    $retorno = acompanhamentoHistorico($gestante);

    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'acompanhamento_id' => $retorno->id,
        'mensagem' => 'Seu retorno é hoje.',
        'tipo' => 'saida',
        'categoria' => GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO,
    ]);

    $row = app(GestanteWhatsappMessageRecorder::class)->record(
        $gestante->telefone,
        'Confirmo presença',
        'entrada'
    );

    expect($row->acompanhamento_id)->toBe($retorno->id)
        ->and($row->categoria)->toBe(GestanteWhatsapp::CATEGORIA_CONVERSA);
});

test('lembrete de consulta e retorno enviam categoria correta', function () {
    $gestante = gestanteHistoricoWhatsapp();
    $consulta = acompanhamentoHistorico($gestante, [
        'motivo' => Acompanhamento::MOTIVO_ECO_RECOMENDADA,
        'titulo' => 'Consulta',
        'whatsapp_lembrete_enviado_em' => null,
        'whatsapp_lembrete_para' => null,
        'agendado_para' => now()->setTime(10, 0),
    ]);
    $retorno = acompanhamentoHistorico($gestante, [
        'motivo' => Acompanhamento::MOTIVO_RETORNO,
        'titulo' => 'Retorno',
        'whatsapp_lembrete_enviado_em' => null,
        'whatsapp_lembrete_para' => null,
        'agendado_para' => now()->setTime(15, 0),
    ]);

    $this->mock(WhatsAppService::class, function ($mock) use ($consulta, $retorno) {
        $mock->shouldReceive('sendGestanteTextMessage')
            ->once()
            ->withArgs(function ($gestante, $texto, $categoria, $id) use ($consulta) {
                return $id === $consulta->id
                    && $categoria === GestanteWhatsapp::CATEGORIA_LEMBRETE_CONSULTA
                    && is_string($texto) && $texto !== '';
            })
            ->andReturn(true);
        $mock->shouldReceive('sendGestanteTextMessage')
            ->once()
            ->withArgs(function ($gestante, $texto, $categoria, $id) use ($retorno) {
                return $id === $retorno->id
                    && $categoria === GestanteWhatsapp::CATEGORIA_LEMBRETE_RETORNO;
            })
            ->andReturn(true);
    });

    $service = app(WhatsAppLembreteService::class);

    expect($service->enviarLembrete($consulta->fresh()))->toBeTrue()
        ->and($service->enviarLembrete($retorno->fresh()))->toBeTrue();
});

test('historico whatsapp nao filtra mais por tipo de agendamento', function () {
    $gestante = gestanteHistoricoWhatsapp();
    acompanhamentoHistorico($gestante, [
        'motivo' => Acompanhamento::MOTIVO_RETORNO,
        'titulo' => 'Retorno exclusivo',
    ]);

    GestanteWhatsapp::query()->create([
        'gestante_id' => $gestante->id,
        'mensagem' => 'Conversa da Ana',
        'tipo' => 'entrada',
        'categoria' => GestanteWhatsapp::CATEGORIA_CONVERSA,
    ]);

    $this->actingAs(medicoHistoricoWhatsapp())
        ->get(route('historico-whatsapp.index', ['tipo_agenda' => 'retorno']))
        ->assertOk()
        ->assertSee('Conversas')
        ->assertDontSee('Retorno exclusivo')
        ->assertSee('Conversa da Ana');
});
