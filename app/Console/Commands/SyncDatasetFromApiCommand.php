<?php

namespace App\Console\Commands;

use App\Exceptions\PythonApiException;
use App\Models\Consulta;
use App\Models\Gestante;
use App\Services\DatasetGestanteImporter;
use App\Services\PythonApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncDatasetFromApiCommand extends Command
{
    protected $signature = 'dataset:sync-from-api
                            {--fresh : Apaga gestantes/consultas antes de sincronizar}
                            {--only-new : Só cria gestantes que ainda não existem no MySQL}
                            {--limit=0 : Limitar quantidade (0 = todas da API)}
                            {--page-size=100 : Tamanho da página em GET /gestantes}';

    protected $description = 'Sincroniza gestantes do microsserviço Python (CSV/API) para o MySQL do Laravel';

    public function handle(PythonApiService $api, DatasetGestanteImporter $importer): int
    {
        try {
            $api->health();
        } catch (PythonApiException $e) {
            $this->error('API Python indisponível: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('Isso apagará TODAS as consultas e gestantes. Continuar?', true)) {
                return self::SUCCESS;
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('consultas')->truncate();
            DB::table('gestantes')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->warn('Tabelas gestantes/consultas limpas.');
        }

        $limit = (int) $this->option('limit');
        $pageSize = max(1, min(1000, (int) $this->option('page-size')));
        $onlyNew = (bool) $this->option('only-new');

        $existingIds = Gestante::query()->pluck('gestante_id')->flip()->all();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $processed = 0;

        $offset = 0;
        $total = null;
        $bar = null;

        $this->info('Sincronizando a partir de '.$this->apiBaseHint().' …');

        DB::beginTransaction();
        try {
            while (true) {
                $page = $api->listGestantes($offset, $pageSize);
                $total ??= (int) ($page['total'] ?? 0);
                $gestantes = $page['gestantes'] ?? [];

                if ($bar === null) {
                    $target = $limit > 0 ? min($limit, $total) : $total;
                    $bar = $this->output->createProgressBar(max(1, $target));
                    $bar->start();
                }

                if ($gestantes === []) {
                    break;
                }

                foreach ($gestantes as $item) {
                    if ($limit > 0 && $processed >= $limit) {
                        break 2;
                    }

                    if (! is_array($item)) {
                        $skipped++;
                        $bar->advance();
                        $processed++;

                        continue;
                    }

                    $codigo = strtoupper(trim((string) ($item['id_gestante'] ?? '')));
                    if ($codigo === '') {
                        $skipped++;
                        $bar->advance();
                        $processed++;

                        continue;
                    }

                    if ($onlyNew && isset($existingIds[$codigo])) {
                        $skipped++;
                        $bar->advance();
                        $processed++;

                        continue;
                    }

                    try {
                        $detail = $api->getGestante($codigo);
                        $linha = $importer->normalizeApiPayload($detail);
                        $result = $importer->upsert($linha);

                        if ($result === 'created') {
                            $created++;
                            $existingIds[$codigo] = true;
                        } else {
                            $updated++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        if ($errors <= 5) {
                            $this->newLine();
                            $this->error("Falha em {$codigo}: ".$e->getMessage());
                        }
                    }

                    $processed++;
                    $bar->advance();

                    if (($created + $updated) > 0 && ($created + $updated) % 200 === 0) {
                        DB::commit();
                        DB::beginTransaction();
                    }
                }

                $offset += count($gestantes);
                if ($offset >= $total) {
                    break;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($bar) {
                $bar->finish();
            }
            $this->newLine();
            $this->error('Abortado: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($bar) {
            $bar->finish();
        }

        $this->newLine(2);
        $this->info("Sync concluído. criadas={$created} atualizadas={$updated} ignoradas={$skipped} erros={$errors}");
        $this->line('API total≈'.($total ?? '?').' | MySQL gestantes='.Gestante::count().' consultas='.Consulta::count());

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function apiBaseHint(): string
    {
        return (string) config('services.python_api.url', 'PYTHON_API_URL');
    }
}
