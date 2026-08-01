<?php

use App\Enum\TransactionOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('recurrence_id');
            $table->string('origin')->default(TransactionOrigin::MANUAL->value)->after('external_id');
        });

        // Dedup das transações importadas do Open Finance: nunca reimportar a
        // mesma transação bancária para a mesma conta. Índice parcial — só se
        // aplica a linhas com external_id preenchido e não excluídas, mesmo
        // padrão de transactions_recurrence_due_date_unique.
        DB::statement(
            'CREATE UNIQUE INDEX transactions_account_external_id_unique ON transactions (account_id, external_id) WHERE external_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transactions_account_external_id_unique');

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['external_id', 'origin']);
        });
    }
};
