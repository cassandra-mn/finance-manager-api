<?php

namespace App\Data\Goals;

use App\Http\Requests\Goals\UpdateGoalRequest;
use Illuminate\Support\Carbon;

final readonly class UpdateGoalData
{
    public function __construct(
        public ?int $accountId,
        public ?string $name,
        public ?int $targetCents,
        public ?Carbon $targetDate,
        public bool $clearTargetDate,
        public ?string $color,
        public ?string $icon,
        public ?string $notes,
        public bool $notesProvided,
    ) {}

    public static function fromRequest(UpdateGoalRequest $request): self
    {
        return new self(
            accountId: $request->filled('account_id') ? (int) $request->integer('account_id') : null,
            name: $request->filled('name') ? $request->string('name')->toString() : null,
            targetCents: $request->filled('target_cents') ? (int) $request->integer('target_cents') : null,
            targetDate: $request->filled('target_date') ? Carbon::parse($request->string('target_date')->toString()) : null,
            clearTargetDate: $request->has('target_date') && ! $request->filled('target_date'),
            color: $request->filled('color') ? $request->string('color')->toString() : null,
            icon: $request->filled('icon') ? $request->string('icon')->toString() : null,
            notes: $request->string('notes')->toString() ?: null,
            notesProvided: $request->has('notes'),
        );
    }
}
