<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatementImports\StoreStatementImportRequest;
use App\Http\Resources\StatementImports\StatementImportResource;
use App\Models\Account;
use App\Services\StatementImports\StatementImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StatementImportController extends Controller
{
    public function __construct(
        private readonly StatementImportService $service,
    ) {}

    public function index(Account $account): JsonResponse
    {
        $imports = $this->service->listForAccount($account);

        return StatementImportResource::collection($imports)->response();
    }

    public function store(StoreStatementImportRequest $request, Account $account): JsonResponse
    {
        $import = $this->service->import($account, $request);

        return (new StatementImportResource($import))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
