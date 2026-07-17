<?php

namespace App\Models;

use App\Enum\AccountType;
use App\Traits\BelongsToUser;
use Database\Factories\AccountFactory;
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
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance_cents' => 'integer',
            'is_active' => 'boolean',
        ];
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
}
