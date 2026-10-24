<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acompanhamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->foreignId('consulta_id')->nullable()->constrained('consultas')->nullOnDelete();
            $table->foreignId('analise_historico_id')->nullable()->constrained('analises_historico')->nullOnDelete();
            $table->string('status', 32)->default('pendente');
            $table->string('prioridade', 16)->nullable();
            $table->string('motivo', 64)->nullable();
            $table->string('titulo', 255);
            $table->text('observacao')->nullable();
            $table->timestamp('agendado_para')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'prioridade']);
            $table->index(['gestante_id', 'status']);
            $table->index('agendado_para');
        });

        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->foreignId('acompanhamento_id')->nullable()->constrained('acompanhamentos')->nullOnDelete();
            $table->string('tipo', 32);
            $table->string('severidade', 16)->default('atencao');
            $table->string('titulo', 255);
            $table->text('mensagem')->nullable();
            $table->string('chave', 120)->nullable();
            $table->timestamp('lido_em')->nullable();
            $table->timestamp('resolvido_em')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['gestante_id', 'resolvido_em']);
            $table->index(['severidade', 'created_at']);
            $table->unique(['gestante_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('acompanhamentos');
    }
};
