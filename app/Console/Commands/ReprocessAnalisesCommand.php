<?php

namespace App\Console\Commands;

use App\Jobs\ExecutarAnaliseGestante;
use App\Models\AnaliseHistorico;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Services\PythonApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReprocessAnalisesCommand extends Command
{
    protected $signature = 'analises:reprocess
                            {--limit=0 : Limitar gestantes (0 = todas com consulta)}
                            {--only-missing : Só gestantes sem análise concluída}
                            {--fresh : Apaga analises_historico antes de reprocessar}
                            {--sync : Executa na hora (sem fila)}';

    protected $description = 'Reprocessa análises ML (API Python → analises_historico) para popular o dashboard MySQL';

    public function handle(PythonApiService $api): int
    {
        try {
            $api->health();
        } catch (\Throwable $e) {
            $this->error('API Python indisponível: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if ($this->input->isInteractive() && ! $this->confirm('Apagar TODAS as análises em analises_historico?', true)) {
                return self::SUCCESS;
            }
            DB::table('analises_historico')->truncate();
            $this->warn('Tabela analises_historico limpa.');
        }

        $limit = (int) $this->option('limit');
        $onlyMissing = (bool) $this->option('only-missing');
        $sync = (bool) $this->option('sync');

        $jaAnalisadas = $onlyMissing
            ? AnaliseHistorico::query()
                ->where('status', 'concluida')
                ->distinct()
                ->pluck('gestante_id')
                ->all()
            : [];

        $query = Gestante::query()
            ->whereHas('consultas')
            ->orderBy('id');

        if ($jaAnalisadas !== []) {
            $query->whereNotIn('id', $jaAnalisadas);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $gestantes = $query->get(['id']);
        $total = $gestantes->count();

        if ($total === 0) {
            $this->info('Nada a reprocessar.');

            return self::SUCCESS;
        }

        $this->info(($sync ? 'Executando' : 'Enfileirando')." {$total} análise(s)…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0;
        $fail = 0;

        foreach ($gestantes as $gestante) {
            $consultaId = Consulta::query()
                ->where('gestante_id', $gestante->id)
                ->latest('id')
                ->value('id');

            try {
                if ($sync) {
                    ExecutarAnaliseGestante::dispatchSync(
                        (int) $gestante->id,
                        $consultaId ? (int) $consultaId : null,
                        AnaliseHistorico::EVENTO_REPROCESSAMENTO,
                    );
                } else {
                    ExecutarAnaliseGestante::dispatch(
                        (int) $gestante->id,
                        $consultaId ? (int) $consultaId : null,
                        AnaliseHistorico::EVENTO_REPROCESSAMENTO,
                    );
                }
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                if ($fail <= 5) {
                    $this->newLine();
                    $this->error("Falha gestante #{$gestante->id}: ".$e->getMessage());
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Concluído. ok={$ok} falhas={$fail}");
        $this->line('analises_historico concluídas='.AnaliseHistorico::where('status', 'concluida')->count());

        if (! $sync) {
            $pending = DB::table('jobs')->count();
            $this->warn("Jobs na fila: {$pending}. Rode: php artisan queue:work --stop-when-empty");
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
