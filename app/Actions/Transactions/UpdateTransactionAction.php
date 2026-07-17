<?php

namespace App\Actions\Transactions;

use App\Data\Transactions\UpdateTransactionData;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class UpdateTransactionAction
{
    public function execute(Transaction $transaction, UpdateTransactionData $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data): Transaction {
            $transaction->fill(array_filter([
                'account_id' => $data->accountId,
                'category_id' => $data->categoryId,
                'type' => $data->type,
                'entry_type' => $data->entryType,
                'description' => $data->description,
                'amount_cents' => $data->amountCents,
                'due_date' => $data->dueDate,
                'notes' => $data->notes,
            ], static fn (mixed $value): bool => $value !== null));

            $transaction->save();

            return $transaction;
        });
    }
}
