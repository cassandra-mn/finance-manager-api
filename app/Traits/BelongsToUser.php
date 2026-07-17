<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Garante que registros financeiros nunca vazem entre usuários: preenche
 * user_id automaticamente na criação e aplica um global scope que restringe
 * toda query ao usuário autenticado. Um registro de outro usuário não "existe"
 * para as queries — route model binding resulta em 404, não 403.
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::creating(function ($model): void {
            if (! $model->user_id && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });

        static::addGlobalScope('belongsToUser', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable().'.user_id', Auth::id());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
