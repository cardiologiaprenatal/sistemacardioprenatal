<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (! Schema::hasColumn('gestantes', 'whatsapp_menu_opcao')) {
                $table->string('whatsapp_menu_opcao', 32)->nullable()->after('whatsapp_ia_pausada_ate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (Schema::hasColumn('gestantes', 'whatsapp_menu_opcao')) {
                $table->dropColumn('whatsapp_menu_opcao');
            }
        });
    }
};
