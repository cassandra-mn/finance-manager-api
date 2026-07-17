<?php

namespace App\Models;

use App\Enum\TransactionType;
use App\Traits\BelongsToUser;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
