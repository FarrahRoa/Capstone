<?php

namespace App\Support;

use App\Models\User;

/**
 * Relative priority for conflict resolution when an admin global override displaces other bookings.
 * Lower integer = higher institutional priority. Unknown affiliations default to general tier.
 *
 * This is intentionally data-driven on affiliation/office names, not space slugs.
 */
final class ReservationUserPriority
{
    public const TIER_OP = 10;

    public const TIER_EXECUTIVE = 20;

    public const TIER_ACADEMIC_STAFF = 30;

    public const TIER_GENERAL = 40;

    public static function rank(User $user): int
    {
        $user->loadMissing('role', 'office');

        $officeName = self::normalizedOfficeLabel($user);
        if ($officeName !== '') {
            if (self::matchesAny($officeName, [
                'office of the president',
            ])) {
                return self::TIER_OP;
            }
            if (self::matchesAny($officeName, [
                'office of the vice-president higher education',
                'office of the vice president',
                'office of the vice president for higher education',
                'ovphe',
                'treasurer',
                'finance',
                'scholarship',
            ])) {
                return self::TIER_EXECUTIVE;
            }
        }

        $type = (string) ($user->user_type ?? '');
        if ($type === User::USER_TYPE_FACULTY_STAFF) {
            return self::TIER_ACADEMIC_STAFF;
        }

        return self::TIER_GENERAL;
    }

    /**
     * When an admin override targets a reservation owned by $subject, only displaces bookings whose
     * owners have a strictly worse (greater) priority rank. Higher-priority conflicts block the override.
     *
     * @param  array<int, User>  $conflictOwnersByReservationId
     * @return array{0: bool, 1: string|null} [allowed, blocking message]
     */
    public static function assertDisplacementsAllowed(User $subject, array $conflictOwnersByReservationId): array
    {
        $subjectRank = self::rank($subject);

        foreach ($conflictOwnersByReservationId as $owner) {
            if (self::rank($owner) < $subjectRank) {
                return [false, 'A higher-priority booking conflicts with this override. Resolve it manually first.'];
            }
        }

        return [true, null];
    }

    private static function normalizedOfficeLabel(User $user): string
    {
        $fromOffice = trim((string) ($user->office?->name ?? ''));
        if ($fromOffice !== '') {
            return strtolower($fromOffice);
        }

        return strtolower(trim((string) ($user->college_office ?? '')));
    }

    /**
     * @param  array<int, string>  $needlesLower
     */
    private static function matchesAny(string $haystackLower, array $needlesLower): bool
    {
        foreach ($needlesLower as $n) {
            if (str_contains($haystackLower, $n)) {
                return true;
            }
        }

        return false;
    }
}
