<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consulta_agendamentos', function (Blueprint $table) {
            $table->foreignId('acompanhamento_id')
                ->nullable()
                ->after('gestante_id')
                ->constrained('acompanhamentos')
                ->nullOnDelete();
            $table->timestamp('whatsapp_enviado_em')->nullable()->after('email_enviado_em');
        });
    }

    public function down(): void
    {
        Schema::table('consulta_agendamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acompanhamento_id');
            $table->dropColumn('whatsapp_enviado_em');
        });
    }
};
