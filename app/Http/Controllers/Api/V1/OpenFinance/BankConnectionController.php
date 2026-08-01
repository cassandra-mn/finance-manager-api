<?php

namespace App\Http\Controllers\Api\V1\OpenFinance;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenFinance\CreateConnectTokenRequest;
use App\Http\Requests\OpenFinance\StoreBankConnectionRequest;
use App\Http\Resources\OpenFinance\BankConnectionResource;
use App\Models\BankConnection;
use App\Services\OpenFinance\BankConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BankConnectionController extends Controller
{
    public function __construct(
        private readonly BankConnectionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $connections = $this->service->listForUser($request->user()->id);

        return BankConnectionResource::collection($connections)->response();
    }

    public function connectToken(CreateConnectTokenRequest $request): JsonResponse
    {
        $token = $this->service->createConnectToken($request);

        return response()->json(['access_token' => $token]);
    }

    public function store(StoreBankConnectionRequest $request): JsonResponse
    {
        $connection = $this->service->connect($request);

        return (new BankConnectionResource($connection))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BankConnection $connection): JsonResponse
    {
        return (new BankConnectionResource($connection))->response();
    }

    public function resync(BankConnection $connection): JsonResponse
    {
        $connection = $this->service->resync($connection);

        return (new BankConnectionResource($connection))->response();
    }

    public function destroy(BankConnection $connection): JsonResponse
    {
        $this->service->disconnect($connection);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
