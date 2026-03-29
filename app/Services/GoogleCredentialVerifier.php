<?php

namespace App\Services;

use App\Contracts\GoogleCredentialVerifier as GoogleCredentialVerifierContract;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GoogleCredentialVerifier implements GoogleCredentialVerifierContract
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const JWKS_CACHE_KEY = 'google_oauth2_jwks_json';

    private const JWKS_TTL_SECONDS = 3600;

    public function verify(string $credential): ?array
    {
        $credential = trim($credential);
        if ($credential === '') {
            return null;
        }

        $clientId = config('services.google.client_id');
        if (! is_string($clientId) || $clientId === '') {
            Log::warning('Google profile enrichment: GOOGLE_CLIENT_ID is not set.');

            return null;
        }

        $jwks = $this->fetchJwks();
        if ($jwks === null) {
            return null;
        }

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($credential, $keys);
        } catch (Throwable $e) {
            Log::debug('Google ID token verification failed', ['message' => $e->getMessage()]);

            return null;
        }

        $payload = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $iss = $payload['iss'] ?? '';
        if (! in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            return null;
        }

        $aud = $payload['aud'] ?? null;
        if ($aud !== $clientId) {
            if (! (is_array($aud) && in_array($clientId, $aud, true))) {
                return null;
            }
        }

        $ev = $payload['email_verified'] ?? null;
        if ($ev !== true && $ev !== 'true') {
            return null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchJwks(): ?array
    {
        try {
            return Cache::remember(self::JWKS_CACHE_KEY, self::JWKS_TTL_SECONDS, function () {
                $response = Http::timeout(10)->get(self::JWKS_URL);
                if (! $response->successful()) {
                    return null;
                }

                $json = $response->json();
                if (! is_array($json) || ! isset($json['keys'])) {
                    return null;
                }

                return $json;
            });
        } catch (Throwable) {
            return null;
        }
    }
}
