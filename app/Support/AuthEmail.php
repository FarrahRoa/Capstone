<?php

namespace App\Support;

/**
 * Single place for auth identifier normalization (login, OTP, throttling).
 */
final class AuthEmail
{
    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }
}
