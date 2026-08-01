<?php

namespace App\Data\OpenFinance;

use App\Http\Requests\OpenFinance\StoreBankConnectionRequest;

final readonly class CreateBankConnectionData
{
    public function __construct(
        public int $userId,
        public string $pluggyItemId,
    ) {}

    public static function fromRequest(StoreBankConnectionRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            pluggyItemId: $request->string('item_id')->toString(),
        );
    }
}
