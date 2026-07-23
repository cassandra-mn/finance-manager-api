<?php

namespace App\Data\Recurrences;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionType;
use App\Http\Requests\Recurrences\ListRecurrencesRequest;

final readonly class RecurrenceFiltersData
{
    public function __construct(
        public ?int $accountId = null,
        public ?int $categoryId = null,
        public ?TransactionType $type = null,
        public ?RecurrenceFrequency $frequency = null,
        public ?bool $isActive = null,
        public ?string $search = null,
    ) {}

    public static function fromRequest(ListRecurrencesRequest $request): self
    {
        return new self(
            accountId: $request->filled('account_id') ? (int) $request->integer('account_id') : null,
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            type: $request->filled('type') ? TransactionType::from($request->string('type')->toString()) : null,
            frequency: $request->filled('frequency') ? RecurrenceFrequency::from($request->string('frequency')->toString()) : null,
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
            search: $request->string('search')->toString() ?: null,
        );
    }
}
