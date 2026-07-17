<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;

final class DeleteTransactionAction
{
    public function execute(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
