<?php

namespace App\Services;

use App\Data\Auth\LoginUserData;
use App\Data\Auth\RegisterUserData;
use App\Exceptions\ServiceException;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AuthService
{
    private const TOKEN_NAME = 'api';

    public function __construct(
        private readonly UserRepository $repository,
    ) {}

    /** @return array{user: User, token: string} */
    public function register(RegisterRequest $request): array
    {
        $data = RegisterUserData::fromRequest($request);

        try {
            return DB::transaction(function () use ($data): array {
                $user = $this->repository->create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'password' => $data->password,
                ]);

                event(new Registered($user));

                $token = $this->repository->issueToken($user, self::TOKEN_NAME);

                return ['user' => $user, 'token' => $token];
            });
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'users_email_unique')) {
                Log::error('finance.auth.register_failed', [
                    'email' => $data->email,
                    'message' => $e->getMessage(),
                ]);

                throw new ServiceException('Não foi possível concluir o registro.', previous: $e);
            }

            throw ValidationException::withMessages([
                'email' => ['Este e-mail já está em uso.'],
            ]);
        } catch (Throwable $e) {
            Log::error('finance.auth.register_failed', [
                'email' => $data->email,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível concluir o registro.', previous: $e);
        }
    }

    /** @return array{user: User, token: string} */
    public function login(LoginRequest $request): array
    {
        $data = LoginUserData::fromRequest($request);

        try {
            $user = $this->repository->findByEmail($data->email);
        } catch (Throwable $e) {
            Log::error('finance.auth.login_failed', ['message' => $e->getMessage()]);

            throw new ServiceException('Não foi possível autenticar no momento.', previous: $e);
        }

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas são inválidas.'],
            ]);
        }

        try {
            $token = $this->repository->issueToken($user, self::TOKEN_NAME);
        } catch (Throwable $e) {
            Log::error('finance.auth.token_issue_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível gerar o token de acesso.', previous: $e);
        }

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        try {
            $this->repository->revokeCurrentToken($user);
        } catch (Throwable $e) {
            Log::error('finance.auth.logout_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível encerrar a sessão.', previous: $e);
        }
    }
}
