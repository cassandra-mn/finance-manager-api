<?php

namespace App\Enum;

enum BankConnectionStatus: string
{
    case UPDATING = 'updating';
    case UPDATED = 'updated';
    case LOGIN_ERROR = 'login_error';
    case OUTDATED = 'outdated';
    case WAITING_USER_INPUT = 'waiting_user_input';
    case ERROR = 'error';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::UPDATING => 'Sincronizando',
            self::UPDATED => 'Atualizado',
            self::LOGIN_ERROR => 'Erro de login',
            self::OUTDATED => 'Desatualizado',
            self::WAITING_USER_INPUT => 'Aguardando ação do usuário',
            self::ERROR => 'Erro',
            self::DELETED => 'Removido no banco',
        };
    }
}
