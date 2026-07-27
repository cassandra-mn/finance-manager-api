<?php

namespace App\Repositories;

use App\Models\User;

final class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return User::query()->where('google_id', $googleId)->first();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function linkGoogleId(User $user, string $googleId): void
    {
        $user->forceFill(['google_id' => $googleId])->save();
    }

    public function issueToken(User $user, string $name): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
