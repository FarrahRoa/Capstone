<?php

namespace App\Support;

use App\Models\PolicyDocument;
use Carbon\Carbon;

/**
 * 4:30 PM Manila submission cutoff constants.
 * Submission timing is enforced via {@see PolicyDocument::isPastReservationCutoff()} (current time only).
 */
final class BookingSlotCutoff
{
    public const CUTOFF_HHMM = '16:30';

    public const CUTOFF_HOUR = 16;

    public const CUTOFF_MINUTE = 30;

    public static function cutoffMinutes(): int
    {
        return self::CUTOFF_HOUR * 60 + self::CUTOFF_MINUTE;
    }

    public static function slotStartMinutesAtOrAfterCutoff(int $startMinutes): bool
    {
        return $startMinutes >= self::cutoffMinutes();
    }

    /**
     * @deprecated Submission cutoff uses {@see PolicyDocument::isPastReservationCutoff()} (server clock only).
     */
    public static function reservationStartAtOrAfterCutoff(Carbon $start, string $tz): bool
    {
        $local = $start->copy()->timezone($tz);

        return ($local->hour * 60 + $local->minute) >= self::cutoffMinutes();
    }

    /** @deprecated Use {@see PolicyDocument::reservationCutoffValidationMessage()}. */
    public static function validationMessage(): string
    {
        return PolicyDocument::reservationCutoffValidationMessage();
    }

    /** @deprecated Use {@see PolicyDocument::reservationCutoffValidationMessage()}. */
    public static function cutoffBlackoutMessage(): string
    {
        $hours = PolicyDocument::decodedOperatingHours();
        $resume = self::formatHhmm12((string) ($hours['day_start'] ?? '06:00'));

        return PolicyDocument::reservationCutoffValidationMessage();
    }

    public static function morningResetMinutes(): int
    {
        return self::wallClockToMinutes((string) (PolicyDocument::decodedOperatingHours()['day_start'] ?? '06:00'));
    }

    public static function wallClockToMinutes(string $hhmm): int
    {
        $normalized = PolicyDocument::normalizeWallClockHhmm($hhmm, '00:00');
        $parts = explode(':', $normalized);
        $h = isset($parts[0]) ? (int) $parts[0] : 0;
        $m = isset($parts[1]) ? (int) $parts[1] : 0;

        return $h * 60 + $m;
    }

    public static function formatHhmm12(string $hhmm): string
    {
        try {
            $anchor = Carbon::parse('2000-01-01 '.$hhmm, ReservationLeadTimePolicy::TZ);

            return $anchor->format('g:i A');
        } catch (\Throwable) {
            return $hhmm;
        }
    }
}
