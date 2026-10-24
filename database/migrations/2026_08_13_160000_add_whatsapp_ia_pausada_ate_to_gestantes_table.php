<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (! Schema::hasColumn('gestantes', 'whatsapp_ia_pausada_ate')) {
                $table->timestamp('whatsapp_ia_pausada_ate')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (Schema::hasColumn('gestantes', 'whatsapp_ia_pausada_ate')) {
                $table->dropColumn('whatsapp_ia_pausada_ate');
            }
        });
    }
};
