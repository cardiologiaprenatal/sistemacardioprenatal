<?php

use App\Models\Acompanhamento;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Models\GestanteDocumento;
use App\Services\WhatsAppMenuFlowService;
use App\Support\WhatsAppFluxoEstado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

function gestanteMenu(array $attrs = []): Gestante
{
    return Gestante::query()->create(array_merge([
        'nome' => 'Caroline Silva',
        'telefone' => '21988887777',
        'whatsapp_fluxo_estado' => WhatsAppFluxoEstado::MENU_INICIAL,
        'atendimento_personalizado_aceito_em' => now(),
    ], $attrs));
}

function consultaMenu(Gestante $gestante, array $attrs = []): Consulta
{
    return Consulta::query()->create(array_merge([
        'gestante_id' => $gestante->id,
        'consulta_numero' => 1,
        'data_consulta' => '2026-08-20',
        'idade_gestacional' => 22,
        'pressao_sistolica' => 110,
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
    ], $attrs));
}

function fluxo(): WhatsAppMenuFlowService
{
    return app(WhatsAppMenuFlowService::class);
}

test('1. gestante seleciona 1 e recebe o submenu de atendente', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, '1');

    expect($r['estado'])->toBe(WhatsAppFluxoEstado::ATENDENTE)
        ->and($r['pular_ia'])->toBeTrue()
        ->and($r['resposta'])->toContain('Vou ajudar você a falar com um atendente')
        ->and($r['resposta'])->toContain('1. Dúvida sobre meu pré-natal')
        ->and($r['resposta'])->not->toContain('Em instantes');
});

test('2. gestante seleciona 2 e recebe o submenu de exames e consultas', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, '2');

    expect($r['estado'])->toBe(WhatsAppFluxoEstado::EXAMES_CONSULTAS)
        ->and($r['resposta'])->toContain('Claro, Caroline.')
        ->and($r['resposta'])->toContain('1. Meus exames')
        ->and($r['resposta'])->toContain('2. Minhas consultas')
        ->and($r['resposta'])->toContain('3. Próximos compromissos');
});

test('3. gestante seleciona 2 → 1 sem exames cadastrados', function () {
    $g = gestanteMenu();
    fluxo()->handle($g, '2');
    $r = fluxo()->handle($g->fresh(), '1');

    expect($r['resposta'])->toContain('não encontrei exames cadastrados')
        ->and($r['resposta'])->toContain('Isso não significa que você não tenha realizado um exame')
        ->and($r['resposta'])->not->toContain('você não realizou')
        ->and($r['resposta'])->not->toMatch('/\d{2}\/\d{2}\/\d{4}/');
});

test('4. gestante seleciona 2 → 2 sem consultas cadastradas', function () {
    $g = gestanteMenu();
    fluxo()->handle($g, '2');
    $r = fluxo()->handle($g->fresh(), '2');

    expect($r['resposta'])->toContain('Não encontrei consultas cadastradas no sistema')
        ->and($r['resposta'])->not->toContain('você não possui consulta');
});

test('5. gestante seleciona 2 → 3 sem compromissos futuros', function () {
    $g = gestanteMenu();
    consultaMenu($g, ['data_consulta' => '2026-01-10']);
    fluxo()->handle($g, '2');
    $r = fluxo()->handle($g->fresh(), '3');

    expect($r['resposta'])->toContain('Não encontrei próximos compromissos cadastrados no sistema')
        ->and($r['resposta'])->not->toContain('10/01/2026');
});

test('6. gestante seleciona 3 e depois envia mensagem para a equipe médica', function () {
    $g = gestanteMenu();
    $r1 = fluxo()->handle($g, '3');
    expect($r1['estado'])->toBe(WhatsAppFluxoEstado::AGUARDANDO_MENSAGEM_MEDICO)
        ->and($r1['resposta'])->toContain('Escreva sua dúvida');

    $r2 = fluxo()->handle($g->fresh(), 'Gostaria de informar que estou com falta de ar.');
    expect($r2['estado'])->toBe(WhatsAppFluxoEstado::CONVERSA_SINTOMA)
        ->and($r2['pular_ia'])->toBeTrue()
        ->and($r2['resposta'])->toContain('Sinto muito')
        ->and($r2['resposta'])->toContain('falta de ar')
        ->and($r2['resposta'])->toContain('Há quanto tempo')
        ->and($r2['resposta'])->not->toContain('Não posso avaliar sintomas')
        ->and($r2['resposta'])->not->toContain('o médico recebeu')
        ->and($r2['resposta'])->not->toContain('diagnóstico');

    $acomp = Acompanhamento::query()->where('gestante_id', $g->id)->first();
    expect($acomp)->not->toBeNull()
        ->and($acomp->motivo)->toBe(Acompanhamento::MOTIVO_MANUAL)
        ->and($acomp->titulo)->toBe('WhatsApp — Falar com o médico')
        ->and($acomp->observacao)->toContain('falta de ar');
});

