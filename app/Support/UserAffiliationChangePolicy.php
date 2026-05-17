<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

/**
 * Monthly limit for students (college) and employees (office) changing affiliation via account settings.
 */
final class UserAffiliationChangePolicy
{
    public const BLOCKED_MESSAGE = 'You can only change your college or office once every month.';

    public static function appliesTo(User $user): bool
    {
        if ($user->isAdminPortalAccount()) {
            return false;
        }

        $type = $user->user_type ?? User::getUserTypeFromEmail($user->email);

        return in_array($type, [User::USER_TYPE_STUDENT, User::USER_TYPE_FACULTY_STAFF], true);
    }

    /**
     * First day (start of day) when the user may change affiliation again, or null if eligible now.
     */
    public static function nextChangeAllowedOn(User $user): ?Carbon
    {
        if (! self::appliesTo($user) || $user->last_affiliation_changed_at === null) {
            return null;
        }

        return $user->last_affiliation_changed_at->copy()->addMonth()->startOfDay();
    }

    public static function canChangeAffiliation(User $user): bool
    {
        $next = self::nextChangeAllowedOn($user);

        if ($next === null) {
            return true;
        }

        return now()->startOfDay()->gte($next);
    }

    public static function studentCollegeChanged(User $user, ?int $newCollegeId): bool
    {
        return (int) ($user->college_id ?? 0) !== (int) ($newCollegeId ?? 0);
    }

    public static function employeeOfficeChanged(User $user, ?int $newOfficeId): bool
    {
        return (int) ($user->office_id ?? 0) !== (int) ($newOfficeId ?? 0);
    }

    /**
     * @return array{college_id?: int|null, office_id?: int|null, college_office?: string}|null
     */
    public static function resolveAffiliationUpdate(User $user, ?int $collegeId, ?int $officeId): ?array
    {
        $type = $user->user_type ?? User::getUserTypeFromEmail($user->email);

        if ($type === User::USER_TYPE_STUDENT && $collegeId !== null) {
            $college = \App\Models\College::query()->find($collegeId);
            if (! $college) {
                return null;
            }

            return [
                'college_id' => $college->id,
                'office_id' => null,
                'college_office' => $college->name,
            ];
        }

        if ($type === User::USER_TYPE_FACULTY_STAFF && $officeId !== null) {
            $office = \App\Models\Office::query()->find($officeId);
            if (! $office) {
                return null;
            }

            return [
                'college_id' => null,
                'office_id' => $office->id,
                'college_office' => $office->name,
            ];
        }

        return null;
    }
}
