<?php

namespace App\Console\Commands;

use App\Models\Gestante;
use App\Services\WhatsAppRagContextService;
use Illuminate\Console\Command;

class WhatsappRagIndexDocumentoCommand extends Command
{
    protected $signature = 'whatsapp:rag-index
        {gestante_id : ID interno da gestante}
        {--titulo=Documento : Título do documento}
        {--tipo=outro : exame|laudo|consulta|anamnese|outro}
        {--arquivo= : Caminho de arquivo .txt/.md (UTF-8)}
        {--texto= : Texto direto (alternativa a --arquivo)}';

    protected $description = 'Indexa texto de documento da gestante para RAG do WhatsApp.';

    public function handle(WhatsAppRagContextService $rag): int
    {
        $gestante = Gestante::query()->find($this->argument('gestante_id'));
        if ($gestante === null) {
            $this->error('Gestante não encontrada.');

            return self::FAILURE;
        }

        $texto = (string) $this->option('texto');
        $arquivo = $this->option('arquivo');
        if ($arquivo) {
            if (! is_readable($arquivo)) {
                $this->error('Arquivo ilegível: '.$arquivo);

                return self::FAILURE;
            }
            $texto = (string) file_get_contents($arquivo);
        }

        $texto = trim($texto);
        if ($texto === '') {
            $this->error('Informe --texto=... ou --arquivo=...');

            return self::FAILURE;
        }

        $doc = $rag->indexDocumentoTexto(
            $gestante,
            (string) $this->option('titulo'),
            $texto,
            (string) $this->option('tipo'),
            'artisan',
        );

        $this->info("Documento #{$doc->id} indexado com {$doc->chunks->count()} chunk(s) para gestante {$gestante->id}.");

        return self::SUCCESS;
    }
}
