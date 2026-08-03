<?php

use App\Enum\TransactionGroupStatus;
use App\Enum\TransactionGroupType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrupador de transações (fatura de cartão de crédito, por ora). Não guarda
 * total_cents: o valor é somado sob demanda a partir das transações filhas,
 * mesmo princípio de AccountBalanceService (evita cache desincronizado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('type')->default(TransactionGroupType::CREDIT_CARD_INVOICE->value);
            $table->string('name')->nullable();
            $table->date('reference_month');
            $table->date('closing_date');
            $table->date('due_date');
            $table->string('status')->default(TransactionGroupStatus::OPEN->value);
            $table->foreignId('payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'account_id']);
        });

        // Impede duas faturas para o mesmo cartão no mesmo ciclo. Índice
        // parcial — só se aplica a agrupadores do tipo credit_card_invoice e
        // não excluídos, mesmo padrão de transactions_recurrence_due_date_unique.
        DB::statement(
            "CREATE UNIQUE INDEX transaction_groups_account_reference_month_unique ON transaction_groups (account_id, reference_month) WHERE type = 'credit_card_invoice' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transaction_groups_account_reference_month_unique');

        Schema::dropIfExists('transaction_groups');
    }
};
