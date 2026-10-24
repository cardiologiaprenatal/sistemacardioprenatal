<?php

use App\Http\Controllers\AcompanhamentoController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AnaliseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CohortGestanteController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GestanteController;
use App\Http\Controllers\GestanteWhatsappController;
use App\Http\Controllers\GraficosController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappCanalController;
use App\Http\Controllers\WhatsappPasswordResetController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->to(Auth::user()->homeRoute());
    }

    return view('auth.login');
});

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1,login')
    ->name('auth');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('esqueci-senha', [WhatsappPasswordResetController::class, 'create'])->name('password.request');
    Route::post('esqueci-senha/whatsapp', [WhatsappPasswordResetController::class, 'send'])
        ->middleware('throttle:5,1,wa-reset')
        ->name('password.whatsapp.send');
    Route::post('esqueci-senha/whatsapp/codigo', [WhatsappPasswordResetController::class, 'verify'])
        ->middleware('throttle:12,1,wa-code')
        ->name('password.whatsapp.verify');
    Route::post('esqueci-senha/whatsapp/senha', [WhatsappPasswordResetController::class, 'update'])
        ->middleware('throttle:6,1,wa-password')
        ->name('password.whatsapp.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/assets/gestrisk/chart.js', [GraficosController::class, 'chartJs'])
        ->name('gestrisk.chartjs');

    Route::middleware('can:'.User::ABILITY_DASHBOARD)->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/analisar', [AnaliseController::class, 'iniciarAnalise'])->name('dashboard.analisar');
        Route::get('/dashboard/verificar-analise', [AnaliseController::class, 'verificarStatus'])->name('dashboard.verificarAnalise');
        Route::get('/dashboard/resultado-analise', [AnaliseController::class, 'obterResultado'])->name('dashboard.resultadoAnalise');
        Route::get('/dashboard/graficos/{slug}', [GraficosController::class, 'descritivo'])
            ->where('slug', '[a-z0-9_]+')
            ->name('dashboard.graficos.descritivo');
    });

    Route::resource('gestantes', GestanteController::class)
        ->except(['index', 'show'])
        ->middleware('can:'.User::ABILITY_GESTANTES_MANAGE);

    Route::resource('gestantes', GestanteController::class)
        ->only(['index', 'show'])
        ->middleware('can:'.User::ABILITY_GESTANTES_VIEW);

    Route::post('/gestantes/{gestante}/whatsapp', [GestanteController::class, 'enviarWhatsapp'])
        ->middleware('can:'.User::ABILITY_WHATSAPP)
        ->name('gestantes.whatsapp');
    Route::post('/gestantes/{gestante}/atendimento-personalizado', [GestanteController::class, 'aceitarAtendimentoPersonalizado'])
        ->middleware('can:'.User::ABILITY_CONSULTAS_MANAGE)
        ->name('gestantes.atendimento-personalizado');
    Route::post('/gestantes/{gestante}/encaminhar-ecocardiograma', [GestanteController::class, 'encaminharEcocardiograma'])
        ->middleware('can:'.User::ABILITY_CONSULTAS_MANAGE)
        ->name('gestantes.encaminhar-ecocardiograma');
    Route::post('/gestantes/{gestante}/email', [GestanteController::class, 'enviarEmail'])
        ->middleware('can:'.User::ABILITY_WHATSAPP)
        ->name('gestantes.email');

    Route::middleware('can:'.User::ABILITY_ACOMPANHAMENTOS)->group(function () {
        Route::get('/acompanhamentos', [AcompanhamentoController::class, 'index'])->name('acompanhamentos.index');
        Route::post('/acompanhamentos/sync', [AcompanhamentoController::class, 'sync'])->name('acompanhamentos.sync');
        Route::patch('/acompanhamentos/{acompanhamento}', [AcompanhamentoController::class, 'update'])->name('acompanhamentos.update');
        Route::patch('/alertas/{alerta}/resolver', [AcompanhamentoController::class, 'resolverAlerta'])->name('alertas.resolver');
    });

    Route::middleware('can:'.User::ABILITY_ML)->group(function () {
        Route::get('/cohort/gestantes', [CohortGestanteController::class, 'index'])->name('cohort.gestantes.index');
        Route::get('/cohort/gestantes/{id}', [CohortGestanteController::class, 'show'])
            ->where('id', '[A-Za-z0-9._-]+')
            ->name('cohort.gestantes.show');
        Route::post('/cohort/gestantes/{id}/predict', [CohortGestanteController::class, 'predict'])
            ->where('id', '[A-Za-z0-9._-]+')
            ->name('cohort.gestantes.predict');
    });

    Route::middleware('can:'.User::ABILITY_WHATSAPP)->group(function () {
        Route::get('/whatsapp', [WhatsappCanalController::class, 'index'])
            ->name('whatsapp.index');
        Route::get('/historico-atendimento-whatsapp', [GestanteWhatsappController::class, 'historicoPage'])
            ->name('historico-whatsapp.index');

        Route::prefix('api')->group(function () {
            Route::get('/gestante-whatsapp', [GestanteWhatsappController::class, 'index']);
            Route::get('/gestante-whatsapp/{gestante}', [GestanteWhatsappController::class, 'show']);
        });
    });

    Route::middleware('can:'.User::ABILITY_CONSULTAS_AGENDA)->group(function () {
        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    });
    Route::post('/agenda/indicar', [AgendaController::class, 'indicar'])
        ->middleware('can:'.User::ABILITY_AGENDAMENTOS_INDICAR)
        ->name('agenda.indicar');
    Route::middleware('can:'.User::ABILITY_CONSULTAS_AGENDAR)->group(function () {
        Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
        Route::put('/agenda/{agendamento}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::post('/agenda/{agendamento}/cancelar', [AgendaController::class, 'cancel'])->name('agenda.cancel');
    });

    Route::middleware('can:'.User::ABILITY_CONSULTAS_IMPORT)->group(function () {
        Route::get('/consultas/import', [ConsultaController::class, 'index'])->name('consultas.import');
        Route::post('/consultas/import', [ConsultaController::class, 'import'])->name('consultas.import.store');
    });

    Route::middleware('can:'.User::ABILITY_CONSULTAS_MANAGE)->group(function () {
        Route::get('/consultas/create/{id}', [ConsultaController::class, 'create'])->name('consultas.create');
        Route::post('/consultas/{id}', [ConsultaController::class, 'store'])->name('consultas.store');
        Route::get('/consultas/{id}/edit', [ConsultaController::class, 'edit'])->name('consultas.edit');
        Route::put('/consultas/{id}', [ConsultaController::class, 'update'])->name('consultas.update');
    });
});

Route::redirect('/register', '/users/create');
Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
