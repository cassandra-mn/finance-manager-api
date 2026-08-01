<?php

namespace App\Models;

use App\Enum\BankConnectionStatus;
use App\Traits\BelongsToUser;
use Database\Factories\BankConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use HasFactory<BankConnectionFactory>
 */
class BankConnection extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'pluggy_item_id',
        'institution_id',
        'institution_name',
        'status',
        'last_synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => BankConnectionStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    #[Scope]
    public function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Conexões que ainda faz sentido tentar sincronizar: exclui estados que
     * exigem ação do usuário (login_error/waiting_user_input) ou que indicam
     * que o item já não existe mais do lado da Pluggy (deleted).
     */
    #[Scope]
    public function syncable(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            BankConnectionStatus::LOGIN_ERROR->value,
            BankConnectionStatus::WAITING_USER_INPUT->value,
            BankConnectionStatus::DELETED->value,
        ]);
    }
}
