<?php

namespace App\Actions\Accounts;

use App\Data\Accounts\UpdateAccountData;
use App\Models\Account;

final class UpdateAccountAction
{
    public function execute(Account $account, UpdateAccountData $data): Account
    {
        $account->fill(array_filter([
            'name' => $data->name,
            'type' => $data->type,
            'initial_balance_cents' => $data->initialBalanceCents,
            'color' => $data->color,
            'is_active' => $data->isActive,
        ], static fn (mixed $value): bool => $value !== null));

        $account->save();

        return $account;
    }
}