test('7. opção inválida no menu inicial', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, '9');

    expect($r['resposta'])->toBe('Não consegui identificar essa opção. Por favor, escolha uma das opções disponíveis.')
        ->and($r['resposta'])->not->toContain('diagnóstico')
        ->and($r['resposta'])->not->toContain('medicamento');
});

test('8 e 10. lista vários exames reais e omite campos ausentes', function () {
    $g = gestanteMenu();
    GestanteDocumento::query()->create([
        'gestante_id' => $g->id,
        'titulo' => 'Ultrassonografia obstétrica',
        'tipo' => 'exame',
        'fonte' => 'manual',
        'data_documento' => '2026-08-01',
        'conteudo_texto' => 'Documento cadastrado.',
        'ativo' => true,
    ]);
    GestanteDocumento::query()->create([
        'gestante_id' => $g->id,
        'titulo' => 'Hemograma',
        'tipo' => 'exame',
        'fonte' => 'manual',
        'data_documento' => null,
        'conteudo_texto' => 'Documento cadastrado.',
        'ativo' => true,
    ]);

    fluxo()->handle($g, '2');
    $r = fluxo()->handle($g->fresh(), '1');

    expect($r['resposta'])->toContain('Estes são os exames cadastrados no seu pré-natal')
        ->and($r['resposta'])->toContain('Ultrassonografia obstétrica')
        ->and($r['resposta'])->toContain('01/08/2026')
        ->and($r['resposta'])->toContain('Hemograma')
        ->and($r['resposta'])->not->toContain('Status:')
        ->and($r['resposta'])->not->toContain('Local:')
        ->and($r['resposta'])->not->toContain('alterado')
        ->and($r['resposta'])->not->toContain('normal');
});

test('9 e 11. lista várias consultas reais sem inventar horário, local ou profissional', function () {
    $g = gestanteMenu();
    consultaMenu($g, ['consulta_numero' => 1, 'data_consulta' => '2026-07-10']);
    consultaMenu($g, ['consulta_numero' => 2, 'data_consulta' => '2026-08-05', 'idade_gestacional' => 28]);

    fluxo()->handle($g, '2');
    $r = fluxo()->handle($g->fresh(), '2');

    expect($r['resposta'])->toContain('Estas são as consultas cadastradas no seu pré-natal')
        ->and($r['resposta'])->toContain('Consulta 1')
        ->and($r['resposta'])->toContain('10/07/2026')
        ->and($r['resposta'])->toContain('Consulta 2')
        ->and($r['resposta'])->toContain('05/08/2026')
        ->and($r['resposta'])->not->toContain('Horário:')
        ->and($r['resposta'])->not->toContain('Local:')
        ->and($r['resposta'])->not->toContain('Profissional:');
});

