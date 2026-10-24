<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestante_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->nullable()->constrained('gestantes')->nullOnDelete();
            $table->string('destinatario');
            $table->string('assunto');
            $table->text('mensagem');
            $table->string('tipo', 64)->nullable();
            $table->timestamps();

            $table->index('gestante_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestante_emails');
    }
};
