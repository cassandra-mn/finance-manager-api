<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meta de economia atrelada a uma conta real — o progresso não é um valor
 * guardado aqui, é sempre o saldo atual da conta vinculada
 * (AccountBalanceService::calculateCurrentBalance), o mesmo princípio de
 * "nunca armazenar o que dá pra derivar" já usado no resto do app. Cada
 * depósito real na conta já avança a meta, sem precisar de um registro de
 * "contribuição" separado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('target_cents');
            $table->date('target_date')->nullable();
            $table->string('color');
            $table->string('icon');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });

        DB::statement('ALTER TABLE goals ADD CONSTRAINT goals_target_cents_positive CHECK (target_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
