<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamento_solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 32);
            $table->string('status', 32)->default('pendente');
            $table->text('observacao')->nullable();
            $table->foreignId('acompanhamento_id')->nullable()->constrained('acompanhamentos')->nullOnDelete();
            $table->timestamps();

            $table->index(['gestante_id', 'status']);
            $table->index('tipo');
        });

        Schema::table('consulta_agendamentos', function (Blueprint $table) {
            $table->string('tipo', 32)->default('consulta')->after('status');
            $table->foreignId('solicitacao_id')
                ->nullable()
                ->after('acompanhamento_id')
                ->constrained('agendamento_solicitacoes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consulta_agendamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitacao_id');
            $table->dropColumn('tipo');
        });

        Schema::dropIfExists('agendamento_solicitacoes');
    }
};
