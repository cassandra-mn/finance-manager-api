<?php

namespace App\Actions\Transactions;

use App\Data\Transactions\CreateTransactionData;
use App\Enum\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class CreateTransactionAction
{
    public function execute(CreateTransactionData $data): Transaction
    {
        return DB::transaction(fn (): Transaction => Transaction::create([
            'user_id' => $data->userId,
            'account_id' => $data->accountId,
            'category_id' => $data->categoryId,
            'type' => $data->type,
            'entry_type' => $data->entryType,
            'status' => TransactionStatus::PENDING,
            'description' => $data->description,
            'amount_cents' => $data->amountCents,
            'due_date' => $data->dueDate,
            'notes' => $data->notes,
        ]));
    }
}
