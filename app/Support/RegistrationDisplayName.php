<?php

namespace App\Support;

/**
 * Derives a non-empty display name for first-time user registration from the email address.
 * This app has no OAuth, directory, or IdP integration; the email local part is the only signal.
 */
final class RegistrationDisplayName
{
    public const FALLBACK = 'XU User';

    public static function needsEnrichment(?string $name): bool
    {
        return trim((string) $name) === self::FALLBACK;
    }

    public static function fromEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        $local = $at === false ? '' : substr($email, 0, $at);
        $local = trim($local);

        if ($local === '') {
            return self::FALLBACK;
        }

        // Student / service-style IDs (digits only)
        if (preg_match('/^\d+$/', $local)) {
            return self::FALLBACK;
        }

        $words = preg_split('/[._+\-]+/', $local, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            return self::FALLBACK;
        }

        $parts = [];
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $parts[] = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }

        if ($parts === []) {
            return self::FALLBACK;
        }

        $name = implode(' ', $parts);

        return $name !== '' && strlen($name) <= 255 ? $name : self::FALLBACK;
    }
}
