<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acompanhamentos', function (Blueprint $table) {
            $table->timestamp('whatsapp_lembrete_enviado_em')->nullable()->after('agendado_para');
            $table->timestamp('whatsapp_lembrete_para')->nullable()->after('whatsapp_lembrete_enviado_em');
        });
    }

    public function down(): void
    {
        Schema::table('acompanhamentos', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_lembrete_enviado_em', 'whatsapp_lembrete_para']);
        });
    }
};
