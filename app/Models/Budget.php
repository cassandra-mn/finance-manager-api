<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use HasFactory<BudgetFactory>
 */
class Budget extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount_cents',
        'reference_month',
        'reference_year',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'reference_month' => 'integer',
            'reference_year' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    #[Scope]
    public function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function forPeriod(Builder $query, int $month, int $year): Builder
    {
        return $query->where('reference_month', $month)->where('reference_year', $year);
    }
}
