<?php

namespace App\Models;

use App\Enum\TransactionType;
use App\Traits\BelongsToUser;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use HasFactory<CategoryFactory>
 */
class Category extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'color',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
        ];
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    #[Scope]
    public function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function ofType(Builder $query, TransactionType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
