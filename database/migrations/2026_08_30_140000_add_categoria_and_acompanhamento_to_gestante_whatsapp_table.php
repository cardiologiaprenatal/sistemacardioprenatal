<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gestante_whatsapp', function (Blueprint $table) {
            $table->foreignId('acompanhamento_id')
                ->nullable()
                ->after('gestante_id')
                ->constrained('acompanhamentos')
                ->nullOnDelete();
            $table->string('categoria', 64)->nullable()->after('tipo');
            $table->index('categoria');
            $table->index(['acompanhamento_id', 'created_at']);
        });

        $this->backfillLembretesExistentes();
    }

    public function down(): void
    {
        Schema::table('gestante_whatsapp', function (Blueprint $table) {
            $table->dropIndex(['acompanhamento_id', 'created_at']);
            $table->dropIndex(['categoria']);
            $table->dropConstrainedForeignId('acompanhamento_id');
            $table->dropColumn('categoria');
        });
    }

    /**
     * Associa lembretes já enviados à mensagem de saída mais próxima do horário do envio.
     */
    private function backfillLembretesExistentes(): void
    {
        if (! Schema::hasTable('acompanhamentos')) {
            return;
        }

        $rows = DB::table('acompanhamentos')
            ->whereNotNull('whatsapp_lembrete_enviado_em')
            ->get(['id', 'gestante_id', 'motivo', 'whatsapp_lembrete_enviado_em']);

        foreach ($rows as $acomp) {
            $enviado = Carbon::parse($acomp->whatsapp_lembrete_enviado_em);
            $categoria = $acomp->motivo === 'retorno'
                ? 'lembrete_retorno'
                : 'lembrete_consulta';

            $candidatas = DB::table('gestante_whatsapp')
                ->where('gestante_id', $acomp->gestante_id)
                ->where('tipo', 'saida')
                ->whereNull('acompanhamento_id')
                ->where('created_at', '>=', $enviado->copy()->subMinutes(5))
                ->where('created_at', '<=', $enviado->copy()->addMinutes(5))
                ->get(['id', 'created_at']);

            $melhor = $candidatas
                ->sortBy(fn ($msg) => abs(Carbon::parse($msg->created_at)->diffInSeconds($enviado)))
                ->first();

            if ($melhor === null) {
                continue;
            }

            DB::table('gestante_whatsapp')->where('id', $melhor->id)->update([
                'categoria' => $categoria,
                'acompanhamento_id' => $acomp->id,
            ]);
        }
    }
};
