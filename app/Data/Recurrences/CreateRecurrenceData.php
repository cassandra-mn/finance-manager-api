<?php

namespace App\Data\Recurrences;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Http\Requests\Recurrences\StoreRecurrenceRequest;
use Illuminate\Support\Carbon;

final readonly class CreateRecurrenceData
{
    public function __construct(
        public int $userId,
        public int $accountId,
        public ?int $categoryId,
        public TransactionType $type,
        public TransactionEntryType $entryType,
        public string $description,
        public int $amountCents,
        public RecurrenceFrequency $frequency,
        public Carbon $startDate,
        public Carbon $nextDueDate,
        public ?Carbon $endDate,
        public ?string $notes,
    ) {}

    public static function fromRequest(StoreRecurrenceRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            accountId: (int) $request->integer('account_id'),
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            type: TransactionType::from($request->string('type')->toString()),
            entryType: TransactionEntryType::from($request->string('entry_type')->toString()),
            description: $request->string('description')->toString(),
            amountCents: (int) $request->integer('amount_cents'),
            frequency: RecurrenceFrequency::from($request->string('frequency')->toString()),
            startDate: Carbon::parse($request->string('start_date')->toString()),
            nextDueDate: Carbon::parse($request->string('next_due_date')->toString()),
            endDate: $request->filled('end_date') ? Carbon::parse($request->string('end_date')->toString()) : null,
            notes: $request->string('notes')->toString() ?: null,
        );
    }
}
