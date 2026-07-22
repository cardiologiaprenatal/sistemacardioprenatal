<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analises_historico')) {
            return;
        }

        Schema::create('analises_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->foreignId('consulta_id')->nullable()->constrained('consultas')->nullOnDelete();
            $table->string('evento_tipo', 32);
            $table->uuid('analise_uuid')->nullable();
            $table->decimal('probabilidade_ccf', 10, 6)->nullable();
            $table->string('classificacao_risco', 16)->nullable();
            $table->decimal('score_prioridade', 8, 2)->nullable();
            $table->string('prioridade_ecocardiograma', 16)->nullable();
            $table->boolean('recomenda_ecocardiograma')->default(false);
            $table->json('intercorrencias')->nullable();
            $table->json('shap')->nullable();
            $table->json('recomendacoes')->nullable();
            $table->json('predicao')->nullable();
            $table->json('analise')->nullable();
            $table->json('qualidade_dados')->nullable();
            $table->json('full_response');
            $table->string('status', 16)->default('concluida');
            $table->text('erro_mensagem')->nullable();
            $table->timestamps();

            $table->index(['gestante_id', 'created_at']);
            $table->index('analise_uuid');
            $table->index('classificacao_risco');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analises_historico');
    }
};
