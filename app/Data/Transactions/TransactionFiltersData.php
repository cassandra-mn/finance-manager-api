<?php

namespace App\Data\Transactions;

use App\Constants\Pagination;
use App\Enum\TransactionDisplayStatus;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionPeriod;
use App\Enum\TransactionType;
use App\Http\Requests\Transactions\ListTransactionsRequest;
use Illuminate\Support\Carbon;

final readonly class TransactionFiltersData
{
    public function __construct(
        public ?int $accountId = null,
        public ?int $categoryId = null,
        public ?TransactionType $type = null,
        public ?TransactionEntryType $entryType = null,
        public ?TransactionDisplayStatus $status = null,
        public ?TransactionPeriod $period = null,
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public ?string $search = null,
        public int $perPage = Pagination::DEFAULT_PER_PAGE,
    ) {}

    public static function fromRequest(ListTransactionsRequest $request): self
    {
        return new self(
            accountId: $request->filled('account_id') ? (int) $request->integer('account_id') : null,
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            type: $request->filled('type') ? TransactionType::from($request->string('type')->toString()) : null,
            entryType: $request->filled('entry_type') ? TransactionEntryType::from($request->string('entry_type')->toString()) : null,
            status: $request->filled('status') ? TransactionDisplayStatus::from($request->string('status')->toString()) : null,
            period: $request->filled('period') ? TransactionPeriod::from($request->string('period')->toString()) : null,
            from: $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : null,
            to: $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : null,
            search: $request->string('search')->toString() ?: null,
            perPage: $request->filled('per_page')
                ? min((int) $request->integer('per_page'), Pagination::MAX_PER_PAGE)
                : Pagination::DEFAULT_PER_PAGE,
        );
    }
}
