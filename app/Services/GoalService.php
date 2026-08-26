<?php

namespace App\Services;

use App\Data\Goals\CreateGoalData;
use App\Data\Goals\UpdateGoalData;
use App\Exceptions\ServiceException;
use App\Http\Requests\Goals\ListGoalsRequest;
use App\Http\Requests\Goals\StoreGoalRequest;
use App\Http\Requests\Goals\UpdateGoalRequest;
use App\Models\Goal;
use App\Repositories\GoalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Meta de economia: o progresso nunca é um valor guardado, é sempre o saldo
 * atual da conta vinculada (AccountBalanceService::calculateCurrentBalance)
 * — o mesmo princípio de "nunca armazenar o que dá pra derivar" já usado no
 * resto do app. Qualquer depósito real na conta já avança a meta.
 */
final class GoalService
{
    public function __construct(
        private readonly GoalRepository $repository,
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    /** @return Collection<int, Goal> */
    public function listForUser(ListGoalsRequest $request): Collection
    {
        return $this->repository->listForUser($request->user()->id);
    }

    public function create(StoreGoalRequest $request): Goal
    {
        $data = CreateGoalData::fromRequest($request, $request->user()->id);

        try {
            return $this->repository->create([
                'user_id' => $data->userId,
                'account_id' => $data->accountId,
                'name' => $data->name,
                'target_cents' => $data->targetCents,
                'target_date' => $data->targetDate,
                'color' => $data->color,
                'icon' => $data->icon,
                'notes' => $data->notes,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.goals.create_failed', [
                'user_id' => $data->userId,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar a meta.', previous: $e);
        }
    }

    public function update(Goal $goal, UpdateGoalRequest $request): Goal
    {
        $data = UpdateGoalData::fromRequest($request);

        $attributes = array_filter([
            'account_id' => $data->accountId,
            'name' => $data->name,
            'target_cents' => $data->targetCents,
            'color' => $data->color,
            'icon' => $data->icon,
        ], static fn (mixed $value): bool => $value !== null);

        if ($data->targetDate !== null) {
            $attributes['target_date'] = $data->targetDate;
        } elseif ($data->clearTargetDate) {
            $attributes['target_date'] = null;
        }

        if ($data->notesProvided) {
            $attributes['notes'] = $data->notes;
        }

        try {
            return $this->repository->update($goal, $attributes);
        } catch (Throwable $e) {
            Log::error('finance.goals.update_failed', [
                'goal_id' => $goal->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível atualizar a meta.', previous: $e);
        }
    }

    public function delete(Goal $goal): void
    {
        try {
            $this->repository->delete($goal);
        } catch (Throwable $e) {
            Log::error('finance.goals.delete_failed', [
                'goal_id' => $goal->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível excluir a meta.', previous: $e);
        }
    }

    /**
     * @param  Collection<int, Goal>  $goals
     * @return list<array{goal: Goal, current_cents: int}>
     */
    public function withProgress(Collection $goals): array
    {
        return $goals->map(fn (Goal $goal): array => [
            'goal' => $goal,
            'current_cents' => $this->accountBalanceService->calculateCurrentBalance($goal->account)->cents,
        ])->all();
    }

    /** @return array{goal: Goal, current_cents: int} */
    public function singleWithProgress(Goal $goal): array
    {
        return [
            'goal' => $goal,
            'current_cents' => $this->accountBalanceService->calculateCurrentBalance($goal->account)->cents,
        ];
    }
}
