<?php

namespace App\Data\Auth;

final readonly class GoogleIdTokenPayload
{
    public function __construct(
        public string $sub,
        public string $email,
        public bool $emailVerified,
        public string $name,
        public ?string $picture,
    ) {}
}
