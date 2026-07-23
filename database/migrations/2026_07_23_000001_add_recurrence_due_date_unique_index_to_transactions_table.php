<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garante, no nível do banco, que uma recorrência nunca gere mais de uma
 * transação para a mesma data de ocorrência (due_date). Índice único parcial
 * — aplica-se somente a linhas com recurrence_id preenchido e não excluídas —
 * seguindo o mesmo padrão de budgets_user_category_period_unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX transactions_recurrence_due_date_unique ON transactions (recurrence_id, due_date) WHERE recurrence_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transactions_recurrence_due_date_unique');
    }
};
