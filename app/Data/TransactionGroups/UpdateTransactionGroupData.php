<?php

namespace App\Data\TransactionGroups;

use App\Http\Requests\TransactionGroups\UpdateTransactionGroupRequest;

final readonly class UpdateTransactionGroupData
{
    public function __construct(
        public ?string $name,
        public ?string $notes,
    ) {}

    public static function fromRequest(UpdateTransactionGroupRequest $request): self
    {
        return new self(
            name: $request->filled('name') ? $request->string('name')->toString() : null,
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );
    }
}
