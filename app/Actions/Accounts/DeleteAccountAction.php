<?php

namespace App\Actions\Accounts;

use App\Models\Account;

final class DeleteAccountAction
{
    public function execute(Account $account): void
    {
        $account->delete();
    }
}
