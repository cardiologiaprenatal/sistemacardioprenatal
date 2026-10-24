<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (! Schema::hasColumn('gestantes', 'email')) {
                $table->string('email')->nullable()->unique()->after('telefone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            if (Schema::hasColumn('gestantes', 'email')) {
                $table->dropUnique(['email']);
                $table->dropColumn('email');
            }
        });
    }
};
