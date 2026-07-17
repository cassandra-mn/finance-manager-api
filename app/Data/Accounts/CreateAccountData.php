<?php

namespace App\Data\Accounts;

use App\Enum\AccountType;
use App\Http\Requests\Accounts\StoreAccountRequest;

final readonly class CreateAccountData
{
    public function __construct(
        public int $userId,
        public string $name,
        public AccountType $type,
        public int $initialBalanceCents,
        public ?string $color,
    ) {}

    public static function fromRequest(StoreAccountRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            name: $request->string('name')->toString(),
            type: AccountType::from($request->string('type')->toString()),
            initialBalanceCents: (int) $request->integer('initial_balance_cents'),
            color: $request->string('color')->toString() ?: null,
        );
    }
}
