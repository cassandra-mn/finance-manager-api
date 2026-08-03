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
        public ?int $creditLimitCents,
        public ?int $invoiceDueDay,
        public ?int $invoiceClosingDay,
        public ?string $color,
    ) {}

    public static function fromRequest(StoreAccountRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            name: $request->string('name')->toString(),
            type: AccountType::from($request->string('type')->toString()),
            initialBalanceCents: (int) $request->integer('initial_balance_cents'),
            creditLimitCents: $request->filled('credit_limit_cents') ? (int) $request->integer('credit_limit_cents') : null,
            invoiceDueDay: $request->filled('invoice_due_day') ? (int) $request->integer('invoice_due_day') : null,
            invoiceClosingDay: $request->filled('invoice_closing_day') ? (int) $request->integer('invoice_closing_day') : null,
            color: $request->string('color')->toString() ?: null,
        );
    }
}
