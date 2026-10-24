<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos textuais da gestante para RAG no WhatsApp (fase 1–2).
 * Conteúdo já extraído (PDF/OCR futuro grava aqui o texto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestante_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->string('titulo', 255);
            $table->string('tipo', 64)->default('outro'); // exame|laudo|consulta|anamnese|outro
            $table->string('fonte', 128)->nullable(); // upload|manual|import
            $table->date('data_documento')->nullable();
            $table->longText('conteudo_texto');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['gestante_id', 'ativo'], 'gestante_docs_gestante_ativo_idx');
        });

        Schema::create('gestante_documento_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestante_documento_id')->constrained('gestante_documentos')->cascadeOnDelete();
            $table->foreignId('gestante_id')->constrained('gestantes')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('conteudo');
            $table->timestamps();

            $table->index(['gestante_id', 'gestante_documento_id'], 'gestante_chunks_gestante_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestante_documento_chunks');
        Schema::dropIfExists('gestante_documentos');
    }
};
