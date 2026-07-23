<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de recorrência (receitas e despesas fixas/variáveis). Nesta etapa
 * a entidade cobre apenas o CRUD da regra — a geração automática de
 * lançamentos a partir dela fica para uma próxima etapa (via Resolver de
 * estratégia por frequência).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('entry_type');
            $table->string('description');
            $table->bigInteger('amount_cents');
            $table->string('frequency');
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('start_date');
            $table->date('next_due_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrences');
    }
};
