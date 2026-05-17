<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

/**
 * Hard business rules for when standard users may place or move reservations (Philippines civil time).
 *
 * - Same calendar day: never bookable (slots may display; Book disabled).
 * - Before 4:30 PM: may book tomorrow and later dates.
 * - From 4:30 PM through 8:59 AM the next morning: no bookings for tomorrow or any later date.
 * - From 9:00 AM onward (until the next 4:30 PM): may book tomorrow and later dates again.
 *
 * {@see User::isAdmin()} Librarians/staff without the Admin role remain subject to these rules.
 */
final class ReservationLeadTimePolicy
{
    public const TZ = 'Asia/Manila';

    public const SAME_DAY_DENIED_MESSAGE = 'Same-day reservations are not allowed.';

    public const CUTOFF_BLACKOUT_MESSAGE = 'Reservations are unavailable after 4:30 PM. Booking resumes at 9:00 AM tomorrow.';

    /** @deprecated Use {@see SAME_DAY_DENIED_MESSAGE} or {@see CUTOFF_BLACKOUT_MESSAGE}. */
    public const ERROR_MESSAGE = 'Reservations for the selected date are no longer allowed based on system rules.';

    private const NEXT_DAY_CUTOFF_HOUR = 16;

    private const NEXT_DAY_CUTOFF_MINUTE = 30;

    private const MORNING_RESET_HOUR = 9;

    private const MORNING_RESET_MINUTE = 0;

    public static function isExempt(?User $user): bool
    {
        return $user !== null && $user->isAdmin();
    }

    /**
     * Evening blackout: from 4:30 PM inclusive until 9:00 AM exclusive (Manila wall clock).
     */
    public static function isInEveningBookingBlackout(Carbon $now): bool
    {
        $local = $now->copy()->timezone(self::TZ);
        $minutes = $local->hour * 60 + $local->minute;
        $cutoffMinutes = self::NEXT_DAY_CUTOFF_HOUR * 60 + self::NEXT_DAY_CUTOFF_MINUTE;
        $resetMinutes = self::MORNING_RESET_HOUR * 60 + self::MORNING_RESET_MINUTE;

        return $minutes >= $cutoffMinutes || $minutes < $resetMinutes;
    }

    /**
     * Whether the reservation half-open interval [start, end) overlaps Manila civil date $dayYmd
     * (interpreted at {@see self::TZ} midnight boundaries).
     */
    private static function rangeOverlapsManilaCalendarDay(Carbon $start, Carbon $end, string $dayYmd): bool
    {
        $anchor = Carbon::parse($dayYmd.' 00:00:00', self::TZ);
        $next = $anchor->copy()->addDay();

        return $start->copy()->timezone(self::TZ)->lt($next) && $end->copy()->timezone(self::TZ)->gt($anchor);
    }

    /**
     * @return non-empty-string|null validation error message for non-exempt users, or null when allowed.
     */
    public static function messageIfBlockedFor(?User $user, Carbon $start, Carbon $end, ?Carbon $now = null): ?string
    {
        if (self::isExempt($user)) {
            return null;
        }

        $now ??= Carbon::now(self::TZ);

        $todayYmd = $now->copy()->timezone(self::TZ)->format('Y-m-d');

        if (self::rangeOverlapsManilaCalendarDay($start, $end, $todayYmd)) {
            return self::SAME_DAY_DENIED_MESSAGE;
        }

        if (self::isInEveningBookingBlackout($now)) {
            $tomorrowStart = $now->copy()->timezone(self::TZ)->startOfDay()->addDay();
            if ($start->copy()->timezone(self::TZ)->gte($tomorrowStart)) {
                return self::CUTOFF_BLACKOUT_MESSAGE;
            }
        }

        return null;
    }
}
