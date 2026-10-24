<?php

use App\Models\User;
use App\Services\DatasetGestanteImporter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function catalogoExperimentosFake(): array
{
    return [
        'disponivel' => true,
        'experimento' => 'experimento_3_completo',
        'fonte' => 'data/metricas_modelos.csv',
        'criterio_selecao' => 'melhor_f1_cv',
        'total_modelos' => 12,
        'destaques' => [
            'melhor_f1_cv' => [
                'modelo' => 'CatBoost',
                'nome_exibicao' => 'CatBoost',
                'valor' => 0.3595,
                'balanceamento' => 'class_weight',
            ],
        ],
        'modelos' => [
            [
                'modelo' => 'Logistic Regression',
                'nome_exibicao' => 'Regressão Logística',
                'balanceamento' => 'random_oversampling',
                'status' => null,
                'treino_cv' => ['f1' => 0.3368, 'acuracia' => 0.798],
                'teste' => ['f1' => 0.3395, 'roc_auc' => 0.8411, 'sensibilidade' => 0.7432, 'especificidade' => 0.7894, 'acuracia' => 0.786],
            ],
            [
                'modelo' => 'CatBoost',
                'nome_exibicao' => 'CatBoost',
                'balanceamento' => 'class_weight',
                'status' => 'publicado',
                'treino_cv' => ['f1' => 0.3595, 'acuracia' => 0.8412],
                'teste' => ['f1' => 0.3488, 'roc_auc' => 0.8041, 'sensibilidade' => 0.6081, 'especificidade' => 0.8499, 'acuracia' => 0.832],
            ],
            [
                'modelo' => 'SVM',
                'nome_exibicao' => 'SVM',
                'balanceamento' => 'random_oversampling',
                'status' => null,
                'treino_cv' => ['f1' => 0.3379],
                'teste' => ['f1' => 0.3563, 'roc_auc' => 0.8522, 'sensibilidade' => 0.7703, 'especificidade' => 0.7959, 'acuracia' => 0.794],
            ],
        ],
        'legendas' => [
            'treino_cv' => 'Média Stratified 10-Fold Cross-Validation',
        ],
        'producao' => [
            'modelo' => 'CatBoost',
            'nome_exibicao' => 'CatBoost',
            'balanceamento' => 'class_weight',
            'carregado' => false,
            'modo_inferencia' => 'heuristica',
            'mensagem' => 'Modelo publicado sem artefato .pkl; a inferência usa regras clínicas até o arquivo ser carregado. O catálogo de experimentos permanece intacto.',
        ],
        'mensagem' => null,
    ];
}

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    config(['services.python_api.url' => 'http://ml.test', 'services.ccf_api.url' => 'http://ml.test']);
    $this->comCatalogoMl = false;

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/health') {
            return Http::response(['status' => 'ok']);
        }

        if ($path === '/model/info') {
            return Http::response([
                'name' => 'CardioPrenatal-ML',
                'version' => '1.0.0',
                'loaded' => false,
                'model_path' => 'models/model.pkl',
                'features' => ['idade', 'diabetes', 'hipertensao', 'imc'],
                'framework' => 'heuristic',
            ]);
        }

        if ($path === '/metrics') {
            return Http::response([
                'model_loaded' => false,
                'total_predictions' => 3,
                'avg_execution_time_ms' => 1.2,
                'last_prediction_at' => null,
            ]);
        }

        if ($path === '/modelos' || $path === '/modelos/metricas') {
            if ($this->comCatalogoMl) {
                return Http::response(catalogoExperimentosFake());
            }

            return Http::response(['detail' => 'Not Found'], 404);
        }

        return Http::response(['detail' => 'Not Found'], 404);
    });
});

function medicoDashboard(): User
{
    return User::query()->create([
        'name' => 'Dra. Dashboard',
        'crm' => 'CRM-DASH-'.uniqid(),
        'password' => 'password',
    ]);
}

test('dashboard lista o catalogo de experimentos com metricas reais', function () {
    $this->comCatalogoMl = true;

    $this->actingAs(medicoDashboard())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Catálogo de experimentos')
        ->assertSee('experimento_3_completo')
        ->assertSee('CatBoost')
        ->assertSee('Regressão Logística')
        ->assertSee('SVM')
        ->assertSee('class_weight')
        ->assertSee(number_format(0.3595 * 100, 1, ',', '.').'%')
        ->assertSee('Modelo em produção')
        ->assertDontSee('Esta API publica o modelo em produção, não o catálogo de experimentos de treino.')
        ->assertDontSee('Endpoint /modelos indisponível nesta API.');
});

test('dashboard nao substitui o catalogo pela heuristica quando GET /modelos nao existe', function () {
    $this->actingAs(medicoDashboard())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Catálogo de experimentos indisponível nesta API.')
        ->assertSee('Modelo em produção')
        ->assertSee('Heurística')
        ->assertDontSee('experimento_3_completo')
        ->assertDontSee('Regressão Logística')
        ->assertDontSee('Esta API publica o modelo em produção, não o catálogo de experimentos de treino.');
});

test('dashboard desenha graficos do cohort a partir do cadastro local', function () {
    Queue::fake();
    app(DatasetGestanteImporter::class)->upsert([
        'id_gestante' => 'GEST-200',
        'idade_materna' => 34,
        'etnia' => 'Branca',
        'imc_pre_gestacional' => 31,
        'idade_gestacional' => 22,
        'diabetes_gestacional' => 1,
        'cardiopatia_congenita' => 1,
    ]);

    $this->actingAs(medicoDashboard())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Cadastro local')
        ->assertSee('Prevalência de CCF')
        ->assertSee('chart-cohort-prevalencia')
        ->assertDontSee('Gráficos descritivos indisponíveis.');
});
