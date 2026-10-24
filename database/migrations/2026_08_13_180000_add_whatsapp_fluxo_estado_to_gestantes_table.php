<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (! Schema::hasColumn('gestantes', 'whatsapp_fluxo_estado')) {
                $table->string('whatsapp_fluxo_estado', 64)->nullable()->after('whatsapp_menu_opcao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (Schema::hasColumn('gestantes', 'whatsapp_fluxo_estado')) {
                $table->dropColumn('whatsapp_fluxo_estado');
            }
        });
    }
};
