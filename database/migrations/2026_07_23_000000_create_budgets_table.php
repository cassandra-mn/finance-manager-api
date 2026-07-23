<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orçamento mensal de gasto por categoria. O consumo (spent_cents) é
 * calculado sob demanda a partir das transações — não é armazenado aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_cents');
            $table->unsignedTinyInteger('reference_month');
            $table->smallInteger('reference_year');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'reference_year', 'reference_month']);
        });

        DB::statement('ALTER TABLE budgets ADD CONSTRAINT budgets_amount_cents_positive CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE budgets ADD CONSTRAINT budgets_reference_month_range CHECK (reference_month BETWEEN 1 AND 12)');

        // Único orçamento não excluído por usuário/categoria/mês/ano. Índice parcial
        // (em vez de unique() simples) para que orçamentos soft-deleted não bloqueiem
        // a criação de um novo orçamento equivalente.
        DB::statement(
            'CREATE UNIQUE INDEX budgets_user_category_period_unique ON budgets (user_id, category_id, reference_month, reference_year) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
