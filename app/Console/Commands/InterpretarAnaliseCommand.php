<?php

namespace App\Console\Commands;

use App\Jobs\InterpretarGestanteJob;
use App\Models\AnaliseHistorico;
use App\Models\InterpretacaoClinica;
use Illuminate\Console\Command;

class InterpretarAnaliseCommand extends Command
{
    protected $signature = 'interpretacao:run

                            {analise_id? : ID em analises_historico (default: última concluída)}

                            {--sync : Executa na hora}

                            {--force : Ignora cache local e regenera}';

    protected $description = 'Dispara interpretação clínica (Gemini/mock v2) para uma análise CatBoost';

    public function handle(): int
    {

        if (! config('services.interpretacao.enabled')) {

            $this->warn('INTERPRETACAO_GEMINI_ENABLED=false — ative no .env');

            return self::FAILURE;

        }

        $id = $this->argument('analise_id');

        $analise = $id

            ? AnaliseHistorico::query()->find($id)

            : AnaliseHistorico::query()->where('status', 'concluida')->latest('id')->first();

        if (! $analise || $analise->status !== 'concluida') {

            $this->error('Análise concluída não encontrada.');

            return self::FAILURE;

        }

        if ($this->option('force')) {

            InterpretacaoClinica::query()

                ->where('analise_historico_id', $analise->id)

                ->update([

                    'contexto_hash' => 'force-'.uniqid('', true),

                    'status' => InterpretacaoClinica::STATUS_PENDENTE,

                ]);

            $this->warn('Cache local invalidado (--force).');

        }

        $this->info("Interpretando analise_id={$analise->id} gestante_id={$analise->gestante_id}");

        if ($this->option('sync')) {

            InterpretarGestanteJob::dispatchSync($analise->id);

        } else {

            InterpretarGestanteJob::dispatch($analise->id);

            $this->warn('Job enfileirado. Worker: php artisan queue:work');

        }

        $row = InterpretacaoClinica::query()

            ->where('analise_historico_id', $analise->id)

            ->latest('id')

            ->first();

        if ($row) {

            $this->table(

                ['campo', 'valor'],

                [

                    ['status', $row->status],

                    ['mock', $row->mock ? 'sim' : 'não'],

                    ['model_id', $row->model_id ?? '—'],

                    ['resumo_medico', \Illuminate\Support\Str::limit((string) $row->resumo_medico, 120)],

                ]

            );

        }

        return self::SUCCESS;

    }
}
