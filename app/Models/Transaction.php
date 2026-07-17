<?php

namespace App\Models;

use App\Enum\TransactionEntryType;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Traits\BelongsToUser;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'type',
        'entry_type',
        'status',
        'description',
        'amount_cents',
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
            'amount_cents' => 'integer',
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
}
