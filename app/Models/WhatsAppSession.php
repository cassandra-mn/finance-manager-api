<?php

namespace App\Models;

use App\Enum\WhatsAppSessionState;
use Database\Factories\WhatsAppSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado da conversa do bot do WhatsApp para um número de telefone. Não usa
 * BelongsToUser: é encontrada pelo número (antes mesmo de haver um usuário
 * vinculado), não por escopo de usuário autenticado.
 *
 * @use HasFactory<WhatsAppSessionFactory>
 */
class WhatsAppSession extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'phone_number',
        'user_id',
        'state',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'state' => WhatsAppSessionState::class,
            'context' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isStale(int $ttlMinutes): bool
    {
        return $this->state !== WhatsAppSessionState::IDLE
            && $this->updated_at !== null
            && $this->updated_at->lt(now()->subMinutes($ttlMinutes));
    }
}
