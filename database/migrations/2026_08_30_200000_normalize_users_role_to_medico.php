<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where(function ($query) {
            $query->where('role', 'user')->orWhereNull('role');
        })->update(['role' => 'medico']);

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('medico')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('user')->change();
            });
        }
    }
};