test('12. próximos compromissos futuros em ordem cronológica', function () {
    $g = gestanteMenu();
    $d1 = now()->addDays(2)->startOfDay();
    $d2 = now()->addDays(4)->startOfDay();
    $d3 = now()->addDays(6)->startOfDay();

    consultaMenu($g, ['consulta_numero' => 2, 'data_consulta' => $d3->toDateString()]);
    consultaMenu($g, ['consulta_numero' => 1, 'data_consulta' => $d1->toDateString()]);
    GestanteDocumento::query()->create([
        'gestante_id' => $g->id,
        'titulo' => 'Ultrassonografia',
        'tipo' => 'exame',
        'data_documento' => $d2->toDateString(),
        'conteudo_texto' => 'Documento cadastrado.',
        'ativo' => true,
    ]);

    fluxo()->handle($g, '2');
    $r = fluxo()->handle($g->fresh(), '3');

    $fmt1 = $d1->format('d/m/Y');
    $fmt2 = $d2->format('d/m/Y');
    $fmt3 = $d3->format('d/m/Y');

    expect($r['resposta'])->toContain('Seus próximos compromissos cadastrados são')
        ->and($r['resposta'])->toContain($fmt1)
        ->and($r['resposta'])->toContain('Consulta 1')
        ->and($r['resposta'])->toContain($fmt2)
        ->and($r['resposta'])->toContain('Ultrassonografia')
        ->and($r['resposta'])->toContain($fmt3)
        ->and($r['resposta'])->toContain('Consulta 2')
        ->and($r['resposta'])->toContain('baseadas nos registros disponíveis no sistema');

    $pos1 = strpos($r['resposta'], $fmt1);
    $pos2 = strpos($r['resposta'], $fmt2);
    $pos3 = strpos($r['resposta'], $fmt3);
    expect($pos1)->toBeLessThan($pos2)
        ->and($pos2)->toBeLessThan($pos3);
});

test('13. dúvida médica espontânea não gera diagnóstico', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, 'Estou sentindo dor.');

    expect($r['estado'])->toBe(WhatsAppFluxoEstado::CONVERSA_SINTOMA)
        ->and($r['resposta'])->toContain('Sinto muito')
        ->and($r['resposta'])->toContain('Caroline')
        ->and($r['resposta'])->toContain('Há quanto tempo')
        ->and($r['resposta'])->not->toContain('Não posso avaliar sintomas')
        ->and($r['resposta'])->not->toContain('é normal')
        ->and($r['resposta'])->not->toContain('você está com uma doença');

    expect(Acompanhamento::query()->where('gestante_id', $g->id)->count())->toBe(1);
});

test('14. pedido de interpretação de exame não inventa resultado', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, 'Meu exame deu alterado. O que significa?');

    expect($r['resposta'])->toContain('Não posso avaliar sintomas, interpretar exames')
        ->and($r['resposta'])->not->toContain('significa que')
        ->and($r['resposta'])->not->toContain('cardiopatia');
});

test('15. pedido de diagnóstico é recusado', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, 'Pode me dar um diagnóstico?');

    expect($r['resposta'])->toContain('Não posso avaliar sintomas')
        ->and($r['resposta'])->not->toContain('você tem')
        ->and($r['resposta'])->not->toContain('o diagnóstico é');
});

test('16. solicitação de medicamento é recusada', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, 'Qual medicamento posso tomar para dor?');

    expect($r['resposta'])->toContain('indicar medicamentos')
        ->and($r['resposta'])->not->toContain('paracetamol')
        ->and($r['resposta'])->not->toContain('dipirona')
        ->and($r['resposta'])->not->toContain('tome');
});

test('17. gestante retorna ao menu inicial com 0', function () {
    $g = gestanteMenu();
    fluxo()->handle($g, '2');
    $r = fluxo()->handle($g->fresh(), '0');

    expect($r['estado'])->toBe(WhatsAppFluxoEstado::MENU_INICIAL)
        ->and($r['resposta'])->toContain('seja bem-vinda ao Cardioprenatal')
        ->and($r['resposta'])->toContain('1. Falar com atendente')
        ->and($r['resposta'])->toContain('Caroline');
});

test('nome nunca é inventado quando o cadastro não tem nome', function () {
    $g = gestanteMenu(['nome' => null]);
    $r = fluxo()->handle($g, '2');

    expect($r['resposta'])->toStartWith('Claro. O que você deseja consultar?')
        ->and($r['resposta'])->not->toContain('gestante');
});

test('opção 1 registra solicitação sem afirmar que um atendente foi notificado', function () {
    $g = gestanteMenu();
    fluxo()->handle($g, '1');
    $r = fluxo()->handle($g->fresh(), 'Tenho dúvida sobre o retorno.');

    expect($r['resposta'])->toBe('Obrigada! Registrei sua solicitação e ela será encaminhada para a equipe responsável.')
        ->and($r['resposta'])->not->toContain('notificado')
        ->and($r['resposta'])->not->toContain('Em instantes');

    $acomp = Acompanhamento::query()->where('gestante_id', $g->id)->first();
    expect($acomp)->not->toBeNull()
        ->and($acomp->observacao)->toContain('dúvida sobre o retorno');
});

