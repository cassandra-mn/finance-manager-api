<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'test-client-id.apps.googleusercontent.com';

    private const KEY_ID = 'test-key-1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => self::CLIENT_ID]);
    }

    public function test_user_can_login_with_google_and_a_new_account_is_created(): void
    {
        [$privateKey, $jwks] = $this->generateKeyPairAndJwks();
        Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response($jwks)]);

        $idToken = $this->issueIdToken($privateKey, [
            'sub' => 'google-user-123',
            'email' => 'ana@example.com',
            'name' => 'Ana Google',
        ]);

        $response = $this->postJson('/api/v1/auth/google', ['credential' => $idToken]);

        // The auth resource wraps a freshly-created User, so Laravel's
        // JsonResource auto-detects wasRecentlyCreated and responds 201 —
        // same as the regular /auth/register endpoint.
        $response->assertCreated()->assertJsonStructure(['user', 'token', 'token_type']);
        $this->assertDatabaseHas('users', [
            'email' => 'ana@example.com',
            'google_id' => 'google-user-123',
            'name' => 'Ana Google',
        ]);
    }

    public function test_existing_account_with_matching_email_is_linked_to_google(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        [$privateKey, $jwks] = $this->generateKeyPairAndJwks();
        Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response($jwks)]);

        $idToken = $this->issueIdToken($privateKey, [
            'sub' => 'google-user-456',
            'email' => 'ana@example.com',
            'name' => 'Ana Google',
        ]);

        $response = $this->postJson('/api/v1/auth/google', ['credential' => $idToken]);

        $response->assertOk();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'google_id' => 'google-user-456']);
    }

    public function test_login_fails_for_a_token_with_an_invalid_signature(): void
    {
        [, $jwks] = $this->generateKeyPairAndJwks();
        [$otherPrivateKey] = $this->generateKeyPairAndJwks();
        Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response($jwks)]);

        // Signed with a different private key than the one published in the
        // trusted JWKS above, but claiming the same kid — a forged token.
        $idToken = $this->issueIdToken($otherPrivateKey, [
            'sub' => 'google-user-789',
            'email' => 'eve@example.com',
            'name' => 'Eve',
        ]);

        $response = $this->postJson('/api/v1/auth/google', ['credential' => $idToken]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['credential']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_fails_for_a_token_issued_to_a_different_audience(): void
    {
        [$privateKey, $jwks] = $this->generateKeyPairAndJwks();
        Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response($jwks)]);

        $idToken = $this->issueIdToken($privateKey, [
            'sub' => 'google-user-999',
            'email' => 'mallory@example.com',
            'name' => 'Mallory',
            'aud' => 'someone-elses-client-id',
        ]);

        $response = $this->postJson('/api/v1/auth/google', ['credential' => $idToken]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['credential']);
    }

    /** @return array{0: string, 1: array} private key PEM, JWKS response body */
    private function generateKeyPairAndJwks(): array
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $privateKeyPem);
        $details = openssl_pkey_get_details($key);

        $jwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::KEY_ID,
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];

        return [$privateKeyPem, ['keys' => [$jwk]]];
    }

    private function issueIdToken(string $privateKeyPem, array $claims): string
    {
        $payload = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'email_verified' => true,
            'iat' => now()->timestamp,
            'exp' => now()->addHour()->timestamp,
        ], $claims);

        return JWT::encode($payload, $privateKeyPem, 'RS256', self::KEY_ID);
    }
}
