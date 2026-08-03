<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dia de vencimento e (opcionalmente) dia de fechamento da fatura, usados
 * apenas por contas type=credit_card. Quando invoice_closing_day é nulo, é
 * calculado a partir de invoice_due_day (ver Account::effectiveInvoiceClosingDay
 * e config('finance.credit_cards.default_closing_offset_days')).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('invoice_due_day')->nullable()->after('credit_limit_cents');
            $table->unsignedTinyInteger('invoice_closing_day')->nullable()->after('invoice_due_day');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn(['invoice_due_day', 'invoice_closing_day']);
        });
    }
};
