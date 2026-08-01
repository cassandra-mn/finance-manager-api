<?php

use App\Enum\BankConnectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Representa a conexão do usuário com uma instituição financeira via Open
 * Finance (agregador Pluggy). Guarda apenas o `pluggy_item_id` — as
 * credenciais bancárias em si ficam do lado da Pluggy, nunca aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pluggy_item_id');
            $table->string('institution_id')->nullable();
            $table->string('institution_name')->nullable();
            $table->string('status')->default(BankConnectionStatus::UPDATING->value);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        // Um `pluggy_item_id` é um handle global do agregador; o índice único
        // parcial impede duas conexões locais não excluídas disputando o mesmo
        // item, mas permite reconectar o mesmo banco (item novo) após um
        // disconnect (soft delete) do item anterior.
        DB::statement(
            'CREATE UNIQUE INDEX bank_connections_pluggy_item_id_unique ON bank_connections (pluggy_item_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_connections');
    }
};
