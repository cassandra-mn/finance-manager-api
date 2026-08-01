<?php

namespace App\Models;

use App\Enum\StatementImportFormat;
use App\Traits\BelongsToUser;
use Database\Factories\StatementImportFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use HasFactory<StatementImportFactory>
 */
class StatementImport extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'format',
        'original_filename',
        'transactions_created',
        'transactions_skipped',
    ];

    protected function casts(): array
    {
        return [
            'format' => StatementImportFormat::class,
            'transactions_created' => 'integer',
            'transactions_skipped' => 'integer',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
}
