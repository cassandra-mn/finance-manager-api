<?php

namespace App\Data\Goals;

use App\Http\Requests\Goals\StoreGoalRequest;
use Illuminate\Support\Carbon;

final readonly class CreateGoalData
{
    public function __construct(
        public int $userId,
        public int $accountId,
        public string $name,
        public int $targetCents,
        public ?Carbon $targetDate,
        public string $color,
        public string $icon,
        public ?string $notes,
    ) {}

    public static function fromRequest(StoreGoalRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            accountId: (int) $request->integer('account_id'),
            name: $request->string('name')->toString(),
            targetCents: (int) $request->integer('target_cents'),
            targetDate: $request->filled('target_date') ? Carbon::parse($request->string('target_date')->toString()) : null,
            color: $request->filled('color') ? $request->string('color')->toString() : '#10b981',
            icon: $request->filled('icon') ? $request->string('icon')->toString() : 'PiggyBank',
            notes: $request->string('notes')->toString() ?: null,
        );
    }
}
