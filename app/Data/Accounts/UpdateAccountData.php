<?php

namespace App\Data\Accounts;

use App\Enum\AccountType;
use App\Http\Requests\Accounts\UpdateAccountRequest;

final readonly class UpdateAccountData
{
    public function __construct(
        public ?string $name,
        public ?AccountType $type,
        public ?int $initialBalanceCents,
        public ?string $color,
        public ?bool $isActive,
    ) {}

    public static function fromRequest(UpdateAccountRequest $request): self
    {
        return new self(
            name: $request->filled('name') ? $request->string('name')->toString() : null,
            type: $request->filled('type') ? AccountType::from($request->string('type')->toString()) : null,
            initialBalanceCents: $request->filled('initial_balance_cents') ? (int) $request->integer('initial_balance_cents') : null,
            color: $request->filled('color') ? $request->string('color')->toString() : null,
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
        );
    }
}
