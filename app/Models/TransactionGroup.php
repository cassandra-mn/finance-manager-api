<?php

namespace App\Models;

use App\Enum\TransactionGroupDisplayStatus;
use App\Enum\TransactionGroupStatus;
use App\Enum\TransactionGroupType;
use App\Traits\BelongsToUser;
use Database\Factories\TransactionGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @use HasFactory<TransactionGroupFactory>
 */
class TransactionGroup extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'type',
        'name',
        'reference_month',
        'closing_date',
        'due_date',
        'status',
        'payment_account_id',
        'payment_transaction_id',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionGroupType::class,
            'status' => TransactionGroupStatus::class,
            'reference_month' => 'date',
            'closing_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_transaction_id');
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    protected function totalCents(): Attribute
    {
        return Attribute::get(function (): int {
            if ($this->relationLoaded('transactions')) {
                return (int) $this->transactions->sum('amount_cents');
            }

            return (int) $this->transactions()->sum('amount_cents');
        });
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->name !== null) {
                return $this->name;
            }

            $accountName = $this->relationLoaded('account') ? $this->account?->name : null;
            $month = $this->reference_month?->translatedFormat('F/Y') ?? '';

            return trim(sprintf('Fatura %s - %s', $accountName ?? '', $month));
        });
    }

    public function isOpen(): bool
    {
        return $this->status === TransactionGroupStatus::OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === TransactionGroupStatus::CLOSED;
    }

    public function isPaid(): bool
    {
        return $this->status === TransactionGroupStatus::PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === TransactionGroupStatus::CLOSED
            && $this->due_date !== null
            && $this->due_date->lt(Carbon::today());
    }

    protected function displayStatus(): Attribute
    {
        return Attribute::get(fn (): TransactionGroupDisplayStatus => match (true) {
            $this->status === TransactionGroupStatus::PAID => TransactionGroupDisplayStatus::PAID,
            $this->status === TransactionGroupStatus::OPEN => TransactionGroupDisplayStatus::OPEN,
            $this->isOverdue() => TransactionGroupDisplayStatus::OVERDUE,
            default => TransactionGroupDisplayStatus::PENDING,
        });
    }

    #[Scope]
    public function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function forAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    #[Scope]
    public function open(Builder $query): Builder
    {
        return $query->where('status', TransactionGroupStatus::OPEN->value);
    }

    #[Scope]
    public function closingBy(Builder $query, Carbon $date): Builder
    {
        return $query->open()->whereDate('closing_date', '<=', $date->toDateString());
    }
}
