<?php

use App\Jobs\ExecutarAnaliseGestante;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function medicoImportacao(): User
{
    return User::query()->create([
        'name' => 'Médica Teste',
        'crm' => 'CRM-IMPORT',
        'password' => 'password',
    ]);
}

function csvDataset(array $linhas): UploadedFile
{
    $header = 'id_gestante,idade_materna,imc_pre_gestacional,paridade,idade_gestacional,cardiopatia_congenita,diabetes_gestacional';
    $corpo = $header."\n".implode("\n", $linhas);

    return UploadedFile::fake()->createWithContent('dataset.csv', $corpo);
}

function xlsxDataset(array $linhas): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array_merge([
        ['id_gestante', 'idade_materna', 'imc_pre_gestacional', 'paridade', 'idade_gestacional', 'cardiopatia_congenita'],
    ], $linhas), null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'dataset.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('importa csv e cria gestante', function () {
    Queue::fake();

    $this->actingAs(medicoImportacao())
        ->postJson(route('consultas.import.store'), [
            'csv' => csvDataset(['GEST-100,30,28,1,22,0,0']),
        ])
        ->assertOk()
        ->assertJsonPath('criadas', 1)
        ->assertJsonPath('atualizadas', 0);

    expect(Gestante::query()->where('gestante_id', 'GEST-100')->count())->toBe(1)
        ->and(Consulta::count())->toBe(1);

    Queue::assertPushed(ExecutarAnaliseGestante::class);
});

test('reimportar as mesmas gestantes atualiza e nao duplica', function () {
    Queue::fake();
    $this->actingAs(medicoImportacao());

    $this->postJson(route('consultas.import.store'), [
        'csv' => csvDataset(['GEST-100,30,28,1,22,0,0']),
    ])->assertOk();

    $consulta = Consulta::query()->first();
    $consulta->update(['pressao_sistolica' => 130]);

    $this->postJson(route('consultas.import.store'), [
        'csv' => csvDataset(['GEST-100,31,29,1,24,1,1']),
    ])
        ->assertOk()
        ->assertJsonPath('criadas', 0)
        ->assertJsonPath('atualizadas', 1);

    expect(Gestante::count())->toBe(1)
        ->and(Consulta::count())->toBe(1);

    $consulta->refresh();
    expect($consulta->pressao_sistolica)->toBe(130)
        ->and($consulta->idade_materna)->toBe(31)
        ->and($consulta->chd_confirmada)->toBeTrue()
        ->and($consulta->diabetes_gestacional)->toBeTrue();
});

test('importa xlsx no mesmo fluxo', function () {
    Queue::fake();

    $this->actingAs(medicoImportacao())
        ->postJson(route('consultas.import.store'), [
            'csv' => xlsxDataset([['GEST-200', 28, 24.5, 0, 18, 0]]),
        ])
        ->assertOk()
        ->assertJsonPath('criadas', 1);

    expect(Gestante::query()->where('gestante_id', 'GEST-200')->exists())->toBeTrue();
});

test('rejeita planilha sem id_gestante', function () {
    $csv = UploadedFile::fake()->createWithContent(
        'legado.csv',
        "gestante_id,data_consulta\n1,2026-01-01\n"
    );

    $this->actingAs(medicoImportacao())
        ->postJson(route('consultas.import.store'), ['csv' => $csv])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'A planilha precisa da coluna id_gestante.']);
});
