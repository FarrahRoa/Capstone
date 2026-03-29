<?php

namespace App\Contracts;

/**
 * Verifies a Google Sign-In / Identity Services JWT (credential) and returns token claims.
 * Does not use Gmail or restricted scopes — ID tokens are issued for openid profile email.
 */
interface GoogleCredentialVerifier
{
    /**
     * @return array<string, mixed>|null Decoded JWT claims, or null if invalid.
     */
    public function verify(string $credential): ?array;
}
