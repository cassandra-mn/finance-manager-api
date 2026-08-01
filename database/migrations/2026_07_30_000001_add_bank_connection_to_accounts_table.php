<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->foreignId('bank_connection_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('external_account_id')->nullable()->after('bank_connection_id');
        });

        // Dedup: nunca importar a mesma conta da Pluggy duas vezes para a
        // mesma conexão. Índice parcial para não afetar contas manuais
        // (bank_connection_id nulo) nem contas já excluídas.
        DB::statement(
            'CREATE UNIQUE INDEX accounts_bank_connection_external_account_unique ON accounts (bank_connection_id, external_account_id) WHERE bank_connection_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS accounts_bank_connection_external_account_unique');

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_connection_id');
            $table->dropColumn('external_account_id');
        });
    }
};
