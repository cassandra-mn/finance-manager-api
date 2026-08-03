<?php

namespace App\Data\TransactionGroups;

use App\Http\Requests\TransactionGroups\PayTransactionGroupRequest;
use Illuminate\Support\Carbon;

final readonly class PayTransactionGroupData
{
    public function __construct(
        public int $paymentAccountId,
        public Carbon $paidAt,
    ) {}

    public static function fromRequest(PayTransactionGroupRequest $request): self
    {
        return new self(
            paymentAccountId: (int) $request->integer('payment_account_id'),
            paidAt: $request->filled('paid_at') ? Carbon::parse($request->string('paid_at')->toString()) : Carbon::now(),
        );
    }
}
