<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lê CSV ou Excel (primeira planilha) no mesmo formato de linhas associativas.
 */
class PlanilhaGestanteReader
{
    /** @var list<string> */
    public const EXTENSOES = ['csv', 'txt', 'xlsx', 'xls'];

    public function extensaoValida(string $nomeArquivo): bool
    {
        $ext = strtolower(pathinfo($nomeArquivo, PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSOES, true);
    }

    /**
     * @return array{header: list<string>, rows: list<array<string, string>>, skipped: int}
     */
    public function ler(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw new \RuntimeException('Não foi possível abrir o arquivo.');
        }

        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->lerExcel($path);
        }

        return $this->lerCsv($path);
    }

    /**
     * @return array{header: list<string>, rows: list<array<string, string>>, skipped: int}
     */
    private function lerCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o CSV.');
        }

        $headerRaw = fgetcsv($handle);
        if ($headerRaw === false) {
            fclose($handle);
            throw new \RuntimeException('Arquivo sem cabeçalho.');
        }

        $header = $this->normalizarCabecalho($headerRaw);
        $rows = [];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->linhaVazia($row)) {
                continue;
            }

            if (count($row) !== count($header)) {
                $skipped++;

                continue;
            }

            $combinada = array_combine($header, array_map(fn ($c) => $this->cellToString($c), $row));
            if ($combinada === false) {
                $skipped++;

                continue;
            }

            $rows[] = $combinada;
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * @return array{header: list<string>, rows: list<array<string, string>>, skipped: int}
     */
    private function lerExcel(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matriz = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($matriz === []) {
            throw new \RuntimeException('Planilha vazia.');
        }

        $header = $this->normalizarCabecalho(array_shift($matriz) ?? []);
        if ($header === [] || implode('', $header) === '') {
            throw new \RuntimeException('Arquivo sem cabeçalho.');
        }

        $cols = count($header);
        $rows = [];

        foreach ($matriz as $row) {
            $valores = array_map(fn ($c) => $this->cellToString($c), array_slice($row, 0, $cols));
            if ($this->linhaVazia($valores)) {
                continue;
            }

            if (count($valores) < $cols) {
                $valores = array_pad($valores, $cols, '');
            }

            $combinada = array_combine($header, $valores);
            if ($combinada !== false) {
                $rows[] = $combinada;
            }
        }

        return ['header' => $header, 'rows' => $rows, 'skipped' => 0];
    }

    /**
     * @param  list<mixed>  $header
     * @return list<string>
     */
    private function normalizarCabecalho(array $header): array
    {
        return array_map(function ($h) {
            $v = $this->cellToString($h);
            $v = preg_replace('/^\xEF\xBB\xBF/', '', $v) ?? $v;

            return trim($v);
        }, $header);
    }

    /**
     * @param  list<mixed>  $row
     */
    private function linhaVazia(array $row): bool
    {
        foreach ($row as $cell) {
            if ($this->cellToString($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value) && $value == floor($value)) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }
}
