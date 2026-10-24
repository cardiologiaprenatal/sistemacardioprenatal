<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consulta_agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->foreignId('profissional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('profissional_nome', 255);
            $table->dateTime('agendado_para');
            $table->string('status', 32)->default('agendada');
            $table->timestamp('email_enviado_em')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['gestante_id', 'agendado_para']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_agendamentos');
    }
};
