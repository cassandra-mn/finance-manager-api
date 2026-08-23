<?php

namespace App\Services\Assistant\Commands;

use App\Models\User;
use App\Repositories\AccountRepository;

final readonly class CreateAccountCommand implements AssistantCommand
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        private AccountRepository $accountRepository,
        private string $summary,
        private array $payload,
    ) {}

    public function execute(User $user): array
    {
        $account = $this->accountRepository->create(array_merge(
            ['user_id' => $user->id],
            $this->payload,
        ));

        return ['kind' => 'account', 'summary' => $this->summary, 'id' => $account->id];
    }
}
