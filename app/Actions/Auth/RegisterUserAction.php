<?php

namespace App\Actions\Auth;

use App\Data\Auth\RegisterUserData;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

final class RegisterUserAction
{
    /** @return array{user: User, token: string} */
    public function execute(RegisterUserData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            event(new Registered($user));

            $token = $user->createToken('api')->plainTextToken;

            return ['user' => $user, 'token' => $token];
        });
    }
}
