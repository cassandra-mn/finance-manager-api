<?php

namespace App\Actions\Accounts;

use App\Data\Accounts\CreateAccountData;
use App\Models\Account;

final class CreateAccountAction
{
    public function execute(CreateAccountData $data): Account
    {
        return Account::create([
            'user_id' => $data->userId,
            'name' => $data->name,
            'type' => $data->type,
            'initial_balance_cents' => $data->initialBalanceCents,
            'color' => $data->color,
        ]);
    }
}
