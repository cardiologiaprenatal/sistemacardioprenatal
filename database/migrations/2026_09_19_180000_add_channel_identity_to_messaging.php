<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestante_canais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->nullable()->constrained('gestantes')->nullOnDelete();
            $table->string('canal', 16);
            $table->string('external_user_id', 64);
            $table->string('external_chat_id', 64)->nullable();
            $table->string('username', 64)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['canal', 'external_user_id']);
            $table->index(['canal', 'external_chat_id']);
            $table->index('gestante_id');
        });

        Schema::table('gestante_whatsapp', function (Blueprint $table) {
            $table->string('canal', 16)->default('whatsapp')->after('gestante_id');
            $table->foreignId('canal_identidade_id')->nullable()->after('canal')->constrained('gestante_canais')->nullOnDelete();
            $table->string('external_user_id', 64)->nullable()->after('canal_identidade_id');
            $table->string('external_chat_id', 64)->nullable()->after('external_user_id');
            $table->json('metadata')->nullable()->after('tempo_atendimento');

            $table->index(['canal', 'external_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gestante_whatsapp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canal_identidade_id');
            $table->dropIndex(['canal', 'external_chat_id']);
            $table->dropColumn([
                'canal',
                'external_user_id',
                'external_chat_id',
                'metadata',
            ]);
        });

        Schema::dropIfExists('gestante_canais');
    }
};
