<?php

namespace App\Console\Commands;

use App\Models\Consulta;
use App\Models\Gestante;
use App\Services\DatasetGestanteImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDatasetCsvCommand extends Command
{
    protected $signature = 'dataset:import
                            {path? : Caminho do CSV (default: storage/app/imports/dataset_com_id_gestante.csv)}
                            {--fresh : Apaga gestantes/consultas antes de importar}
                            {--limit=0 : Limitar quantidade de linhas (0 = todas)}';

    protected $description = 'Importa dataset_com_id_gestante.csv para MySQL (gestantes + consultas)';

    public function handle(DatasetGestanteImporter $importer): int
    {
        $path = $this->argument('path')
            ?: storage_path('app/imports/dataset_com_id_gestante.csv');

        if (! is_readable($path)) {
            $this->error("CSV não encontrado ou ilegível: {$path}");

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
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error('Não foi possível abrir o CSV.');

            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('CSV sem cabeçalho.');

            return self::FAILURE;
        }

        $header = array_map(static fn ($h) => trim((string) $h), $header);
        if (! in_array('id_gestante', $header, true)) {
            fclose($handle);
            $this->error('CSV sem coluna id_gestante.');

            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($limit > 0 ? $limit : 5000);
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($limit > 0 && $imported >= $limit) {
                    break;
                }

                if (count($row) !== count($header)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $linha = array_combine($header, $row);
                if ($linha === false) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                try {
                    $importer->upsert($linha);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors++;
                    if ($errors <= 5) {
                        $this->newLine();
                        $this->error('Falha em '.($linha['id_gestante'] ?? '?').': '.$e->getMessage());
                    }
                }

                $bar->advance();

                if ($imported > 0 && $imported % 500 === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            $bar->finish();
            $this->newLine();
            $this->error('Abortado: '.$e->getMessage());

            return self::FAILURE;
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);
        $this->info("Importação concluída. importadas={$imported} ignoradas={$skipped} erros={$errors}");
        $this->line('gestantes='.Gestante::count().' consultas='.Consulta::count());

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
