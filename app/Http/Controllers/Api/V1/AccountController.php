<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->service->listForUser($request->user()->id);

        return AccountResource::collection($accounts)->response();
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = $this->service->create($request);

        return (new AccountResource($account))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Account $account): JsonResponse
    {
        return (new AccountResource($account))->response();
    }

    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        $account = $this->service->update($account, $request);

        return (new AccountResource($account))->response();
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->service->delete($account);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
