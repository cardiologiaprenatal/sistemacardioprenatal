<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interpretacoes_clinicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->foreignId('consulta_id')->nullable()->constrained('consultas')->nullOnDelete();
            $table->foreignId('analise_historico_id')->constrained('analises_historico')->cascadeOnDelete();

            $table->string('status', 32)->default('pendente'); // pendente|concluida|erro|cache_hit
            $table->string('prompt_version', 64)->default('system_v1');
            $table->string('model_id', 128)->nullable();
            $table->string('contexto_hash', 64)->nullable()->index();
            $table->string('schema_version', 16)->default('1.0');

            $table->text('resumo_medico')->nullable();
            $table->text('resumo_gestante')->nullable();
            $table->json('fatores_explicados')->nullable();
            $table->json('recomendacoes')->nullable();
            $table->json('deltas')->nullable();
            $table->json('contexto_enviado')->nullable();
            $table->json('resposta_bruta')->nullable();

            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('mock')->default(true);
            $table->text('erro_mensagem')->nullable();

            $table->timestamps();

            $table->index(['gestante_id', 'status']);
            $table->unique(['analise_historico_id', 'prompt_version'], 'interp_analise_prompt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interpretacoes_clinicas');
    }
};
