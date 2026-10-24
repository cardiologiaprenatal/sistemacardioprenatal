<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestantes', function (Blueprint $table) {
            $table->string('fluxo_atendimento', 64)->default('aguardando_consulta')->after('whatsapp_fluxo_estado');
            $table->timestamp('atendimento_personalizado_aceito_em')->nullable()->after('fluxo_atendimento');
            $table->foreignId('atendimento_personalizado_aceito_por')
                ->nullable()
                ->after('atendimento_personalizado_aceito_em')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('fluxo_atendimento');
        });

        Schema::table('acompanhamentos', function (Blueprint $table) {
            $table->foreignId('encaminhado_por_user_id')
                ->nullable()
                ->after('consulta_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('encaminhado_em')->nullable()->after('encaminhado_por_user_id');
            $table->index('encaminhado_em');
        });

        $consultaRealizada = DB::table('gestantes')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('consultas')
                    ->whereColumn('consultas.gestante_id', 'gestantes.id');
            })
            ->whereNull('atendimento_personalizado_aceito_em')
            ->update(['fluxo_atendimento' => 'consulta_realizada']);

        unset($consultaRealizada);
    }

    public function down(): void
    {
        Schema::table('acompanhamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encaminhado_por_user_id');
            $table->dropColumn('encaminhado_em');
        });

        Schema::table('gestantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atendimento_personalizado_aceito_por');
            $table->dropColumn([
                'fluxo_atendimento',
                'atendimento_personalizado_aceito_em',
            ]);
        });
    }
};
