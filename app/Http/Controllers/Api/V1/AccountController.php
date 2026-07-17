<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Accounts\CreateAccountAction;
use App\Actions\Accounts\DeleteAccountAction;
use App\Actions\Accounts\UpdateAccountAction;
use App\Data\Accounts\CreateAccountData;
use App\Data\Accounts\UpdateAccountData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function index(Request $request, AccountRepository $repository): JsonResponse
    {
        $accounts = $repository->listForUser($request->user()->id);

        return AccountResource::collection($accounts)->response();
    }

    public function store(StoreAccountRequest $request, CreateAccountAction $action): JsonResponse
    {
        $account = $action->execute(CreateAccountData::fromRequest($request, $request->user()->id));

        return (new AccountResource($account))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Account $account): JsonResponse
    {
        return (new AccountResource($account))->response();
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccountAction $action): JsonResponse
    {
        $account = $action->execute($account, UpdateAccountData::fromRequest($request));

        return (new AccountResource($account))->response();
    }

    public function destroy(Account $account, DeleteAccountAction $action): JsonResponse
    {
        $action->execute($account);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
