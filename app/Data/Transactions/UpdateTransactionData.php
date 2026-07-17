<?php

namespace App\Data\Transactions;

use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use Illuminate\Support\Carbon;

final readonly class UpdateTransactionData
{
    public function __construct(
        public ?int $accountId,
        public ?int $categoryId,
        public ?TransactionType $type,
        public ?TransactionEntryType $entryType,
        public ?string $description,
        public ?int $amountCents,
        public ?Carbon $dueDate,
        public ?string $notes,
    ) {}

    public static function fromRequest(UpdateTransactionRequest $request): self
    {
        return new self(
            accountId: $request->filled('account_id') ? (int) $request->integer('account_id') : null,
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            type: $request->filled('type') ? TransactionType::from($request->string('type')->toString()) : null,
            entryType: $request->filled('entry_type') ? TransactionEntryType::from($request->string('entry_type')->toString()) : null,
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            amountCents: $request->filled('amount_cents') ? (int) $request->integer('amount_cents') : null,
            dueDate: $request->filled('due_date') ? Carbon::parse($request->string('due_date')->toString()) : null,
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );
    }
}
