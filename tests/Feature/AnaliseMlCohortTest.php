<?php

use App\Models\Gestante;
use App\Models\User;
use App\Services\DatasetGestanteImporter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function medicoCohort(): User
{
    return User::query()->create([
        'name' => 'Dra. Cohort',
        'crm' => 'CRM-COHORT-'.uniqid(),
        'password' => 'password',
    ]);
}

function fakeMlSemDataset(): void
{
    config(['services.python_api.url' => 'http://ml.test']);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/health') {
            return Http::response(['status' => 'ok']);
        }

        if ($path === '/predict') {
            return Http::response([
                'risk' => 'Alto',
                'probability' => 0.81,
                'classificacao_risco' => 'Alto',
                'probabilidade_risco' => 0.81,
                'class' => 1,
                'shap' => [['feature' => 'imc', 'impact' => 0.22]],
                'execution_time_ms' => 1.4,
                'fonte' => 'catboost',
                'modelo' => 'CatBoost',
                'experimento' => 'experimento_3_completo',
                'modo_inferencia' => 'modelo',
            ]);
        }

        return Http::response(['detail' => 'Not Found'], 404);
    });
}

function importarGestanteCohort(string $codigo = 'GEST-100'): void
{
    Queue::fake();
    app(DatasetGestanteImporter::class)->upsert([
        'id_gestante' => $codigo,
        'idade_materna' => 32,
        'etnia' => 'Branca',
        'imc_pre_gestacional' => 31.2,
        'idade_gestacional' => 22,
        'diabetes_gestacional' => 1,
        'hipertensao_cronica' => 0,
        'paridade' => 1,
    ]);
}

test('nao trata a analise como indisponivel quando /gestantes nao existe e o cadastro esta vazio', function () {
    fakeMlSemDataset();

    $this->actingAs(medicoCohort())
        ->get(route('cohort.gestantes.index'))
        ->assertOk()
        ->assertSee('Nenhuma gestante encontrada')
        ->assertDontSee('Serviço de análise indisponível');
});

test('lista o cohort local quando a API Python nao tem /gestantes', function () {
    fakeMlSemDataset();
    importarGestanteCohort();

    $this->actingAs(medicoCohort())
        ->get(route('cohort.gestantes.index'))
        ->assertOk()
        ->assertSee('GEST-100')
        ->assertSee('cadastro local')
        ->assertDontSee('Serviço de análise indisponível');
});

test('mostra gestante cadastrada mesmo quando a API Python tem outro dataset', function () {
    config(['services.python_api.url' => 'http://ml.test']);
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/health') {
            return Http::response(['status' => 'ok']);
        }
        if ($path === '/gestantes') {
            return Http::response([
                'total' => 1,
                'offset' => 0,
                'limit' => 25,
                'dataset' => 'treino.csv',
                'gestantes' => [['id_gestante' => 'SOMENTE-API']],
            ]);
        }

        return Http::response(['detail' => 'Not Found'], 404);
    });

    Gestante::query()->create([
        'nome' => 'Ana Cadastro',
        'data_nascimento' => '1992-03-10',
        'cpf' => '39053344705',
        'telefone' => '11988887777',
    ]);

    $this->actingAs(medicoCohort())
        ->get(route('cohort.gestantes.index'))
        ->assertOk()
        ->assertSee('Ana Cadastro')
        ->assertDontSee('SOMENTE-API');
});

test('coloca cadastro recente no topo e permite buscar por nome', function () {
    fakeMlSemDataset();
    importarGestanteCohort('GEST-001');

    Gestante::query()->create([
        'nome' => 'Beatriz Recente',
        'data_nascimento' => '1990-01-02',
        'cpf' => '52998224725',
        'telefone' => '21977776666',
    ]);

    $this->actingAs(medicoCohort())
        ->get(route('cohort.gestantes.index'))
        ->assertOk()
        ->assertSeeInOrder(['Beatriz Recente', 'GEST-001']);

    $this->actingAs(medicoCohort())
        ->get(route('cohort.gestantes.index', ['q' => 'Beatriz']))
        ->assertOk()
        ->assertSee('Beatriz Recente')
        ->assertDontSee('GEST-001');
});

test('exibe ficha local e roda predicao via POST /predict', function () {
    fakeMlSemDataset();
    importarGestanteCohort();
    $medico = medicoCohort();

    $this->actingAs($medico)
        ->get(route('cohort.gestantes.show', 'GEST-100'))
        ->assertOk()
        ->assertSee('GEST-100')
        ->assertSee('Predição de risco')
        ->assertDontSee('Falha na requisição à API Python');

    $this->actingAs($medico)
        ->from(route('cohort.gestantes.show', 'GEST-100'))
        ->post(route('cohort.gestantes.predict', 'GEST-100'))
        ->assertRedirect(route('cohort.gestantes.show', 'GEST-100'))
        ->assertSessionHas('success');

    $this->actingAs($medico)
        ->get(route('cohort.gestantes.show', 'GEST-100'))
        ->assertOk()
        ->assertSee('Inferência concluída')
        ->assertSee('81,0%')
        ->assertSee('CatBoost');
});
