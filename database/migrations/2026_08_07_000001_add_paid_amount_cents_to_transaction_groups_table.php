<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_groups', function (Blueprint $table): void {
            $table->bigInteger('paid_amount_cents')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_groups', function (Blueprint $table): void {
            $table->dropColumn('paid_amount_cents');
        });
    }
};
