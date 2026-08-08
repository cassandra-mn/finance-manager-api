<?php

namespace App\Data\TransactionGroups;

use App\Http\Requests\TransactionGroups\StoreTransactionGroupRequest;
use Illuminate\Support\Carbon;

final readonly class CreateTransactionGroupData
{
    public function __construct(
        public int $accountId,
        public Carbon $referenceMonth,
    ) {}

    public static function fromRequest(StoreTransactionGroupRequest $request): self
    {
        return new self(
            accountId: (int) $request->integer('account_id'),
            referenceMonth: Carbon::createFromFormat('Y-m', $request->string('reference_month')->toString())->startOfMonth(),
        );
    }
}
