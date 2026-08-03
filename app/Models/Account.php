<?php

namespace App\Models;

use App\Enum\AccountType;
use App\Traits\BelongsToUser;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use HasFactory<AccountFactory>
 */
class Account extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'initial_balance_cents',
        'credit_limit_cents',
        'invoice_due_day',
        'invoice_closing_day',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance_cents' => 'integer',
            'credit_limit_cents' => 'integer',
            'invoice_due_day' => 'integer',
            'invoice_closing_day' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Dia de fechamento efetivo da fatura: usa invoice_closing_day quando
     * definido, senão calcula a partir de invoice_due_day menos o offset
     * padrão (config('finance.credit_cards.default_closing_offset_days')).
     * É uma aproximação de dia-do-mês (1-28) — o cálculo de datas reais do
     * ciclo fica em CreditCardCycleResolver.
     */
    protected function effectiveInvoiceClosingDay(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->invoice_closing_day !== null) {
                return $this->invoice_closing_day;
            }

            if ($this->invoice_due_day === null) {
                return null;
            }

            $offset = (int) config('finance.credit_cards.default_closing_offset_days');
            $closingDay = $this->invoice_due_day - $offset;

            while ($closingDay < 1) {
                $closingDay += 30;
            }

            return min($closingDay, 28);
        });
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Recurrence, $this> */
    public function recurrences(): HasMany
    {
        return $this->hasMany(Recurrence::class);
    }

    /** @return HasMany<StatementImport, $this> */
    public function statementImports(): HasMany
    {
        return $this->hasMany(StatementImport::class);
    }

    /** @return HasMany<TransactionGroup, $this> */
    public function transactionGroups(): HasMany
    {
        return $this->hasMany(TransactionGroup::class);
    }

    #[Scope]
    public function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
