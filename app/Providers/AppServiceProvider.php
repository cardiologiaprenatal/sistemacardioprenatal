<?php

namespace App\Providers;

use App\Contracts\AtendimentoMenuFlow;
use App\Contracts\LlmProvider;
use App\Contracts\RagContextBuilder;
use App\Models\Consulta;
use App\Models\User;
use App\Observers\ConsultaObserver;
use App\Services\Atendimento\GestanteRagContextService;
use App\Services\Gemini\GeminiChatService;
use App\Services\WhatsAppMenuFlowService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AtendimentoMenuFlow::class, WhatsAppMenuFlowService::class);
        $this->app->bind(RagContextBuilder::class, GestanteRagContextService::class);
        $this->app->bind(LlmProvider::class, function ($app) {
            return match ((string) config('services.llm.provider', 'gemini')) {
                default => $app->make(GeminiChatService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.cardioprenatal');
        Consulta::observe(ConsultaObserver::class);

        foreach (User::abilities() as $ability) {
            Gate::define($ability, fn (User $user) => $user->hasAbility($ability));
        }

        // Produção atrás de Nginx/Caddy: FORCE_HTTPS=true + APP_URL=https://...
        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)) {
            URL::forceScheme('https');
        }
    }
}
