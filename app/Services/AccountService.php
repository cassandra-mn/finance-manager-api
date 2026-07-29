<?php

namespace App\Services;

use App\Data\Accounts\CreateAccountData;
use App\Data\Accounts\UpdateAccountData;
use App\Exceptions\ServiceException;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AccountService
{
    public function __construct(
        private readonly AccountRepository $repository,
    ) {}

    /** @return Collection<int, Account> */
    public function listForUser(int $userId): Collection
    {
        return $this->repository->listForUser($userId);
    }

    public function create(StoreAccountRequest $request): Account
    {
        $data = CreateAccountData::fromRequest($request, $request->user()->id);

        try {
            return $this->repository->create([
                'user_id' => $data->userId,
                'name' => $data->name,
                'type' => $data->type,
                'initial_balance_cents' => $data->initialBalanceCents,
                'credit_limit_cents' => $data->creditLimitCents,
                'color' => $data->color,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.accounts.create_failed', [
                'user_id' => $data->userId,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar a conta.', previous: $e);
        }
    }

    public function update(Account $account, UpdateAccountRequest $request): Account
    {
        $data = UpdateAccountData::fromRequest($request);

        try {
            return $this->repository->update($account, array_filter([
                'name' => $data->name,
                'type' => $data->type,
                'initial_balance_cents' => $data->initialBalanceCents,
                'credit_limit_cents' => $data->creditLimitCents,
                'color' => $data->color,
                'is_active' => $data->isActive,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (Throwable $e) {
            Log::error('finance.accounts.update_failed', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível atualizar a conta.', previous: $e);
        }
    }

    public function delete(Account $account): void
    {
        try {
            $this->repository->delete($account);
        } catch (Throwable $e) {
            Log::error('finance.accounts.delete_failed', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível excluir a conta.', previous: $e);
        }
    }
}
