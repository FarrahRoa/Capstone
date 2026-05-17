<?php

namespace App\Support;

use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Students ({@see User::hasStudentRole()}) may book only the Confab assignment pool.
 */
final class StudentSpaceAccess
{
    public const CONFAB_ONLY_MESSAGE = 'Students may only reserve Confab spaces.';

    public static function mayReserveSpace(Space $space): bool
    {
        return $space->type === Space::TYPE_CONFAB && $space->isConfabAssignmentPool();
    }

    public static function isNumberedConfabShowcaseSpace(Space $space): bool
    {
        if ($space->type !== Space::TYPE_CONFAB || $space->isConfabAssignmentPool()) {
            return false;
        }

        $slug = strtolower((string) $space->slug);
        if (preg_match('/^confab-[1-6]$/', $slug) === 1) {
            return true;
        }

        return preg_match('/^Confab [1-6]$/i', trim((string) $space->name)) === 1;
    }

    public static function isMedicalConfabShowcaseSpace(Space $space): bool
    {
        return $space->type === Space::TYPE_MEDICAL_CONFAB;
    }

    public static function mayShowInStudentShowcase(User $user, Space $space): bool
    {
        if (! $user->hasStudentRole()) {
            return false;
        }

        if (self::isNumberedConfabShowcaseSpace($space)) {
            return true;
        }

        if (self::isMedicalConfabShowcaseSpace($space)) {
            return (bool) $user->med_confab_eligible;
        }

        return false;
    }

    /**
     * Image preview carousel on the student dashboard (not bookable list).
     *
     * @param  Collection<int, Space>  $spaces
     * @return Collection<int, Space>
     */
    public static function filterShowcaseSpaces(Collection $spaces, User $user): Collection
    {
        return $spaces
            ->filter(fn (Space $space) => self::mayShowInStudentShowcase($user, $space))
            ->values();
    }

    /**
     * @param  Collection<int, Space>  $spaces
     * @return Collection<int, Space>
     */
    public static function filterBookableSpaces(Collection $spaces): Collection
    {
        return $spaces
            ->filter(fn (Space $space) => self::mayReserveSpace($space))
            ->values();
    }

    public static function blockedMessageForSpace(Space $space): ?string
    {
        if (self::mayReserveSpace($space)) {
            return null;
        }

        if ($space->type === Space::TYPE_CONFAB && ! $space->isConfabAssignmentPool()) {
            return 'Reserve the general Confab slot; a specific confab room is assigned when staff approves your request.';
        }

        return self::CONFAB_ONLY_MESSAGE;
    }

    public static function blockedMessageForUserAndSpace(User $user, Space $space): ?string
    {
        if (! $user->hasStudentRole()) {
            return null;
        }

        return self::blockedMessageForSpace($space);
    }
}
