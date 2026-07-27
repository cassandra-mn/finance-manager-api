<?php

namespace App\Data\Auth;

use App\Http\Requests\Auth\GoogleLoginRequest;

final readonly class GoogleLoginData
{
    public function __construct(
        public string $credential,
    ) {}

    public static function fromRequest(GoogleLoginRequest $request): self
    {
        return new self(
            credential: $request->string('credential')->toString(),
        );
    }
}
