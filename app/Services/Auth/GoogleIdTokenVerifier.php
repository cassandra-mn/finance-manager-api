<?php

namespace App\Services\Auth;

use App\Data\Auth\GoogleIdTokenPayload;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifies Google Identity Services ID tokens without depending on the full
 * google/apiclient SDK: fetches Google's public JWKS (cached, since the keys
 * rotate infrequently) and validates the token's signature, issuer and
 * audience with firebase/php-jwt.
 */
final class GoogleIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function verify(string $idToken): GoogleIdTokenPayload
    {
        $clientId = (string) config('services.google.client_id');

        if ($clientId === '') {
            throw new RuntimeException('GOOGLE_CLIENT_ID não está configurado.');
        }

        $jwks = Cache::remember('google.id_token_certs', now()->addHours(6), function (): array {
            return Http::timeout(5)->get(self::CERTS_URL)->throw()->json();
        });

        $claims = JWT::decode($idToken, JWK::parseKeySet($jwks));

        if (! in_array($claims->iss ?? null, self::VALID_ISSUERS, true)) {
            throw new RuntimeException('Emissor do token do Google é inválido.');
        }

        if (($claims->aud ?? null) !== $clientId) {
            throw new RuntimeException('Audiência do token do Google é inválida.');
        }

        if (empty($claims->sub) || empty($claims->email)) {
            throw new RuntimeException('Token do Google sem os campos obrigatórios.');
        }

        return new GoogleIdTokenPayload(
            sub: (string) $claims->sub,
            email: (string) $claims->email,
            emailVerified: (bool) ($claims->email_verified ?? false),
            name: (string) ($claims->name ?? $claims->email),
            picture: isset($claims->picture) ? (string) $claims->picture : null,
        );
    }
}
