<?php

namespace App\Support;

use App\Models\Space;
use App\Models\User;

/**
 * Per-reservation duration caps by account type and space category.
 * AVR and Lobby are exempt (multi-day range bookings allowed).
 */
final class ReservationDurationPolicy
{
    public const STUDENT_MAX_MINUTES = 120;

    public const EMPLOYEE_LIBRARY_MAX_MINUTES = 180;

    /**
     * Library-style spaces where students (2h) and employees (3h) caps apply.
     */
    public static function spaceAppliesLibraryDurationCap(Space $space): bool
    {
        if (in_array((string) $space->type, [Space::TYPE_AVR, Space::TYPE_LOBBY], true)) {
            return false;
        }

        return in_array((string) $space->type, [
            Space::TYPE_CONFAB,
            Space::TYPE_MEDICAL_CONFAB,
            Space::TYPE_BOARDROOM,
            Space::TYPE_LECTURE,
        ], true);
    }

    /**
     * Maximum duration in minutes, or null when no cap applies.
     */
    public static function maxMinutesFor(User $user, Space $space): ?int
    {
        if ($user->isAdmin()) {
            return null;
        }

        if (! self::spaceAppliesLibraryDurationCap($space)) {
            return null;
        }

        $userType = $user->user_type ?? User::getUserTypeFromEmail((string) $user->email);

        if ($userType === User::USER_TYPE_STUDENT) {
            return self::STUDENT_MAX_MINUTES;
        }

        if ($userType === User::USER_TYPE_FACULTY_STAFF) {
            return self::EMPLOYEE_LIBRARY_MAX_MINUTES;
        }

        return null;
    }

    public static function validationMessage(int $maxMinutes): string
    {
        $maxHours = $maxMinutes / 60;

        return "You have exceeded your maximum booking limit of {$maxHours} hours for your account type.";
    }

    /**
     * @return string|null Error message when duration exceeds cap; null when allowed.
     */
    public static function messageIfDurationExceedsCap(User $user, Space $space, int $durationMinutes): ?string
    {
        $maxMinutes = self::maxMinutesFor($user, $space);
        if ($maxMinutes === null) {
            return null;
        }

        if ($durationMinutes > $maxMinutes) {
            return self::validationMessage($maxMinutes);
        }

        return null;
    }
}
