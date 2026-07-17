<?php

namespace App\Actions\Transactions;

use App\Enum\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class MarkTransactionAsPaidAction
{
    public function execute(Transaction $transaction): Transaction
    {
        if ($transaction->status === TransactionStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível pagar uma transação cancelada.'],
            ]);
        }

        $transaction->update([
            'status' => TransactionStatus::PAID,
            'paid_at' => Carbon::now(),
        ]);

        return $transaction;
    }
}
