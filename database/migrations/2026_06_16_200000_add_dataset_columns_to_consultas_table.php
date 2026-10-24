<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('consultas', 'idade_materna')) {
            return;
        }

        Schema::table('consultas', function (Blueprint $table) {
            $table->integer('idade_materna')->nullable()->after('data_consulta');
            $table->string('etnia', 50)->nullable()->after('idade_materna');
            $table->decimal('imc_pre_gestacional', 5, 2)->nullable()->after('etnia');
            $table->integer('paridade')->nullable()->after('imc_pre_gestacional');
            $table->integer('abortamentos_previos')->nullable()->after('paridade');
            $table->boolean('historico_natimorto')->default(false)->after('abortamentos_previos');
            $table->boolean('historico_filho_anterior_chd')->default(false)->after('historico_natimorto');
            $table->boolean('diabetes_pre_gestacional')->default(false)->after('historico_familiar_chd');
            $table->boolean('lupus_eritomatoso_sistemico')->default(false)->after('diabetes_gestacional');
            $table->boolean('fenilcetonuria')->default(false)->after('lupus_eritomatoso_sistemico');
            $table->boolean('doencas_tireoidianas')->default(false)->after('fenilcetonuria');
            $table->boolean('hipertensao_cronica')->default(false)->after('doencas_tireoidianas');
            $table->boolean('rubeola')->default(false)->after('hipertensao_cronica');
            $table->boolean('citomegalovirus')->default(false)->after('rubeola');
            $table->boolean('toxoplasmose')->default(false)->after('citomegalovirus');
            $table->boolean('sifilis')->default(false)->after('toxoplasmose');
            $table->boolean('uso_isotretinoina')->default(false)->after('uso_medicamentos');
            $table->boolean('uso_acido_valproico')->default(false)->after('uso_isotretinoina');
            $table->boolean('uso_litio')->default(false)->after('uso_acido_valproico');
            $table->boolean('drogas_ilicitas')->default(false)->after('alcoolismo');
            $table->boolean('exposicao_ocupacional')->default(false)->after('drogas_ilicitas');
            $table->boolean('usg_precoce_confirmada')->default(false)->after('exposicao_ocupacional');
            $table->string('tipo_gestacao', 32)->nullable()->after('usg_precoce_confirmada');
            $table->string('corionicidade', 50)->nullable()->after('tipo_gestacao');
            $table->boolean('translucencia_nucal_aumentada')->default(false)->after('translucencia_nucal_mm');
            $table->boolean('regurgitacao_tricuspide_fetal')->default(false)->after('doppler_ducto_venoso');
            $table->boolean('malformacoes_extracardiacas_associadas')->default(false)->after('regurgitacao_tricuspide_fetal');
            $table->boolean('crescimento_fetal_rcf_iugr')->default(false)->after('malformacoes_extracardiacas_associadas');
            $table->string('polidramnio_oligoidramnio', 32)->nullable()->after('crescimento_fetal_rcf_iugr');
        });
    }

    public function down(): void
    {
        // irreversível em ambiente de importação
    }
};