test('relato de dor entra no fluxo de sintoma e o detalhe seguinte nao e opcao invalida', function () {
    $g = gestanteMenu();
    consultaMenu($g, ['idade_gestacional' => 24]);
    $r1 = fluxo()->handle($g, 'Estou com dor de cabeça');

    expect($r1['estado'])->toBe(WhatsAppFluxoEstado::CONVERSA_SINTOMA)
        ->and($r1['pular_ia'])->toBeTrue()
        ->and($r1['resposta'])->toContain('dor de cabeça')
        ->and($r1['resposta'])->toContain('Caroline')
        ->and($r1['resposta'])->toContain('24 semanas')
        ->and($r1['resposta'])->toContain('Há quanto tempo')
        ->and($r1['resposta'])->not->toContain('Não posso avaliar sintomas')
        ->and($r1['resposta'])->not->toContain('Não consegui identificar essa opção');

    $r2 = fluxo()->handle($g->fresh(), 'fazem dois dias dores intensas');

    expect($r2['resposta'])->not->toContain('Não consegui identificar essa opção')
        ->and($r2['pular_ia'])->toBeTrue()
        ->and($r2['resposta'])->toContain('forte')
        ->and($r2['resposta'])->toContain('dois dias')
        ->and($r2['resposta'])->toContain('contínua ou vai e volta')
        ->and($r2['estado'])->toBe(WhatsAppFluxoEstado::CONVERSA_SINTOMA);

    expect(Acompanhamento::query()->where('gestante_id', $g->id)->count())->toBe(1);
    $acomp = Acompanhamento::query()->where('gestante_id', $g->id)->first();
    expect($acomp->observacao)->toContain('Estou com dor de cabeça')
        ->and($acomp->observacao)->toContain('fazem dois dias dores intensas')
        ->and($acomp->titulo)->toBe('WhatsApp — Falar com o médico');

    $r3 = fluxo()->handle($g->fresh(), '0');
    expect($r3['estado'])->toBe(WhatsAppFluxoEstado::MENU_INICIAL)
        ->and($r3['resposta'])->toContain('seja bem-vinda ao Cardioprenatal');
});

test('pedido livre de exame consulta os dados cadastrados sem interpretar', function () {
    $g = gestanteMenu();
    GestanteDocumento::query()->create([
        'gestante_id' => $g->id,
        'titulo' => 'Ultrassonografia obstétrica',
        'tipo' => 'exame',
        'fonte' => 'manual',
        'data_documento' => '2026-08-01',
        'conteudo_texto' => 'Documento cadastrado.',
        'ativo' => true,
    ]);

    $r = fluxo()->handle($g, 'Quero saber o resultado do meu exame');

    expect($r['intencao'])->toBe('exames_consultas')
        ->and($r['resposta'])->toContain('Estes são os exames cadastrados no seu pré-natal')
        ->and($r['resposta'])->toContain('Ultrassonografia obstétrica')
        ->and($r['resposta'])->not->toContain('Não consegui identificar essa opção')
        ->and($r['resposta'])->not->toContain('cardiopatia')
        ->and($r['resposta'])->not->toContain('alterado');
});

test('mensagem livre fora do menu nao e tratada como opcao invalida', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, 'Oi, tudo bem? Preciso de uma informação do meu pré-natal.');

    expect($r['pular_ia'])->toBeFalse()
        ->and($r['respondido'])->toBeFalse()
        ->and($r['intencao'])->toBe('conversa_livre')
        ->and($r['resposta'])->toBe('')
        ->and($r['resposta'])->not->toContain('Não consegui identificar essa opção');
});

test('oi com muitas dores recebe acolhimento imediato do laravel', function () {
    $g = gestanteMenu();
    $r = fluxo()->handle($g, 'oii, estou com muitas dores, me ajuda');

    expect($r['estado'])->toBe(WhatsAppFluxoEstado::CONVERSA_SINTOMA)
        ->and($r['pular_ia'])->toBeTrue()
        ->and($r['resposta'])->toContain('Sinto muito')
        ->and($r['resposta'])->toContain('Caroline')
        ->and($r['resposta'])->toContain('Há quanto tempo')
        ->and($r['resposta'])->not->toBe('');
});
