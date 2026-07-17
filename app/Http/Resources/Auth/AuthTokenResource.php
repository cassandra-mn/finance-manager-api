<?php

namespace App\Http\Resources\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    public function __construct(
        private readonly User $authUser,
        private readonly string $token,
    ) {
        parent::__construct($authUser);
    }

    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->authUser),
            'token' => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
