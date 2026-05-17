<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks failed OTP verifications and resend requests per email (1-hour lockout after 5 attempts).
 */
final class OtpAttemptLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 3600;

    public const LOCKOUT_MESSAGE = 'Too many attempts. Please try again in 1 hour.';

    public static function cacheKey(string $email): string
    {
        return 'otp_attempts_'.AuthEmail::normalize($email);
    }

    public static function attempts(string $email): int
    {
        return (int) Cache::get(self::cacheKey($email), 0);
    }

    public static function isLockedOut(string $email): bool
    {
        return self::attempts($email) >= self::MAX_ATTEMPTS;
    }

    public static function recordAttempt(string $email): int
    {
        $key = self::cacheKey($email);
        $attempts = self::attempts($email) + 1;
        Cache::put($key, $attempts, self::LOCKOUT_SECONDS);

        return $attempts;
    }

    public static function clear(string $email): void
    {
        Cache::forget(self::cacheKey($email));
    }
}
