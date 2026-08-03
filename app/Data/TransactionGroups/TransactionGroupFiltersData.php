<?php

namespace App\Data\TransactionGroups;

use App\Enum\TransactionGroupStatus;
use App\Enum\TransactionGroupType;
use App\Http\Requests\TransactionGroups\ListTransactionGroupsRequest;

final readonly class TransactionGroupFiltersData
{
    public function __construct(
        public ?int $accountId = null,
        public ?TransactionGroupType $type = null,
        public ?TransactionGroupStatus $status = null,
    ) {}

    public static function fromRequest(ListTransactionGroupsRequest $request): self
    {
        return new self(
            accountId: $request->filled('account_id') ? (int) $request->integer('account_id') : null,
            type: $request->filled('type') ? TransactionGroupType::from($request->string('type')->toString()) : null,
            status: $request->filled('status') ? TransactionGroupStatus::from($request->string('status')->toString()) : null,
        );
    }
}
