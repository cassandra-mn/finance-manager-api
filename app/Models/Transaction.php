<?php

namespace App\Models;

use App\Enum\TransactionDisplayStatus;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionOrigin;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Traits\BelongsToUser;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @use HasFactory<TransactionFactory>
 */
class Transaction extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'recurrence_id',
        'transaction_group_id',
        'external_id',
        'origin',
        'type',
        'entry_type',
        'status',
        'description',
        'amount_cents',
        'paid_amount_cents',
        'due_date',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'entry_type' => TransactionEntryType::class,
            'status' => TransactionStatus::class,
            'origin' => TransactionOrigin::class,
            'amount_cents' => 'integer',
            'paid_amount_cents' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Recurrence, $this> */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }

    /** @return BelongsTo<TransactionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TransactionGroup::class, 'transaction_group_id');
    }

    public function isGrouped(): bool
    {
        return $this->transaction_group_id !== null;
    }

    public function isOverdue(): bool
    {
        return $this->status === TransactionStatus::PENDING
            && $this->due_date !== null
            && $this->due_date->lt(Carbon::today());
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === TransactionStatus::PAID;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === TransactionStatus::PARTIALLY_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === TransactionStatus::CANCELLED;
    }

    protected function displayStatus(): Attribute
    {
        return Attribute::get(fn (): TransactionDisplayStatus => match (true) {
            $this->status === TransactionStatus::CANCELLED => TransactionDisplayStatus::CANCELLED,
            $this->status === TransactionStatus::PAID => TransactionDisplayStatus::PAID,
            $this->status === TransactionStatus::PARTIALLY_PAID => TransactionDisplayStatus::PARTIALLY_PAID,
            $this->isOverdue() => TransactionDisplayStatus::OVERDUE,
            default => TransactionDisplayStatus::PENDING,
        });
    }

    /**
     * Quando a transação pertence a um agrupador (ex.: fatura de cartão), ela
     * não tem status de pago próprio — quem está "pago" é a fatura. Este
     * accessor delega ao status da fatura nesse caso; fora disso, é igual a
     * displayStatus.
     */
    protected function effectiveDisplayStatus(): Attribute
    {
        return Attribute::get(function (): TransactionDisplayStatus {
            if (! $this->isGrouped()) {
                return $this->display_status;
            }

            $group = $this->group;

            if ($group === null) {
                return $this->display_status;
            }

            return match (true) {
                $group->isPaid() => TransactionDisplayStatus::PAID,
                $group->isPartiallyPaid() => TransactionDisplayStatus::PARTIALLY_PAID,
                $group->isOverdue() => TransactionDisplayStatus::OVERDUE,
                default => TransactionDisplayStatus::PENDING,
            };
        });
    }

    #[Scope]
    public function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function forRecurrence(Builder $query, int $recurrenceId): Builder
    {
        return $query->where('recurrence_id', $recurrenceId);
    }

    #[Scope]
    public function dueOn(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('due_date', $date->toDateString());
    }

    #[Scope]
    public function pending(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::PENDING->value);
    }

    #[Scope]
    public function paid(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::PAID->value);
    }

    #[Scope]
    public function partiallyPaid(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::PARTIALLY_PAID->value);
    }

    #[Scope]
    public function cancelled(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::CANCELLED->value);
    }

    #[Scope]
    public function overdue(Builder $query): Builder
    {
        return $query->pending()->whereDate('due_date', '<', Carbon::today());
    }
}
