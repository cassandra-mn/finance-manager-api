<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de importações de extrato (OFX/CSV) por conta — cada upload gera
 * um registro com o resumo (transações criadas/ignoradas), para auditoria.
 * As transações em si são gravadas normalmente em `transactions`
 * (origin = statement_import, external_id preenchido para dedup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('format');
            $table->string('original_filename');
            $table->unsignedInteger('transactions_created')->default(0);
            $table->unsignedInteger('transactions_skipped')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_imports');
    }
};
