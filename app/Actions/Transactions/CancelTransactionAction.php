<?php

namespace App\Actions\Transactions;

use App\Enum\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

final class CancelTransactionAction
{
    public function execute(Transaction $transaction): Transaction
    {
        if ($transaction->status === TransactionStatus::PAID) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível cancelar uma transação já paga.'],
            ]);
        }

        $transaction->update(['status' => TransactionStatus::CANCELLED]);

        return $transaction;
    }
}
