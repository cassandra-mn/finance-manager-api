<?php

namespace App\Data\Transactions;

use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use Illuminate\Support\Carbon;

final readonly class CreateTransactionData
{
    public function __construct(
        public int $userId,
        public int $accountId,
        public ?int $categoryId,
        public TransactionType $type,
        public TransactionEntryType $entryType,
        public string $description,
        public int $amountCents,
        public Carbon $dueDate,
        public ?string $notes,
    ) {}

    public static function fromRequest(StoreTransactionRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            accountId: (int) $request->integer('account_id'),
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            type: TransactionType::from($request->string('type')->toString()),
            entryType: TransactionEntryType::from($request->string('entry_type')->toString()),
            description: $request->string('description')->toString(),
            amountCents: (int) $request->integer('amount_cents'),
            dueDate: Carbon::parse($request->string('due_date')->toString()),
            notes: $request->string('notes')->toString() ?: null,
        );
    }
}
