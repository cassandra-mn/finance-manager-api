<?php

namespace App\Data\Recurrences;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Http\Requests\Recurrences\UpdateRecurrenceRequest;
use Illuminate\Support\Carbon;

final readonly class UpdateRecurrenceData
{
    public function __construct(
        public ?int $accountId,
        public ?int $categoryId,
        public ?TransactionType $type,
        public ?TransactionEntryType $entryType,
        public ?string $description,
        public ?int $amountCents,
        public ?RecurrenceFrequency $frequency,
        public ?Carbon $startDate,
        public ?Carbon $nextDueDate,
        public ?Carbon $endDate,
        public ?string $notes,
    ) {}

    public static function fromRequest(UpdateRecurrenceRequest $request): self
    {
        return new self(
            accountId: $request->filled('account_id') ? (int) $request->integer('account_id') : null,
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            type: $request->filled('type') ? TransactionType::from($request->string('type')->toString()) : null,
            entryType: $request->filled('entry_type') ? TransactionEntryType::from($request->string('entry_type')->toString()) : null,
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            amountCents: $request->filled('amount_cents') ? (int) $request->integer('amount_cents') : null,
            frequency: $request->filled('frequency') ? RecurrenceFrequency::from($request->string('frequency')->toString()) : null,
            startDate: $request->filled('start_date') ? Carbon::parse($request->string('start_date')->toString()) : null,
            nextDueDate: $request->filled('next_due_date') ? Carbon::parse($request->string('next_due_date')->toString()) : null,
            endDate: $request->filled('end_date') ? Carbon::parse($request->string('end_date')->toString()) : null,
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );
    }
}
