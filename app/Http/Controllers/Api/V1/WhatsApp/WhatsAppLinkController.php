<?php

namespace App\Http\Controllers\Api\V1\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsApp\WhatsAppLinkCodeResource;
use App\Services\WhatsAppLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppLinkController extends Controller
{
    public function __construct(
        private readonly WhatsAppLinkService $service,
    ) {}

    public function generateCode(Request $request): JsonResponse
    {
        $result = $this->service->generateLinkCode($request->user());

        return (new WhatsAppLinkCodeResource($result))->response();
    }

    public function unlink(Request $request): JsonResponse
    {
        $this->service->unlink($request->user());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
