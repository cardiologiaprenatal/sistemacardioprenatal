<?php

use App\Http\Controllers\AnaliseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GestanteController;
use App\Http\Controllers\GestanteWhatsappController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {

    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('auth.login');
});

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('auth');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

// ROTAS PROTEGIDAS POR LOGIN
Route::group(['middleware' => 'auth'], function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Rotas de Análise (IA) via Job para evitar timeout
    Route::post('/dashboard/analisar', [AnaliseController::class, 'iniciarAnalise'])->name('dashboard.analisar');
    Route::get('/dashboard/verificar-analise', [AnaliseController::class, 'verificarStatus'])->name('dashboard.verificarAnalise');
    Route::get('/dashboard/resultado-analise', [AnaliseController::class, 'obterResultado'])->name('dashboard.resultadoAnalise');

    Route::resource('gestantes', GestanteController::class);

    Route::get('/historico-atendimento-whatsapp', [GestanteWhatsappController::class, 'historicoPage'])
        ->name('historico-whatsapp.index');

    Route::prefix('api')->group(function () {
        Route::get('/gestante-whatsapp', [GestanteWhatsappController::class, 'index']);
        Route::get('/gestante-whatsapp/{gestante}', [GestanteWhatsappController::class, 'show']);
    });

    // Rotas de Consulta
    Route::get('/consultas/import', [ConsultaController::class, 'index'])->name('consultas.import');
    Route::post('/consultas/import', [ConsultaController::class, 'import'])->name('consultas.import.store');
    Route::get('/consultas/create/{id}', [ConsultaController::class, 'create'])->name('consultas.create');
    Route::post('/consultas/{id}', [ConsultaController::class, 'store'])->name('consultas.store');
    Route::get('/consultas/{id}/edit', [ConsultaController::class, 'edit'])->name('consultas.edit');
    Route::put('/consultas/{id}', [ConsultaController::class, 'update'])->name('consultas.update');
});

Route::redirect('/register', '/users/create');
Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
Route::post('/users', [UserController::class, 'store'])->name('users.store');

